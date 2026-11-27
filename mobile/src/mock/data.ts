import { fromCents, toCents } from '@/lib/format';

import {
  PERMISSION_GROUPS,
  type ApiBakery,
  type ApiBank,
  type ApiCustomer,
  type ApiMovement,
  type ApiSale,
  type ApiStaff,
  type ApiUser,
  type Permission,
  type QueuedOperation,
  type StaffPermission,
} from './types';

/** The mock "today": the app reads dates from here so screenshots stay stable. */
export const TODAY = '2026-11-27';

export const ALL_STAFF_PERMISSIONS: StaffPermission[] = PERMISSION_GROUPS.flatMap((g) =>
  g.items.map(([key]) => key),
);
const OWNER_PERMISSIONS: Permission[] = [
  ...ALL_STAFF_PERMISSIONS,
  'delete-customers',
  'delete-banks',
  'manage-users',
  'manage-settings',
];

export const MOCK_USERS = {
  owner: {
    id: 2,
    name: 'محمد',
    email: 'owner@bakery.test',
    role: 'bakery_owner',
    bakery_id: 1,
    is_active: true,
    permissions: OWNER_PERMISSIONS,
  },
  staff: {
    id: 3,
    name: 'سامر',
    email: 'samer@alnoor.ps',
    role: 'bakery_staff',
    bakery_id: 1,
    is_active: true,
    permissions: ['view-customers', 'create-customers', 'record-sale', 'record-payment', 'record-deposit'],
  },
  noPermissions: {
    id: 5,
    name: 'خالد',
    email: 'khaled@alnoor.ps',
    role: 'bakery_staff',
    bakery_id: 1,
    is_active: true,
    permissions: [],
  },
  admin: {
    id: 1,
    name: 'مالك المنصة',
    email: 'admin@bakery.test',
    role: 'super_admin',
    bakery_id: null,
    is_active: true,
    permissions: [],
  },
} satisfies Record<string, ApiUser>;

export type MockUserKey = keyof typeof MOCK_USERS;

export const BAKERY: ApiBakery = {
  id: 1,
  name: 'مخبز النور',
  address: 'نابلس، شارع فيصل',
  price_per_kg: '6.00',
  flour_exchange_fee_per_kg: '2.00',
  subscription_ends_at: '2026-12-26',
  is_active: true,
  owner_name: 'محمد',
  owner_email: 'owner@bakery.test',
  staff_count: 3,
};

export const BANKS: ApiBank[] = [
  { id: 1, name: 'جوال باي' },
  { id: 2, name: 'بنك فلسطين' },
  { id: 3, name: 'بال باي' },
];

export const STAFF: ApiStaff[] = [
  {
    id: 3,
    name: 'سامر',
    email: 'samer@alnoor.ps',
    is_active: true,
    permissions: ['view-customers', 'create-customers', 'record-sale', 'record-payment', 'record-deposit'],
  },
  {
    id: 4,
    name: 'ريم',
    email: 'reem@alnoor.ps',
    is_active: true,
    permissions: [
      'view-dashboard',
      'view-customers',
      'view-money-statement',
      'view-flour-statement',
      'export-statements',
      'view-sales-log',
      'record-adjustment',
      'view-banks',
      'view-sales',
    ],
  },
  { id: 5, name: 'خالد', email: 'khaled@alnoor.ps', is_active: false, permissions: ['record-sale', 'view-customers'] },
];

export const CUSTOMERS: ApiCustomer[] = [
  { id: 1, name: 'أبو محمد الشريف', phone: '0599123456', notes: 'يأخذ الخبز صباحًا' },
  { id: 2, name: 'مطعم الأصدقاء', phone: '0598765432', notes: null },
  { id: 3, name: 'كافتيريا الجامعة', phone: '0594555666', notes: 'الدفع آخر الشهر' },
  { id: 4, name: 'أم خالد', phone: '0592333444', notes: null },
  { id: 5, name: 'سوبرماركت النور', phone: '0597111222', notes: null },
  { id: 6, name: 'مدرسة النجاح', phone: '0595888777', notes: null },
];

/** Target balances (money in shekels, flour in kg; positive = عليه) the generator ends on. */
const TARGETS: Record<number, [string, string]> = {
  1: ['16.00', '-12'],
  2: ['-30.00', '8'],
  3: ['0.00', '-142'],
  4: ['42.50', '0'],
  5: ['120.00', '-60'],
  6: ['0.00', '0'],
};

export const OTHER_BAKERIES: ApiBakery[] = [
  BAKERY,
  {
    id: 2,
    name: 'مخبز الأمل',
    address: 'رام الله',
    price_per_kg: '6.50',
    flour_exchange_fee_per_kg: '2.00',
    subscription_ends_at: '2026-12-01',
    is_active: true,
    owner_name: 'أحمد',
    owner_email: 'amal@bakery.ps',
    staff_count: 1,
  },
  {
    id: 3,
    name: 'أفران الشام',
    address: 'الخليل',
    price_per_kg: '5.50',
    flour_exchange_fee_per_kg: '1.50',
    subscription_ends_at: '2026-11-20',
    is_active: true,
    owner_name: 'سليم',
    owner_email: 'sham@bakery.ps',
    staff_count: 4,
  },
  {
    id: 4,
    name: 'مخبز القدس',
    address: 'جنين',
    price_per_kg: '6.00',
    flour_exchange_fee_per_kg: '2.00',
    subscription_ends_at: '2027-02-14',
    is_active: false,
    owner_name: 'يوسف',
    owner_email: 'quds@bakery.ps',
    staff_count: 0,
  },
];

// ---------------------------------------------------------------------------------------------
// A deterministic history: ~60 days of sales, payments and deposits, shaped like the server's.

function addDays(date: string, days: number): string {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}
export { addDays };

let seed = 7;
const rand = () => {
  seed = (seed * 16807) % 2147483647;
  return (seed - 1) / 2147483646;
};
const pick = <T,>(xs: readonly T[]): T => xs[Math.floor(rand() * xs.length)];

function build() {
  const movements: ApiMovement[] = [];
  const sales: ApiSale[] = [];
  let id = 1;
  let number = 1;
  let saleId = 1;
  const nameOf = (cid: number | null) => CUSTOMERS.find((c) => c.id === cid)?.name ?? null;
  const by = () => pick(['محمد', 'محمد', 'سامر', 'ريم']);

  const move = (m: Omit<ApiMovement, 'id' | 'number' | 'customer_name' | 'note' | 'sale_id' | 'from_bank' | 'to_bank' | 'transfer_reference' | 'recorded_by_name'> & Partial<ApiMovement>) => {
    movements.push({
      id: id++,
      number: number++,
      customer_name: nameOf(m.customer_id),
      note: null,
      sale_id: null,
      from_bank: null,
      to_bank: null,
      transfer_reference: null,
      recorded_by_name: by(),
      ...m,
    });
  };

  const stamp = (date: string, minutes: number) =>
    `${date}T${String(6 + Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}:00`;

  const cashSale = (date: string, t: number, cid: number | null, kg: number, paid: boolean, buyer: string | null = null) => {
    const total = toCents(BAKERY.price_per_kg) * kg;
    const sid = saleId++;
    const created = stamp(date, t);
    sales.push({
      id: sid,
      customer_id: cid,
      customer_name: nameOf(cid),
      buyer_name: buyer,
      sale_type: 'cash',
      kg_amount: kg.toFixed(2),
      price_per_kg: BAKERY.price_per_kg,
      total_amount: fromCents(total),
      paid_amount: paid ? fromCents(total) : '0.00',
      sale_date: date,
      created_at: created,
    });
    move({ customer_id: cid, ledger: 'money', type: 'sale', amount: fromCents(total), description: `خبز ${kg} كغ × ${BAKERY.price_per_kg} شيكل`, sale_id: sid, movement_date: date, created_at: created });
    if (paid) move({ customer_id: cid, ledger: 'money', type: 'payment', amount: fromCents(-total), description: `دفعة عن بيع ${date.slice(8, 10)}/${date.slice(5, 7)}/${date.slice(0, 4)}`, sale_id: sid, movement_date: date, created_at: created });
  };

  const flourSale = (date: string, t: number, cid: number, kg: number, paid: boolean) => {
    const fee = toCents(BAKERY.flour_exchange_fee_per_kg) * kg;
    const sid = saleId++;
    const created = stamp(date, t);
    sales.push({
      id: sid,
      customer_id: cid,
      customer_name: nameOf(cid),
      buyer_name: null,
      sale_type: 'flour',
      kg_amount: kg.toFixed(2),
      price_per_kg: BAKERY.flour_exchange_fee_per_kg,
      total_amount: fromCents(fee),
      paid_amount: paid ? fromCents(fee) : '0.00',
      sale_date: date,
      created_at: created,
    });
    move({ customer_id: cid, ledger: 'flour', type: 'flour_withdrawal', amount: kg.toFixed(2), description: `خبز مقابل طحين ${kg} كغ`, sale_id: sid, movement_date: date, created_at: created });
    move({ customer_id: cid, ledger: 'money', type: 'exchange_fee', amount: fromCents(fee), description: `رسم تبديل طحين ${kg} كغ`, sale_id: sid, movement_date: date, created_at: created });
    if (paid) move({ customer_id: cid, ledger: 'money', type: 'payment', amount: fromCents(-fee), description: `دفعة عن بيع ${date.slice(8, 10)}/${date.slice(5, 7)}/${date.slice(0, 4)}`, sale_id: sid, movement_date: date, created_at: created });
  };

  const start = addDays(TODAY, -400);
  // Opening balances first, so the generated history ends on TARGETS.
  const openingAt = movements.length;

  for (let day = -400; day < 0; day++) {
    const date = addDays(TODAY, day);
    const weekend = new Date(`${date}T12:00:00Z`).getUTCDay() === 5;
    const n = weekend ? 3 : 4 + Math.floor(rand() * 5);
    for (let i = 0; i < n; i++) {
      const t = 30 + i * 70 + Math.floor(rand() * 40);
      const r = rand();
      if (r < 0.45) cashSale(date, t, null, 3 + Math.floor(rand() * 14), true);
      else if (r < 0.7) cashSale(date, t, pick([1, 2, 4, 5]), 5 + Math.floor(rand() * 20), rand() < 0.7);
      else if (r < 0.85) flourSale(date, t, pick([1, 3, 5]), 5 + Math.floor(rand() * 15), rand() < 0.6);
      else if (r < 0.93) move({ customer_id: pick([1, 3, 5]), ledger: 'flour', type: 'deposit', amount: (-(20 + Math.floor(rand() * 40))).toFixed(2), description: 'إيداع طحين', movement_date: date, created_at: stamp(date, t) });
      else {
        const transfer = rand() < 0.5;
        move({
          customer_id: pick([1, 2, 4, 5]),
          ledger: 'money',
          type: 'payment',
          amount: fromCents(-toCents(20 + Math.floor(rand() * 80))),
          description: 'دفعة',
          from_bank: transfer ? 'جوال باي' : null,
          to_bank: transfer ? 'بنك فلسطين' : null,
          transfer_reference: transfer && rand() < 0.5 ? `TR-${90000 + Math.floor(rand() * 999)}` : null,
          movement_date: date,
          created_at: stamp(date, t),
        });
      }
    }
  }

  // Today, as in the prototype.
  cashSale(TODAY, 151, null, 12, true);
  move({ customer_id: 2, ledger: 'money', type: 'payment', amount: '-90.00', description: 'دفعة', from_bank: 'جوال باي', to_bank: 'بنك فلسطين', transfer_reference: 'TR-90210', movement_date: TODAY, created_at: `${TODAY}T08:20:00` });
  move({ customer_id: 3, ledger: 'flour', type: 'deposit', amount: '-40.00', description: 'إيداع طحين', movement_date: TODAY, created_at: `${TODAY}T07:50:00` });
  flourSale(TODAY, 112, 1, 17, false);
  cashSale(TODAY, 125, null, 5, true);
  cashSale(TODAY, 105, 2, 22, false);
  cashSale(TODAY, 80, 4, 7, true);
  cashSale(TODAY, 60, 5, 26, true);

  // Opening balances that land each customer on its target.
  const openings: ApiMovement[] = [];
  for (const c of CUSTOMERS) {
    for (const [ledger, target] of [['money', TARGETS[c.id][0]], ['flour', TARGETS[c.id][1]]] as const) {
      const sum = movements.filter((m) => m.customer_id === c.id && m.ledger === ledger).reduce((s, m) => s + toCents(m.amount), 0);
      const diff = toCents(target) - sum;
      if (diff !== 0)
        openings.push({
          id: 0,
          number: 0,
          customer_id: c.id,
          customer_name: c.name,
          ledger,
          type: 'opening_balance',
          amount: fromCents(diff),
          description: 'رصيد افتتاحي',
          note: null,
          sale_id: null,
          from_bank: null,
          to_bank: null,
          transfer_reference: null,
          recorded_by_name: 'ترحيل البيانات',
          movement_date: addDays(start, -1),
          created_at: `${addDays(start, -1)}T06:00:00`,
        });
    }
  }
  movements.splice(openingAt, 0, ...openings);
  // Renumber in insertion order so numbers stay unique and increasing.
  movements.forEach((m, i) => {
    m.id = i + 1;
    m.number = i + 1;
  });
  // A few unpaid sales are settled by later payments on the server; the mock keeps paid flags as generated.
  return { movements, sales };
}

const built = build();
export const MOVEMENTS: ApiMovement[] = built.movements;
export const SALES: ApiSale[] = built.sales;

/** One rejected operation left in the queue, to show the sync screen's "rejected" state. */
export const SEED_QUEUE: QueuedOperation[] = [
  {
    client_id: 'seed-rejected-1',
    kind: 'flour_sale',
    title: 'بيع مقابل طحين، 5 كغ',
    subtitle: 'مدرسة النجاح',
    created_at: `${TODAY}T07:12:00`,
    status: 'rejected',
    reason: 'الزبون حُذف من جهاز آخر',
  },
];
