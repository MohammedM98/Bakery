/**
 * The one way the app talks to /api/v1 (docs/api.md). Every refusal comes back as an ApiError with the
 * server's code and Arabic message; no connection at all is the code "network".
 */

export class ApiError extends Error {
  code: string;
  status: number;
  errors?: Record<string, string[]>;

  // No parameter properties: Node's type stripping (npm test) runs this file as it is.
  constructor(code: string, message: string, status = 0, errors?: Record<string, string[]>) {
    super(message);
    this.code = code;
    this.status = status;
    this.errors = errors;
  }

  /** The first validation message, or the message itself: what a form shows under its fields. */
  get text(): string {
    const first = this.errors && Object.values(this.errors)[0]?.[0];
    return first ?? this.message;
  }
}

export type Request = <T>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, body?: unknown) => Promise<T>;

export const API_URL = (process.env.EXPO_PUBLIC_API_URL ?? 'http://localhost:8000/api/v1').replace(/\/+$/, '');

export const NETWORK_MESSAGE = 'لا يوجد اتصال بالخادم';

/** A request function bound to a server and a token source. Node tests build one against a real server. */
export function createClient(base: string, token: () => string | null, timeoutMs = 20000): Request {
  return async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    let res: Response;
    try {
      const t = token();
      res = await fetch(`${base}/${path.replace(/^\//, '')}`, {
        method,
        signal: controller.signal,
        headers: {
          Accept: 'application/json',
          ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
          ...(t ? { Authorization: `Bearer ${t}` } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
      });
    } catch {
      throw new ApiError('network', NETWORK_MESSAGE);
    } finally {
      clearTimeout(timer);
    }
    const json = await res.json().catch(() => null);
    if (!res.ok) {
      throw new ApiError(json?.code ?? (res.status === 401 ? 'unauthenticated' : 'server_error'), json?.message ?? 'تعذّر تنفيذ الطلب في الخادم', res.status, json?.errors);
    }
    return json as T;
  } as Request;
}
