/**
 * The offline copy and its sync (CLAUDE.md section 13, docs/api.md "المزامنة دون إنترنت"), as pure functions
 * on one LocalData value so tests run them in Node. src/state/app.tsx keeps the value in React state and
 * src/sync/storage.ts writes it to SQLite.
 *
 * - An operation recorded on the phone goes into the queue with its client_uuid and writes its expected
 *   movements locally with number = null ("ينتظر المزامنة").
 * - push: accepted / duplicate → drop the waiting rows, keep the server's movements and customer balances.
 *   rejected + retryable → stays waiting. rejected → stays on the sync screen with its reason.
 * - pull: replaces changed customers and banks, adds movements, applies tombstones; `full` drops the copy first.
 */

import type { Request } from '../api/client.ts';
import { ApiError } from '../api/client.ts';
import type { ApiBank, ApiCustomer, ApiMovement, Me, OperationResult, PullResponse, PushResult, QueuedOperation } from '../api/types.ts';
import { formatFlourBalance, formatMoney, fromCents, toCents } from '../lib/format.ts';

export interface LocalData {
  me: Me | null;
  cursor: string | null;
  lastSyncAt: string | null;
  customers: ApiCustomer[];
  movements: ApiMovement[];
  banks: ApiBank[];
  queue: QueuedOperation[];
  /** Last answers of online-only reads (dashboard, sales, log...), shown offline with their time. */
  cache: Record<string, { at: string; data: unknown }>;
  /** Signed out by the server with work not yet sent: whose it is, kept for that user's next login. */
  heldFor: { id: number; email: string } | null;
  /** Server clock − phone clock (ms) when `me` last came: "today" and dates follow the server, not the phone. */
  clockOffset: number;
}

export const EMPTY: LocalData = { me: null, cursor: null, lastSyncAt: null, customers: [], movements: [], banks: [], queue: [], cache: {}, heldFor: null, clockOffset: 0 };

/** Most operations in one push (SyncController::BATCH). */
export const BATCH = 100;
/** Accepted operations kept for the sync screen's "sent recently" and for printing their receipt. */
const SENT_KEPT = 20;

/** A movement's storage key: the server number, or the waiting operation and its position. */
export const movementKey = (m: ApiMovement) => (m.number != null ? `n${m.number}` : `p${m.client_uuid}:${m.client_seq ?? 0}`);

const byName = (a: { name: string }, b: { name: string }) => a.name.localeCompare(b.name, 'ar');

function upsert<T>(list: T[], rows: T[], key: (x: T) => string | number): T[] {
  if (!rows.length) return list;
  const map = new Map(list.map((x) => [key(x), x]));
  for (const r of rows) map.set(key(r), r);
  return Array.from(map.values());
}

/** Adds an operation and what it is expected to write until the server answers. */
export function enqueue(d: LocalData, op: QueuedOperation, effects: { movements?: ApiMovement[]; customer?: ApiCustomer } = {}): LocalData {
  return {
    ...d,
    queue: [...d.queue, op],
    movements: effects.movements?.length ? [...d.movements, ...effects.movements.map((m, i) => ({ ...m, number: null, client_uuid: op.client_uuid, client_seq: i }))] : d.movements,
    customers: effects.customer ? [...d.customers, { ...effects.customer, client_uuid: op.client_uuid }].sort(byName) : d.customers,
  };
}

/** The operations to send now, in the order they were recorded, in the shape sync/push takes. */
export function pushBatch(d: LocalData) {
  return d.queue
    .filter((q) => q.status === 'pending')
    .slice(0, BATCH)
    .map((q) => ({ client_uuid: q.client_uuid, type: q.type, client_created_at: q.client_created_at, payload: q.payload }));
}

/** Server movements of customers only: the walk-in buyer has no statement on the phone. */
const customerMovements = (rows: ApiMovement[] | undefined) => (rows ?? []).filter((m) => m.customer_id != null);

/** The server's answer to one operation replaces what the phone expected. */
function accept(d: LocalData, op: QueuedOperation, data: OperationResult, now: string): LocalData {
  let customers = d.customers;
  let movements = d.movements.filter((m) => m.client_uuid !== op.client_uuid);
  if (op.type === 'customer.create' && data.customer) {
    // The customer added offline gets its server id; waiting operations of it follow.
    const local = customers.find((c) => c.client_uuid === op.client_uuid);
    customers = customers.filter((c) => c.client_uuid !== op.client_uuid);
    if (local) movements = movements.map((m) => (m.customer_id === local.id ? { ...m, customer_id: data.customer!.id } : m));
  }
  if (data.customer) customers = upsert(customers, [data.customer], (c) => c.id).sort(byName);
  movements = upsert(movements, customerMovements(data.movements), (m) => movementKey(m));
  const sent: QueuedOperation = { ...op, status: 'sent', result: data, sent_at: now, code: undefined, reason: undefined };
  const queue = d.queue.filter((q) => q.client_uuid !== op.client_uuid);
  const kept = queue.filter((q) => q.status === 'sent').slice(-(SENT_KEPT - 1));
  return { ...d, customers, movements, queue: [...queue.filter((q) => q.status !== 'sent'), ...kept, sent] };
}

/** Applies the results of one push, matched by client_uuid. */
export function applyPush(d: LocalData, results: PushResult[], now: string): LocalData {
  let next = d;
  for (const r of results) {
    const op = next.queue.find((q) => q.client_uuid === r.client_uuid);
    if (!op) continue;
    if (r.status === 'accepted' || r.status === 'duplicate') next = accept(next, op, r.data ?? {}, now);
    else if (!r.retryable) {
      const reason = (r.errors && Object.values(r.errors)[0]?.[0]) ?? r.message ?? 'رفض الخادم العملية';
      next = { ...next, queue: next.queue.map((q) => (q === op ? { ...q, status: 'rejected', code: r.code, reason } : q)) };
    }
  }
  return next;
}

/** The user dismissed a refused operation: it and what it wrote locally go. */
export function dismiss(d: LocalData, uuid: string): LocalData {
  const local = d.customers.find((c) => c.client_uuid === uuid);
  return {
    ...d,
    queue: d.queue.filter((q) => q.client_uuid !== uuid),
    movements: d.movements.filter((m) => m.client_uuid !== uuid && (!local || m.customer_id !== local.id)),
    customers: local ? d.customers.filter((c) => c !== local) : d.customers,
  };
}

/** Send a refused operation again as it is (after the cause is fixed: a permission given back, a renewal...). */
export function retry(d: LocalData, uuid: string): LocalData {
  return { ...d, queue: d.queue.map((q) => (q.client_uuid === uuid ? { ...q, status: 'pending', code: undefined, reason: undefined } : q)) };
}

const waiting = (m: ApiMovement) => m.number == null;

/** A fresh session (login, me, pull) with the server's clock against the phone's at that moment. */
export function withMe(d: LocalData, me: Me, now: string): LocalData {
  const offset = Date.parse(me.server_time) - Date.parse(now);
  return { ...d, me, clockOffset: Number.isFinite(offset) ? offset : d.clockOffset };
}

/** Applies one sync/pull page. */
export function applyPull(d: LocalData, r: PullResponse, now: string): LocalData {
  const { cursor, has_more: _more, full, customers, movements, banks, deleted, ...me } = r;
  // An expired bakery's answer echoes the cursor it got, "" on a first pull: that is still no copy.
  let next: LocalData = { ...withMe(d, me, now), cursor: cursor || null, lastSyncAt: now };
  if (!me.subscription?.active) return next;
  if (full) {
    // Keep what waits for the server (and the customers it was recorded for); drop the rest of the copy.
    const keep = new Set(next.movements.filter(waiting).map((m) => m.customer_id));
    next = { ...next, customers: next.customers.filter((c) => c.id < 0 || keep.has(c.id)), movements: next.movements.filter(waiting), banks: [] };
  }
  const gone = new Set(deleted.customers);
  return {
    ...next,
    customers: upsert(next.customers, customers, (c) => c.id)
      .filter((c) => !gone.has(c.id))
      .sort(byName),
    movements: upsert(next.movements, movements, (m) => movementKey(m)).filter((m) => m.customer_id == null || !gone.has(m.customer_id)),
    banks: upsert(next.banks, banks, (b) => b.id)
      .filter((b) => !deleted.banks.includes(b.id))
      .sort(byName),
  };
}

/**
 * The server signed the user out (token revoked, account stopped) before the queue was sent: keep only what has
 * not reached it (and the customers it was recorded for), so nothing is lost; afterLogin() hands it back.
 */
export function holdUnsent(d: LocalData): LocalData {
  const unsent = d.queue.filter((q) => q.status !== 'sent');
  if (!unsent.length || !d.me) return EMPTY;
  const uuids = new Set(unsent.map((q) => q.client_uuid));
  const movements = d.movements.filter((m) => m.number == null && uuids.has(m.client_uuid ?? ''));
  const ids = new Set(movements.map((m) => m.customer_id));
  return {
    ...EMPTY,
    heldFor: { id: d.me.user.id, email: d.me.user.email },
    queue: unsent,
    movements,
    customers: d.customers.filter((c) => ids.has(c.id) || uuids.has(c.client_uuid ?? '')),
  };
}

/** A login keeps held work only when it is the same user's (the queue belongs to its user, docs/api.md). */
export function afterLogin(d: LocalData, me: Me, now: string): LocalData {
  return withMe(d.heldFor?.id === me.user.id ? { ...d, heldFor: null } : EMPTY, me, now);
}

/** The first day of movements kept on the phone ("Ymd" at the cursor's end), or null before the first sync. */
export function historyFloor(cursor: string | null): string | null {
  const m = cursor?.match(/\.(\d{4})(\d{2})(\d{2})$/);
  return m ? `${m[1]}-${m[2]}-${m[3]}` : null;
}

export interface Balance {
  /** Hundredths, + = عليه. */
  money: number;
  flour: number;
  /** Operations wait for the server: the numbers are the phone's estimate. */
  pending: boolean;
  moneyText: string;
  flourText: string;
}

/** The server's balances plus what waits on the phone; the server's own text when nothing waits. */
export function balances(d: LocalData): Map<number, Balance> {
  const map = new Map<number, Balance>();
  for (const c of d.customers) {
    map.set(c.id, { money: toCents(c.money_balance), flour: toCents(c.flour_balance), pending: false, moneyText: c.money_balance_text, flourText: c.flour_balance_text });
  }
  for (const m of d.movements) {
    const b = m.number == null && m.customer_id != null ? map.get(m.customer_id) : undefined;
    if (!b) continue;
    if (m.ledger === 'money') b.money += toCents(m.amount);
    else b.flour += toCents(m.amount);
    b.pending = true;
    b.moneyText = formatMoney(fromCents(b.money));
    b.flourText = formatFlourBalance(fromCents(b.flour));
  }
  return map;
}

export type SyncOutcome = { ok: true; sent: number; rejected: number } | { ok: false; error: ApiError };

/**
 * One full sync: push the queue (in batches) then pull until has_more is false. `get` / `set` read and write
 * the current LocalData, so operations recorded meanwhile are kept. Stops at the first request that fails.
 */
export async function sync(request: Request, get: () => LocalData, set: (d: LocalData) => void, now: () => string): Promise<SyncOutcome> {
  let sent = 0;
  let rejected = 0;
  try {
    for (let batch = pushBatch(get()); batch.length; batch = pushBatch(get())) {
      const { results } = await request<{ results: PushResult[] }>('POST', 'sync/push', { operations: batch });
      const before = get();
      set(applyPush(before, results, now()));
      sent += results.filter((r) => r.status !== 'rejected').length;
      rejected += results.filter((r) => r.status === 'rejected' && !r.retryable).length;
      // Retryable refusals stay pending: try them on the next sync, not in a loop now.
      if (results.some((r) => r.status === 'rejected' && r.retryable)) break;
    }
    for (let more = true; more; ) {
      const cursor = get().cursor;
      let page: PullResponse;
      try {
        page = await request<PullResponse>('GET', `sync/pull${cursor ? `?since=${encodeURIComponent(cursor)}` : ''}`);
      } catch (e) {
        if (e instanceof ApiError && e.code === 'invalid_cursor' && cursor) {
          set({ ...get(), cursor: null });
          continue;
        }
        throw e;
      }
      set(applyPull(get(), page, now()));
      more = page.has_more && !!page.subscription?.active;
    }
    return { ok: true, sent, rejected };
  } catch (e) {
    return { ok: false, error: e instanceof ApiError ? e : new ApiError('server_error', String(e)) };
  }
}
