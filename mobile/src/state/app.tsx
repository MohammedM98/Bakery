import * as React from 'react';
import { Platform } from 'react-native';

import { formatDate, fromCents, toCents } from '@/lib/format';
import {
  BANKS,
  BAKERY,
  CUSTOMERS,
  MOCK_USERS,
  MOVEMENTS,
  OTHER_BAKERIES,
  SALES,
  SEED_QUEUE,
  STAFF,
  TODAY,
  addDays,
  type MockUserKey,
} from '@/mock/data';
import type {
  ApiBakery,
  ApiBank,
  ApiCustomer,
  ApiMovement,
  ApiSale,
  ApiStaff,
  ApiUser,
  Ledger,
  OperationKind,
  Permission,
  QueuedOperation,
  StaffPermission,
} from '@/mock/types';

/**
 * The app's state while there is no server (session 10). Every operation below is the offline path
 * from CLAUDE.md section 13: it is written locally at once, and either "sent" (online: the mock server
 * gives it a number) or queued with a client id until the next sync. Session 11 replaces the bodies
 * of these functions with API calls and SQLite, keeping the same signatures.
 */

type Result = { ok: true } | { ok: false; error: string };

export interface AppState {
  userKey: MockUserKey | null;
  online: boolean;
  /** false = a new phone that has never synced (the "offline with no data" state). */
  hasLocalData: boolean;
  subscriptionExpired: boolean;
  bakery: ApiBakery;
  customers: ApiCustomer[];
  movements: ApiMovement[];
  sales: ApiSale[];
  banks: ApiBank[];
  staff: ApiStaff[];
  bakeries: ApiBakery[];
  queue: QueuedOperation[];
  lastSyncAt: string;
}

/** Web only: ?user=owner|staff|noPermissions|admin|out&net=off&expired=1&empty=1 (screenshots, dev menu). */
function fromUrl(): Partial<AppState> {
  if (Platform.OS !== 'web' || typeof window === 'undefined') return {};
  const q = new URLSearchParams(window.location.search);
  const out: Partial<AppState> = {};
  const user = q.get('user');
  if (user === 'out') out.userKey = null;
  else if (user && user in MOCK_USERS) out.userKey = user as MockUserKey;
  if (q.get('net') === 'off') out.online = false;
  if (q.get('expired') === '1') out.subscriptionExpired = true;
  if (q.get('empty') === '1') out.hasLocalData = false;
  return out;
}

/** ?pending=1: two operations recorded offline and waiting (sync screen, pending marks). */
function withPendingDemo(s: AppState): AppState {
  if (Platform.OS !== 'web' || typeof window === 'undefined') return s;
  if (new URLSearchParams(window.location.search).get('pending') !== '1') return s;
  const base = { note: null, from_bank: null, to_bank: null, transfer_reference: null, recorded_by_name: 'محمد', movement_date: TODAY } as const;
  const id = s.movements.length;
  const saleA = s.sales.length + 1000;
  const saleB = saleA + 1;
  const sales: ApiSale[] = [
    { id: saleA, customer_id: null, customer_name: null, buyer_name: null, sale_type: 'cash', kg_amount: '12.00', price_per_kg: '6.00', total_amount: '72.00', paid_amount: '0.00', sale_date: TODAY, created_at: `${TODAY}T08:31:00`, client_id: 'demo-1' },
    { id: saleB, customer_id: 1, customer_name: 'أبو محمد الشريف', buyer_name: null, sale_type: 'flour', kg_amount: '8.00', price_per_kg: '2.00', total_amount: '16.00', paid_amount: '0.00', sale_date: TODAY, created_at: `${TODAY}T08:12:00`, client_id: 'demo-2' },
  ];
  const movements: ApiMovement[] = [
    { ...base, id: id + 1, number: null, customer_id: null, customer_name: null, ledger: 'money', type: 'sale', amount: '72.00', description: 'خبز 12 كغ × 6.00 شيكل', sale_id: saleA, created_at: `${TODAY}T08:31:00`, client_id: 'demo-1' },
    { ...base, id: id + 2, number: null, customer_id: 1, customer_name: 'أبو محمد الشريف', ledger: 'flour', type: 'flour_withdrawal', amount: '8.00', description: 'خبز مقابل طحين 8 كغ', sale_id: saleB, created_at: `${TODAY}T08:12:00`, client_id: 'demo-2' },
    { ...base, id: id + 3, number: null, customer_id: 1, customer_name: 'أبو محمد الشريف', ledger: 'money', type: 'exchange_fee', amount: '16.00', description: 'رسم تبديل طحين 8 كغ', sale_id: saleB, created_at: `${TODAY}T08:12:00`, client_id: 'demo-2' },
  ];
  const queue: QueuedOperation[] = [
    { client_id: 'demo-1', kind: 'sale', title: 'بيع عادي، 12 كغ', subtitle: 'مشترٍ عابر', created_at: `${TODAY}T08:31:00`, status: 'pending', reason: null },
    { client_id: 'demo-2', kind: 'flour_sale', title: 'خبز مقابل طحين، 8 كغ', subtitle: 'أبو محمد الشريف', created_at: `${TODAY}T08:12:00`, status: 'pending', reason: null },
  ];
  return { ...s, sales: [...sales, ...s.sales], movements: [...s.movements, ...movements], queue: [...queue, ...s.queue] };
}

/** The mock end date when the dev menu (or ?expired=1) ends the subscription. */
const EXPIRED_ON = '2026-11-20';

function initialState(): AppState {
  const url = fromUrl();
  return withPendingDemo({
    userKey: 'owner',
    online: true,
    hasLocalData: true,
    subscriptionExpired: false,
    bakery: url.subscriptionExpired ? { ...BAKERY, subscription_ends_at: EXPIRED_ON } : BAKERY,
    customers: CUSTOMERS,
    movements: MOVEMENTS,
    sales: SALES,
    banks: BANKS,
    staff: STAFF,
    bakeries: OTHER_BAKERIES,
    queue: SEED_QUEUE,
    lastSyncAt: `${TODAY}T08:02:00`,
    ...url,
  });
}

let clientSeq = 0;
const clientId = () => `${Date.now().toString(36)}-${(clientSeq++).toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
const nowIso = () => {
  const d = new Date();
  const hh = String(d.getHours()).padStart(2, '0');
  const mm = String(d.getMinutes()).padStart(2, '0');
  return `${TODAY}T${hh}:${mm}:00`;
};

export interface Balance {
  money: number; // cents, + = عليه
  flour: number; // hundredths of a kg, + = عليه
  pending: boolean;
}

function useAppModel() {
  const [state, setState] = React.useState<AppState>(initialState);
  const user: ApiUser | null = state.userKey ? MOCK_USERS[state.userKey] : null;

  const can = React.useCallback((p: Permission) => !!user?.permissions.includes(p), [user]);

  const balances = React.useMemo(() => {
    const map = new Map<number, Balance>();
    for (const c of state.customers) map.set(c.id, { money: 0, flour: 0, pending: false });
    for (const m of state.movements) {
      if (m.customer_id == null) continue;
      const b = map.get(m.customer_id);
      if (!b) continue;
      if (m.ledger === 'money') b.money += toCents(m.amount);
      else b.flour += toCents(m.amount);
      if (m.number == null) b.pending = true;
    }
    return map;
  }, [state.customers, state.movements]);

  const pendingCount = state.queue.filter((q) => q.status === 'pending').length;

  /** Writes movements locally; numbers them now when online, or queues them for the next sync. */
  const commit = React.useCallback(
    (
      kind: OperationKind,
      title: string,
      subtitle: string,
      build: (cid: string) => { movements?: Omit<ApiMovement, 'id' | 'number' | 'client_id'>[]; sale?: Omit<ApiSale, 'id' | 'client_id'> },
    ) => {
      setState((s) => {
        const cid = clientId();
        const out = build(cid);
        let nextNumber = Math.max(0, ...s.movements.map((m) => m.number ?? 0)) + 1;
        let nextId = s.movements.length + 1;
        const saleId = out.sale ? s.sales.length + 1000 : null;
        const sales = out.sale ? [{ ...out.sale, id: saleId!, client_id: cid }, ...s.sales] : s.sales;
        const added: ApiMovement[] = (out.movements ?? []).map((m) => ({
          ...m,
          sale_id: m.sale_id ?? saleId,
          id: nextId++,
          number: s.online ? nextNumber++ : null,
          client_id: cid,
        }));
        const op: QueuedOperation = {
          client_id: cid,
          kind,
          title,
          subtitle,
          created_at: nowIso(),
          status: s.online ? 'sent' : 'pending',
          reason: null,
        };
        return { ...s, sales, movements: [...s.movements, ...added], queue: [op, ...s.queue] };
      });
    },
    [],
  );

  const blocked = (): string | null => {
    if (state.subscriptionExpired) return 'انتهى الاشتراك. لا يمكن تسجيل عمليات جديدة';
    return null;
  };

  const customerName = (id: number | null) => state.customers.find((c) => c.id === id)?.name ?? null;
  const by = user?.name ?? '';

  return {
    state,
    user,
    can,
    balances,
    pendingCount,

    // ---- session (mock) -------------------------------------------------------------------
    login(email: string, password: string): Result {
      const match = Object.entries(MOCK_USERS).find(([, u]) => u.email === email.trim().toLowerCase());
      if (!match || password !== 'password') return { ok: false, error: 'بيانات الدخول غير متطابقة مع سجلاتنا' };
      setState((s) => ({ ...s, userKey: match[0] as MockUserKey }));
      return { ok: true };
    },
    logout() {
      setState((s) => ({ ...s, userKey: null }));
    },
    switchUser(key: MockUserKey) {
      setState((s) => ({ ...s, userKey: key }));
    },
    setOnline(online: boolean) {
      setState((s) => ({ ...s, online }));
    },
    setExpired(subscriptionExpired: boolean) {
      setState((s) => ({ ...s, subscriptionExpired, bakery: { ...s.bakery, subscription_ends_at: subscriptionExpired ? EXPIRED_ON : BAKERY.subscription_ends_at } }));
    },
    setHasLocalData(hasLocalData: boolean) {
      setState((s) => ({ ...s, hasLocalData }));
    },

    // ---- operations that work offline ------------------------------------------------------
    recordSale(i: { customerId: number | null; buyerName: string; kg: number; paid: boolean }): Result {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!(i.kg > 0)) return { ok: false, error: 'اكتب الكمية' };
      if (i.kg > 100000) return { ok: false, error: 'الكمية أكبر من المسموح' };
      const price = state.bakery.price_per_kg;
      const total = Math.round(toCents(price) * i.kg);
      const name = customerName(i.customerId);
      const created = nowIso();
      commit('sale', `بيع عادي، ${i.kg} كغ`, name ?? (i.buyerName || 'مشترٍ عابر'), () => ({
        sale: {
          customer_id: i.customerId,
          customer_name: name,
          buyer_name: i.buyerName || null,
          sale_type: 'cash',
          kg_amount: i.kg.toFixed(2),
          price_per_kg: price,
          total_amount: fromCents(total),
          paid_amount: i.paid ? fromCents(total) : '0.00',
          sale_date: TODAY,
          created_at: created,
        },
        movements: [
          mv(i.customerId, name, 'money', 'sale', fromCents(total), `خبز ${i.kg} كغ × ${price} شيكل`, by, created),
          ...(i.paid ? [mv(i.customerId, name, 'money', 'payment', fromCents(-total), `دفعة عن بيع ${formatDate(TODAY)}`, by, created)] : []),
        ],
      }));
      return { ok: true };
    },

    recordFlourSale(i: { customerId: number; kg: number; paid: boolean }): Result {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!(i.kg > 0)) return { ok: false, error: 'اكتب الكمية' };
      const fee = Math.round(toCents(state.bakery.flour_exchange_fee_per_kg) * i.kg);
      const name = customerName(i.customerId);
      const created = nowIso();
      commit('flour_sale', `خبز مقابل طحين، ${i.kg} كغ`, name ?? '', () => ({
        sale: {
          customer_id: i.customerId,
          customer_name: name,
          buyer_name: null,
          sale_type: 'flour',
          kg_amount: i.kg.toFixed(2),
          price_per_kg: state.bakery.flour_exchange_fee_per_kg,
          total_amount: fromCents(fee),
          paid_amount: i.paid ? fromCents(fee) : '0.00',
          sale_date: TODAY,
          created_at: created,
        },
        movements: [
          mv(i.customerId, name, 'flour', 'flour_withdrawal', i.kg.toFixed(2), `خبز مقابل طحين ${i.kg} كغ`, by, created),
          ...(fee > 0 ? [mv(i.customerId, name, 'money', 'exchange_fee', fromCents(fee), `رسم تبديل طحين ${i.kg} كغ`, by, created)] : []),
          ...(i.paid && fee > 0 ? [mv(i.customerId, name, 'money', 'payment', fromCents(-fee), `دفعة عن بيع ${formatDate(TODAY)}`, by, created)] : []),
        ],
      }));
      return { ok: true };
    },

    recordDeposit(i: { customerId: number; kg: number; note: string }): Result {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!(i.kg > 0)) return { ok: false, error: 'اكتب الكمية' };
      const name = customerName(i.customerId);
      commit('deposit', `إيداع طحين، ${i.kg} كغ`, name ?? '', () => ({
        movements: [{ ...mv(i.customerId, name, 'flour', 'deposit', (-i.kg).toFixed(2), 'إيداع طحين', by, nowIso()), note: i.note || null }],
      }));
      return { ok: true };
    },

    recordPayment(i: {
      customerId: number;
      amount: number;
      method: 'cash' | 'transfer';
      fromBank: string | null;
      toBank: string | null;
      reference: string;
      saleId: number | null;
    }): Result {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!(i.amount > 0)) return { ok: false, error: 'المبلغ يجب أن يكون أكبر من صفر' };
      if (i.method === 'transfer' && (!i.fromBank || !i.toBank)) return { ok: false, error: 'اختر البنك المرسل والمستقبل' };
      const name = customerName(i.customerId);
      const sale = state.sales.find((x) => x.id === i.saleId);
      commit('payment', `دفعة، ${fromCents(toCents(i.amount))} شيكل`, name ?? '', () => ({
        movements: [
          {
            ...mv(i.customerId, name, 'money', 'payment', fromCents(-toCents(i.amount)), sale ? `دفعة عن بيع ${formatDate(sale.sale_date)}` : 'دفعة', by, nowIso()),
            sale_id: sale?.id ?? null,
            from_bank: i.method === 'transfer' ? i.fromBank : null,
            to_bank: i.method === 'transfer' ? i.toBank : null,
            transfer_reference: i.method === 'transfer' && i.reference ? i.reference : null,
          },
        ],
      }));
      return { ok: true };
    },

    recordAdjustment(i: { customerId: number; ledger: Ledger; direction: 'debit' | 'credit'; amount: number; reason: string; corrects: number | null }): Result {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!(i.amount > 0)) return { ok: false, error: 'اكتب المبلغ أو الكمية' };
      if (!i.reason.trim()) return { ok: false, error: 'اكتب سبب التسوية' };
      const name = customerName(i.customerId);
      const signed = i.direction === 'debit' ? i.amount : -i.amount;
      const desc = `تسوية: ${i.reason.trim()}${i.corrects ? ` (تصحيح الحركة رقم ${i.corrects})` : ''}`;
      commit('adjustment', i.ledger === 'money' ? 'قيد تسوية مالي' : 'قيد تسوية طحين', name ?? '', () => ({
        movements: [mv(i.customerId, name, i.ledger, 'adjustment', signed.toFixed(2), desc, by, nowIso())],
      }));
      return { ok: true };
    },

    addCustomer(i: { name: string; phone: string; notes: string; openingFlour: number }): Result & { id?: number } {
      const err = blocked();
      if (err) return { ok: false, error: err };
      if (!i.name.trim()) return { ok: false, error: 'اكتب اسم الزبون' };
      if (state.customers.some((c) => c.name === i.name.trim())) return { ok: false, error: 'يوجد زبون بهذا الاسم' };
      const id = Math.max(0, ...state.customers.map((c) => c.id)) + 1;
      const customer: ApiCustomer = { id, name: i.name.trim(), phone: i.phone.trim() || null, notes: i.notes.trim() || null };
      setState((s) => ({ ...s, customers: [...s.customers, customer] }));
      commit('customer', 'إضافة زبون', customer.name, () => ({
        movements: i.openingFlour > 0 ? [mv(id, customer.name, 'flour', 'opening_balance', (-i.openingFlour).toFixed(2), 'رصيد افتتاحي', by, nowIso())] : [],
      }));
      return { ok: true, id };
    },

    // ---- online only -----------------------------------------------------------------------
    updateCustomer(id: number, i: { name: string; phone: string; notes: string }): Result {
      if (!i.name.trim()) return { ok: false, error: 'اكتب اسم الزبون' };
      setState((s) => ({
        ...s,
        customers: s.customers.map((c) => (c.id === id ? { ...c, name: i.name.trim(), phone: i.phone.trim() || null, notes: i.notes.trim() || null } : c)),
      }));
      return { ok: true };
    },
    deleteCustomer(id: number) {
      setState((s) => ({
        ...s,
        customers: s.customers.filter((c) => c.id !== id),
        movements: s.movements.filter((m) => m.customer_id !== id),
        sales: s.sales.filter((x) => x.customer_id !== id),
      }));
    },
    markSalePaid(saleId: number): Result {
      const sale = state.sales.find((x) => x.id === saleId);
      if (!sale) return { ok: false, error: 'العملية غير موجودة' };
      const rest = toCents(sale.total_amount) - toCents(sale.paid_amount);
      if (rest <= 0) return { ok: false, error: 'هذه العملية مدفوعة مسبقًا' };
      setState((s) => ({ ...s, sales: s.sales.map((x) => (x.id === saleId ? { ...x, paid_amount: x.total_amount } : x)) }));
      commit('payment', `تأكيد دفع، ${fromCents(rest)} شيكل`, sale.customer_name ?? 'مشترٍ عابر', () => ({
        movements: [{ ...mv(sale.customer_id, sale.customer_name, 'money', 'payment', fromCents(-rest), `دفعة عن بيع ${formatDate(sale.sale_date)}`, by, nowIso()), sale_id: saleId }],
      }));
      return { ok: true };
    },
    saveSettings(price: string, fee: string): Result {
      if (!(Number(price) > 0) || Number(price) > 999.99) return { ok: false, error: 'سعر الكيلو بين 0.01 و 999.99' };
      if (!(Number(fee) >= 0) || Number(fee) > 999.99) return { ok: false, error: 'رسم التبديل بين 0 و 999.99' };
      setState((s) => ({ ...s, bakery: { ...s.bakery, price_per_kg: Number(price).toFixed(2), flour_exchange_fee_per_kg: Number(fee).toFixed(2) } }));
      return { ok: true };
    },
    saveBank(id: number | null, name: string): Result {
      const n = name.trim();
      if (!n) return { ok: false, error: 'اكتب اسم البنك أو المحفظة' };
      if (state.banks.some((b) => b.name === n && b.id !== id)) return { ok: false, error: 'هذا الاسم موجود' };
      setState((s) => ({
        ...s,
        banks: id ? s.banks.map((b) => (b.id === id ? { ...b, name: n } : b)) : [...s.banks, { id: Math.max(0, ...s.banks.map((b) => b.id)) + 1, name: n }],
      }));
      return { ok: true };
    },
    deleteBank(id: number) {
      setState((s) => ({ ...s, banks: s.banks.filter((b) => b.id !== id) }));
    },
    saveStaff(id: number | null, i: { name: string; email: string; password: string; permissions: StaffPermission[] }): Result {
      if (!i.name.trim()) return { ok: false, error: 'اكتب الاسم' };
      if (!/^\S+@\S+\.\S+$/.test(i.email.trim())) return { ok: false, error: 'اكتب بريدًا إلكترونيًا صحيحًا' };
      if (!id && i.password.length < 8) return { ok: false, error: 'كلمة المرور 8 أحرف على الأقل' };
      const email = i.email.trim().toLowerCase();
      setState((s) => ({
        ...s,
        staff: id
          ? s.staff.map((x) => (x.id === id ? { ...x, name: i.name.trim(), email, permissions: i.permissions } : x))
          : [...s.staff, { id: Math.max(10, ...s.staff.map((x) => x.id)) + 1, name: i.name.trim(), email, is_active: true, permissions: i.permissions }],
      }));
      return { ok: true };
    },
    setStaffActive(id: number, active: boolean) {
      setState((s) => ({ ...s, staff: s.staff.map((x) => (x.id === id ? { ...x, is_active: active } : x)) }));
    },
    renewBakery(id: number, months: number) {
      setState((s) => ({
        ...s,
        bakeries: s.bakeries.map((b) => {
          if (b.id !== id) return b;
          const from = b.subscription_ends_at > TODAY ? b.subscription_ends_at : TODAY;
          return { ...b, is_active: true, subscription_ends_at: addMonths(from, months) };
        }),
      }));
    },
    setBakeryActive(id: number, active: boolean) {
      setState((s) => ({ ...s, bakeries: s.bakeries.map((b) => (b.id === id ? { ...b, is_active: active } : b)) }));
    },
    addBakery(i: { name: string; address: string; ownerName: string; ownerEmail: string; password: string; months: number }): Result {
      if (!i.name.trim()) return { ok: false, error: 'اكتب اسم المخبز' };
      if (!i.ownerName.trim()) return { ok: false, error: 'اكتب اسم صاحب المخبز' };
      if (!/^\S+@\S+\.\S+$/.test(i.ownerEmail.trim())) return { ok: false, error: 'اكتب بريدًا إلكترونيًا صحيحًا' };
      if (i.password.length < 8) return { ok: false, error: 'كلمة المرور 8 أحرف على الأقل' };
      setState((s) => ({
        ...s,
        bakeries: [
          {
            id: Math.max(0, ...s.bakeries.map((b) => b.id)) + 1,
            name: i.name.trim(),
            address: i.address.trim() || null,
            price_per_kg: '6.00',
            flour_exchange_fee_per_kg: '2.00',
            subscription_ends_at: addMonths(TODAY, i.months),
            is_active: true,
            owner_name: i.ownerName.trim(),
            owner_email: i.ownerEmail.trim().toLowerCase(),
            staff_count: 0,
          },
          ...s.bakeries,
        ],
      }));
      return { ok: true };
    },

    /** Mock server: numbers every waiting movement in order and marks the queue sent. */
    syncNow(): Result & { count?: number } {
      if (!state.online) return { ok: false, error: 'لا يوجد اتصال بالإنترنت' };
      const count = state.queue.filter((q) => q.status === 'pending').length;
      setState((s) => {
        let next = Math.max(0, ...s.movements.map((m) => m.number ?? 0)) + 1;
        return {
          ...s,
          hasLocalData: true,
          movements: s.movements.map((m) => (m.number == null ? { ...m, number: next++ } : m)),
          queue: s.queue.map((q) => (q.status === 'pending' ? { ...q, status: 'sent' } : q)),
          lastSyncAt: nowIso(),
        };
      });
      return { ok: true, count };
    },
    dismissRejected(clientIdToDrop: string) {
      setState((s) => ({ ...s, queue: s.queue.filter((q) => q.client_id !== clientIdToDrop) }));
    },
  };
}

function mv(
  customerId: number | null,
  name: string | null,
  ledger: Ledger,
  type: ApiMovement['type'],
  amount: string,
  description: string,
  by: string,
  created: string,
): Omit<ApiMovement, 'id' | 'number' | 'client_id'> {
  return {
    customer_id: customerId,
    customer_name: name,
    ledger,
    type,
    amount,
    description,
    note: null,
    sale_id: null,
    from_bank: null,
    to_bank: null,
    transfer_reference: null,
    recorded_by_name: by,
    movement_date: TODAY,
    created_at: created,
  };
}

function addMonths(date: string, months: number): string {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCMonth(d.getUTCMonth() + months);
  return d.toISOString().slice(0, 10);
}

export { addDays, TODAY };

type AppModel = ReturnType<typeof useAppModel>;
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
