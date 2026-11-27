import NetInfo from '@react-native-community/netinfo';
import { randomUUID } from 'expo-crypto';
import * as React from 'react';
import { AppState as RNAppState, Platform } from 'react-native';

import { API_URL, ApiError, createClient } from '@/api/client';
import { localTime } from '@/lib/format';
import type { ApiCustomer, Ledger, Me, OperationResult, Permission } from '@/api/types';
import { afterLogin, balances as computeBalances, dismiss, EMPTY, enqueue, historyFloor, holdUnsent, retry, sync, type LocalData } from '@/sync/engine';
import * as ops from '@/sync/operations';
import { getToken, load, save, setToken } from '@/sync/storage';

/**
 * The session, the offline copy and every operation (CLAUDE.md section 13). Operations allowed offline are
 * queued with a client_uuid and pushed at once when online; what the server refuses then comes back to the
 * form. Online-only actions call the API directly (`call`), and online-only screens read through useRemote().
 */

export type Result<T = undefined> = { ok: true; data?: T } | { ok: false; error: string; errors?: Record<string, string[]> };
/** An offline-capable operation: queued (waits for the internet) or confirmed by the server with its answer. */
export type OpResult = { ok: true; queued: boolean; result?: OperationResult } | { ok: false; error: string };

/** The bakery's day in Palestine (the server's `local_date`, config app.local_timezone): sales and periods use it. */
export const today = () => localTime(new Date().toISOString()).slice(0, 10);

/** Web only: ?net=off draws the offline states (screenshots). */
const forcedOffline = Platform.OS === 'web' && typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('net') === 'off';

let localIds = -Date.now();
/** The API token: module state, read by the request function outside React's render. */
let currentToken: string | null = null;
const request = createClient(API_URL, () => currentToken);

function useAppModel() {
  const [data, setData] = React.useState<LocalData>(EMPTY);
  const [ready, setReady] = React.useState(false);
  const [online, setOnline] = React.useState(!forcedOffline);
  const [syncing, setSyncing] = React.useState(false);
  /** Why the user was signed out (account stopped, token revoked), shown on the login screen. */
  const [signedOutReason, setSignedOutReason] = React.useState<string | null>(null);

  const ref = React.useRef<LocalData>(EMPTY);
  const writes = React.useRef<Promise<void>>(Promise.resolve());
  const syncChain = React.useRef<Promise<unknown>>(Promise.resolve());
  const onlineRef = React.useRef(online);
  React.useEffect(() => {
    onlineRef.current = online;
  }, [online]);

  const get = React.useCallback(() => ref.current, []);
  const set = React.useCallback((next: LocalData) => {
    const prev = ref.current;
    if (prev === next) return;
    ref.current = next;
    setData(next);
    // Writes run one after another so a slow one never lands after a newer one.
    writes.current = writes.current.then(() => save(prev, next)).catch(() => {});
  }, []);
  const update = React.useCallback((f: (d: LocalData) => LocalData) => set(f(ref.current)), [set]);

  /** `keep`: signed out by the server, so work not yet sent stays for the same user's next login. */
  const signOutLocally = React.useCallback(
    async (reason: string | null, keep: boolean) => {
      currentToken = null;
      await setToken(null);
      set(keep ? holdUnsent(ref.current) : EMPTY);
      setSignedOutReason(reason);
    },
    [set],
  );

  /** Handles what every screen would otherwise repeat: a revoked token or a stopped account signs out. */
  const handle = React.useCallback(
    async (e: unknown): Promise<ApiError> => {
      const err = e instanceof ApiError ? e : new ApiError('server_error', String(e));
      if (err.code === 'unauthenticated' || err.code === 'account_disabled') await signOutLocally(err.code === 'account_disabled' ? err.message : 'انتهت الجلسة. ادخل من جديد', true);
      else if (err.code === 'subscription_expired') {
        const me = await request<Me>('GET', 'me').catch(() => null);
        if (me) update((d) => ({ ...d, me }));
      }
      return err;
    },
    [signOutLocally, update],
  );

  /** Online-only calls (prices, staff, banks, bakeries, editing customers...). */
  const call = React.useCallback(
    async <T,>(method: 'GET' | 'POST' | 'PUT' | 'DELETE', path: string, body?: unknown): Promise<Result<T>> => {
      if (!onlineRef.current) return { ok: false, error: 'يحتاج إنترنت' };
      // Not signed in, or the saved token is not read yet: a request now would be a 401 that signs out.
      if (!currentToken) return { ok: false, error: 'سجّل الدخول أولًا' };
      try {
        return { ok: true, data: await request<T>(method, path, body) };
      } catch (e) {
        const err = await handle(e);
        return { ok: false, error: err.text, errors: err.errors };
      }
    },
    [handle],
  );

  /** One sync at a time; a call while one runs waits and runs again (so a new operation is pushed). */
  const runSync = React.useCallback(() => {
    const next = syncChain.current.then(async () => {
      if (!currentToken || !onlineRef.current) return { ok: false as const, error: new ApiError('network', 'لا يوجد اتصال بالإنترنت') };
      setSyncing(true);
      // A token without its session (the app was closed during the login): fetch it, it tells the role.
      if (!ref.current.me) {
        const me = await request<Me>('GET', 'me').catch(() => null);
        if (me) update((d) => ({ ...d, me }));
      }
      // The platform owner keeps nothing offline: refreshing the session is the whole sync.
      const out =
        ref.current.me?.user.role === 'super_admin'
          ? await request<Me>('GET', 'me').then(
              (me) => (update((d) => ({ ...d, me, lastSyncAt: new Date().toISOString() })), { ok: true as const, sent: 0, rejected: 0 }),
              (e) => ({ ok: false as const, error: e instanceof ApiError ? e : new ApiError('server_error', String(e)) }),
            )
          : await sync(request, get, set, () => new Date().toISOString());
      setSyncing(false);
      if (!out.ok) await handle(out.error);
      return out;
    });
    syncChain.current = next.catch(() => {});
    return next;
  }, [get, set, update, handle]);

  // Start: the saved copy and token, then a sync.
  React.useEffect(() => {
    (async () => {
      const [saved, t] = await Promise.all([load(), getToken()]);
      currentToken = t;
      ref.current = t || saved.heldFor ? saved : EMPTY;
      // No token (signed out): nothing of the last user stays on the phone, except work held for their next login.
      if (!t && !saved.heldFor && saved !== EMPTY) writes.current = save(saved, EMPTY).catch(() => {});
      setData(ref.current);
      setReady(true);
      if (t) runSync();
    })();
  }, [runSync]);

  // Back online, or back to the app: send the queue and fetch what changed.
  React.useEffect(() => {
    if (forcedOffline) return;
    const unNet = NetInfo.addEventListener((s) => {
      const now = s.isConnected !== false && s.isInternetReachable !== false;
      if (now && !onlineRef.current) {
        onlineRef.current = true;
        runSync();
      }
      setOnline(now);
    });
    const unApp = RNAppState.addEventListener('change', (s) => s === 'active' && runSync());
    return () => {
      unNet();
      unApp.remove();
    };
  }, [runSync]);

  const me = data.me;
  const user = me ? { ...me.user, permissions: me.permissions } : null;
  const can = React.useCallback((p: Permission) => !!me?.permissions.includes(p), [me]);
  const balances = React.useMemo(() => computeBalances(data), [data]);
  const pendingCount = data.queue.filter((q) => q.status === 'pending').length;
  const rejectedCount = data.queue.filter((q) => q.status === 'rejected').length;

  const ctx = (): ops.Ctx => ({ uuid: randomUUID(), now: new Date().toISOString(), today: today(), by: me?.user.name ?? '', customers: ref.current.customers });

  /** Queues an operation and, online, sends it now: a refusal goes back to the form instead of the sync screen. */
  const record = async (built: ops.Built): Promise<OpResult> => {
    if (me?.subscription && !me.subscription.active) return { ok: false, error: 'انتهى الاشتراك. لا يمكن تسجيل عمليات جديدة' };
    update((d) => enqueue(d, built.op, { movements: built.movements, customer: built.customer }));
    if (!onlineRef.current) return { ok: true, queued: true };
    await runSync();
    const q = ref.current.queue.find((x) => x.client_uuid === built.op.client_uuid);
    if (q?.status === 'sent') return { ok: true, queued: false, result: q.result };
    if (q?.status === 'rejected') {
      update((d) => dismiss(d, q.client_uuid));
      return { ok: false, error: q.reason ?? 'رفض الخادم العملية' };
    }
    return { ok: true, queued: true };
  };

  /**
   * Offline, a sale keeps the last price the phone got (section 13). Online it is sent at once without one, so
   * the server applies the current price, as for any direct request (the phone's copy may be minutes old).
   */
  const devicePrice = (price: string | undefined) => (onlineRef.current ? null : (price ?? '0.00'));
  const positive = (n: number, error: string) => (n > 0 && n <= 100000 ? null : { ok: false as const, error });

  return {
    state: {
      ready,
      online,
      syncing,
      ...data,
      /** false = a phone that has never synced (the "offline with no data" state). */
      hasLocalData: data.cursor != null,
      historyFloor: historyFloor(data.cursor),
      subscriptionExpired: !!me?.subscription && !me.subscription.active,
      signedOutReason,
    },
    me,
    user,
    can,
    balances,
    pendingCount,
    rejectedCount,
    call,
    syncNow: runSync,

    setCache(key: string, value: unknown) {
      update((d) => {
        const entries = Object.entries({ ...d.cache, [key]: { at: new Date().toISOString(), data: value } });
        // Keep the 30 most recent answers.
        return { ...d, cache: Object.fromEntries(entries.sort((a, b) => (a[1].at < b[1].at ? 1 : -1)).slice(0, 30)) };
      });
    },

    // ---- session -------------------------------------------------------------------------------
    async login(email: string, password: string): Promise<Result> {
      try {
        const r = await request<Me & { token: string }>('POST', 'login', { email: email.trim(), password, device_name: `تطبيق المخبز (${Platform.OS})` });
        const { token: t, ...meData } = r;
        currentToken = t;
        await setToken(t);
        setSignedOutReason(null);
        set(afterLogin(ref.current, meData));
        if (meData.user.role !== 'super_admin') runSync();
        return { ok: true };
      } catch (e) {
        const err = e instanceof ApiError ? e : new ApiError('server_error', String(e));
        return { ok: false, error: err.code === 'network' ? 'تعذّر الوصول إلى الخادم. الدخول يحتاج إنترنت' : err.text };
      }
    },
    /** Sends what waits first (the queue belongs to this user), then revokes this phone's token. */
    async logout(force = false): Promise<{ ok: true } | { ok: false; pending: number }> {
      if (onlineRef.current && ref.current.queue.some((q) => q.status === 'pending')) await runSync();
      const pending = ref.current.queue.filter((q) => q.status === 'pending').length;
      if (pending && !force) return { ok: false, pending };
      if (onlineRef.current) await request('POST', 'logout').catch(() => {});
      await signOutLocally(null, false);
      return { ok: true };
    },

    // ---- operations that work offline -------------------------------------------------------------
    recordSale(i: { kg: number; paid: boolean; buyerName: string }): Promise<OpResult> | OpResult {
      return positive(i.kg, 'اكتب الكمية') ?? record(ops.sale(ctx(), { ...i, price: devicePrice(me?.prices?.regular_price_per_kg) }));
    },
    recordFlourSale(i: { customerId: number; kg: number; paid: boolean }): Promise<OpResult> | OpResult {
      return positive(i.kg, 'اكتب الكمية') ?? record(ops.flourSale(ctx(), { ...i, fee: devicePrice(me?.prices?.flour_exchange_fee_per_kg), shownFee: me?.prices?.flour_exchange_fee_per_kg }));
    },
    recordDeposit(i: { customerId: number; kg: number; note: string }): Promise<OpResult> | OpResult {
      return positive(i.kg, 'اكتب الكمية') ?? record(ops.deposit(ctx(), i));
    },
    recordPayment(i: Parameters<typeof ops.payment>[1]): Promise<OpResult> | OpResult {
      if (i.method === 'transfer' && (!i.fromBank || !i.toBank)) return { ok: false, error: 'اختر البنك المرسل والمستقبل' };
      return positive(i.amount, 'المبلغ يجب أن يكون أكبر من صفر') ?? record(ops.payment(ctx(), i));
    },
    recordAdjustment(i: { customerId: number; ledger: Ledger; direction: 'debit' | 'credit'; amount: number; reason: string; corrects: number | null }): Promise<OpResult> | OpResult {
      if (!i.reason.trim()) return { ok: false, error: 'اكتب سبب التسوية' };
      return positive(i.amount, 'اكتب المبلغ أو الكمية') ?? record(ops.adjustment(ctx(), i));
    },
    async addCustomer(i: { name: string; phone: string; notes: string; openingFlour: number }): Promise<OpResult & { id?: number }> {
      if (!i.name.trim()) return { ok: false, error: 'اكتب اسم الزبون' };
      const built = ops.customer(ctx(), i, localIds--);
      const r = await record(built);
      const id = r.ok && r.result?.customer ? r.result.customer.id : built.customer!.id;
      return r.ok ? { ...r, id } : r;
    },

    dismissRejected(uuid: string) {
      update((d) => dismiss(d, uuid));
    },
    retryRejected(uuid: string) {
      update((d) => retry(d, uuid));
      return runSync();
    },

    // ---- online only: write, then pull so the copy follows ----------------------------------------
    async online<T>(method: 'POST' | 'PUT' | 'DELETE', path: string, body?: Record<string, unknown>): Promise<Result<T>> {
      const r = await call<T>(method, path, body && method === 'POST' ? { client_uuid: randomUUID(), ...body } : body);
      if (r.ok) runSync();
      return r;
    },
    /** Changing the password revokes every token (User::updated), so the server hands this phone a new one. */
    // In the sync chain: a sync between the revocation and the new token would get 401 and sign out.
    changePassword(current: string, password: string): Promise<Result> {
      const run = syncChain.current.then(async (): Promise<Result> => {
        const r = await call<{ token: string }>('PUT', 'password', { current_password: current, password, password_confirmation: password, device_name: `تطبيق المخبز (${Platform.OS})` });
        if (!r.ok) return r;
        currentToken = r.data!.token;
        await setToken(r.data!.token);
        return { ok: true };
      });
      syncChain.current = run.catch(() => {});
      return run;
    },
    customer(id: number): ApiCustomer | undefined {
      return data.customers.find((c) => c.id === id);
    },
  };
}

export type AppModel = ReturnType<typeof useAppModel>;
const AppContext = React.createContext<AppModel | null>(null);

export function AppProvider({ children }: { children: React.ReactNode }) {
  const model = useAppModel();
  return <AppContext.Provider value={model}>{children}</AppContext.Provider>;
}

export function useApp(): AppModel {
  const v = React.useContext(AppContext);
  if (!v) throw new Error('useApp outside AppProvider');
  return v;
}

/**
 * An online-only read (dashboard, sales, sales log, staff, bakeries...): fetched when online and again after
 * every sync, kept as the last answer for offline, where the screen says how old it is.
 */
export function useRemote<T>(path: string | null) {
  const app = useApp();
  const cached = path ? (app.state.cache[path] as { at: string; data: T } | undefined) : undefined;
  const [error, setError] = React.useState<string | null>(null);
  const { call, setCache } = app;
  const { online, lastSyncAt, ready } = app.state;
  const [tick, setTick] = React.useState(0);

  React.useEffect(() => {
    if (!path || !online || !ready) return;
    let live = true;
    call<T>('GET', path).then((r) => {
      if (!live) return;
      if (r.ok) {
        setError(null);
        setCache(path, r.data);
      } else setError(r.error);
    });
    return () => {
      live = false;
    };
    // setCache changes every render; the cache is written, never read, here.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, online, ready, lastSyncAt, tick, call]);

  return { data: cached?.data ?? null, at: cached?.at ?? null, error, stale: !online && !!cached, reload: () => setTick((t) => t + 1) };
}
