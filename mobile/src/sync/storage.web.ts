import { EMPTY, type LocalData } from './engine';

/**
 * The web build's copy (src/sync/storage.ts is the phone's SQLite one; Metro picks this file for web).
 * shortcut: one JSON snapshot in localStorage (~5 MB); the web build is the demo and screenshot target.
 */

const KEY = 'bakery.local';
const TOKEN_KEY = 'bakery.token';

export async function load(): Promise<LocalData> {
  try {
    const raw = globalThis.localStorage?.getItem(KEY);
    return raw ? { ...EMPTY, ...JSON.parse(raw) } : EMPTY;
  } catch {
    return EMPTY;
  }
}

let timer: ReturnType<typeof setTimeout> | null = null;

export async function save(_prev: LocalData, next: LocalData): Promise<void> {
  if (timer) clearTimeout(timer);
  timer = setTimeout(() => {
    try {
      globalThis.localStorage?.setItem(KEY, JSON.stringify(next));
    } catch {}
  }, 300);
}

export async function getToken(): Promise<string | null> {
  return globalThis.localStorage?.getItem(TOKEN_KEY) ?? null;
}

export async function setToken(token: string | null): Promise<void> {
  if (token) globalThis.localStorage?.setItem(TOKEN_KEY, token);
  else globalThis.localStorage?.removeItem(TOKEN_KEY);
}
