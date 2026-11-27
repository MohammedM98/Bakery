/**
 * Data shapes the screens read. They are a guess at what /api/v1 will return (session 9 builds it,
 * session 11 wires it): snake_case keys, decimals as strings (Laravel `decimal` casts), dates as
 * "Y-m-d", timestamps as ISO strings. Keep screens on these types so swapping the mock for the API
 * only touches src/mock and src/state.
 */

export type Role = 'super_admin' | 'bakery_owner' | 'bakery_staff';

/** Same keys and order as User::PERMISSIONS / User::OWNER_ONLY on the server. */
export const PERMISSION_GROUPS = [
  { label: 'اللوحة', items: [['view-dashboard', 'عرض اللوحة الرئيسية']] },
  {
    label: 'الزبائن',
    items: [
      ['view-customers', 'عرض الزبائن'],
      ['create-customers', 'إضافة زبون'],
      ['edit-customers', 'تعديل زبون'],
    ],
  },
  {
    label: 'المبيعات',
    items: [
      ['view-sales', 'عرض قائمة المبيعات'],
      ['record-sale', 'تسجيل بيع عادي'],
      ['record-flour-sale', 'تسجيل بيع مقابل طحين'],
    ],
  },
  { label: 'المال', items: [['record-payment', 'تسجيل دفعة']] },
  { label: 'الطحين', items: [['record-deposit', 'تسجيل إيداع طحين']] },
  {
    label: 'الكشوف',
    items: [
      ['view-money-statement', 'عرض كشف الحساب المالي'],
      ['view-flour-statement', 'عرض كشف الطحين'],
      ['export-statements', 'تحميل الكشوف (PDF / Excel)'],
    ],
  },
  { label: 'السجل', items: [['view-sales-log', 'عرض سجل مبيعات المخبز']] },
  { label: 'التسوية', items: [['record-adjustment', 'تسجيل قيد تسوية']] },
  {
    label: 'البنوك',
    items: [
      ['view-banks', 'عرض البنوك'],
      ['manage-banks', 'إضافة وتعديل بنك'],
    ],
  },
] as const;

export const OWNER_ONLY = ['delete-customers', 'delete-banks', 'manage-users', 'manage-settings'] as const;

export type StaffPermission = (typeof PERMISSION_GROUPS)[number]['items'][number][0];
export type Permission = StaffPermission | (typeof OWNER_ONLY)[number];

export interface ApiUser {
  id: number;
  name: string;
  email: string;
  role: Role;
  bakery_id: number | null;
  is_active: boolean;
  /** What this user may do (the server's `auth.permissions`): all keys for the owner. */
  permissions: Permission[];
}

export interface ApiBakery {
  id: number;
  name: string;
  address: string | null;
  price_per_kg: string;
  flour_exchange_fee_per_kg: string;
  subscription_ends_at: string;
  is_active: boolean;
  owner_name: string;
  owner_email: string;
  staff_count: number;
}

export interface ApiCustomer {
  id: number;
  name: string;
  phone: string | null;
  notes: string | null;
}

export type Ledger = 'money' | 'flour';
export type MovementType =
  | 'sale'
  | 'exchange_fee'
  | 'flour_withdrawal'
  | 'deposit'
  | 'payment'
  | 'adjustment'
  | 'opening_balance';

export interface ApiMovement {
  id: number;
  /** The server's per-bakery serial. null until the server has accepted the operation. */
  number: number | null;
  customer_id: number | null;
  customer_name: string | null;
  ledger: Ledger;
  type: MovementType;
  /** Signed: positive = عليه (owed by the customer), negative = له. Shekels or kilograms. */
  amount: string;
  description: string;
  note: string | null;
  sale_id: number | null;
  from_bank: string | null;
  to_bank: string | null;
  transfer_reference: string | null;
  recorded_by_name: string;
  movement_date: string;
  created_at: string;
  /** Client only: the queued operation that produced it, while it waits for the server. */
  client_id?: string;
}

export type SaleType = 'cash' | 'flour';

export interface ApiSale {
  id: number;
  customer_id: number | null;
  customer_name: string | null;
  buyer_name: string | null;
  sale_type: SaleType;
  kg_amount: string;
  price_per_kg: string;
  total_amount: string;
  paid_amount: string;
  sale_date: string;
  created_at: string;
  client_id?: string;
}

export interface ApiBank {
  id: number;
  name: string;
}

export interface ApiStaff {
  id: number;
  name: string;
  email: string;
  is_active: boolean;
  permissions: StaffPermission[];
}

export type OperationKind = 'sale' | 'flour_sale' | 'deposit' | 'payment' | 'adjustment' | 'customer';

/**
 * One offline operation in the phone's queue. `client_id` is the idempotency key the server uses to
 * save it once (the website's `submission_id`). The server alone gives the movement number.
 */
export interface QueuedOperation {
  client_id: string;
  kind: OperationKind;
  title: string;
  subtitle: string;
  created_at: string;
  status: 'pending' | 'sent' | 'rejected';
  reason: string | null;
}
