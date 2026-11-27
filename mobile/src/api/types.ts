/**
 * The shapes /api/v1 returns (docs/api.md): snake_case keys, amounts as decimal strings with a ready
 * `_text` made by the website's own money() / kg() / flour_balance(), dates "Y-m-d", times ISO 8601.
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

export interface Subscription {
  status: 'active' | 'inactive' | string;
  active: boolean;
  expires_at: string | null;
  days_left: number | null;
}

export interface Prices {
  regular_price_per_kg: string;
  regular_price_text: string;
  flour_exchange_fee_per_kg: string;
  flour_exchange_fee_text: string;
}

/** GET /me (and the head of every /sync/pull). */
export interface Me {
  user: { id: number; name: string; email: string; role: Role };
  bakery: { id: number; name: string; phone: string | null; address: string | null } | null;
  subscription: Subscription | null;
  permissions: Permission[];
  prices: Prices | null;
  server_time: string;
  timezone: string;
  local_timezone: string;
}

export interface ApiCustomer {
  /** The server id; negative for a customer added offline that the server has not accepted yet. */
  id: number;
  name: string;
  mobile_number: string | null;
  notes: string | null;
  money_balance: string;
  money_balance_text: string;
  flour_balance: string;
  flour_balance_text: string;
  /** Client only: the customer.create operation of a customer added offline. */
  client_uuid?: string;
}

export type Ledger = 'money' | 'flour';
export type MovementType = 'sale' | 'exchange_fee' | 'flour_withdrawal' | 'deposit' | 'payment' | 'adjustment' | 'opening_balance';

/** MovementResource. */
export interface ApiMovement {
  /** The server's per-bakery serial; null while the operation waits for the server. */
  number: number | null;
  customer_id: number | null;
  sale_id: number | null;
  ledger: Ledger;
  type: MovementType;
  type_label: string;
  date: string;
  /** Signed: positive = عليه, negative = له. Shekels or kilograms. */
  amount: string;
  direction: 'debit' | 'credit';
  direction_label: 'عليه' | 'له';
  /** The size without a sign: "30.00 شيكل" or "30 كغ". */
  amount_text: string;
  description: string;
  note: string | null;
  /** Payments only: "نقدًا" or "جوال باي ← بنك فلسطين". */
  transfer: string | null;
  transfer_reference: string | null;
  recorded_by: string;
  created_at: string;
  /** Client only: the queued operation that produced a waiting movement. */
  client_uuid?: string;
  client_seq?: number;
}

/** SaleResource. */
export interface ApiSale {
  id: number;
  sale_date: string;
  sale_type: 'cash' | 'flour_exchange';
  customer_id: number | null;
  buyer: string;
  buyer_mobile: string | null;
  kg_amount: string;
  price_per_kg: string;
  total_amount: string;
  remaining_amount: string;
  payment_status: 'paid' | 'unpaid';
  is_partially_paid: boolean;
  kg_text: string;
  total_text: string;
  remaining_text: string;
  payment_status_label: string;
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

/** BakeryResource (platform owner). */
export interface ApiBakery {
  id: number;
  name: string;
  phone: string | null;
  address: string | null;
  subscription: Subscription;
  owner?: { name: string; email: string } | null;
}

export interface Paged<T> {
  data: T[];
  meta: { current_page: number; last_page: number; total: number };
}

/** A write's answer ("رد حركة"): the server's movement numbers and the balances after them. */
export interface OperationResult {
  movements?: ApiMovement[];
  customer?: ApiCustomer | null;
  sale?: ApiSale | null;
}

/** What the phone may queue offline (Operations::OFFLINE). */
export type OfflineType = 'sale' | 'flour_sale' | 'deposit' | 'payment' | 'adjustment' | 'customer.create';

/**
 * One operation in the phone's queue. `client_uuid` is the idempotency key: the server saves it once and the
 * server alone gives the movement numbers. A refused one stays (status rejected) until the user dismisses it.
 */
export interface QueuedOperation {
  client_uuid: string;
  type: OfflineType;
  client_created_at: string;
  payload: Record<string, unknown>;
  title: string;
  subtitle: string;
  status: 'pending' | 'rejected' | 'sent';
  code?: string;
  reason?: string;
  /** Set when the server accepted it: what to print. */
  result?: OperationResult;
  sent_at?: string;
  /** customer.create: the customer's local (negative) id, so an open page can follow it to its server id. */
  local_id?: number;
}

export interface PushResult {
  client_uuid: string | null;
  status: 'accepted' | 'duplicate' | 'rejected';
  data?: OperationResult;
  code?: string;
  message?: string;
  errors?: Record<string, string[]>;
  retryable?: boolean;
}

export interface PullResponse extends Me {
  cursor: string;
  has_more: boolean;
  full: boolean;
  customers: ApiCustomer[];
  movements: ApiMovement[];
  banks: ApiBank[];
  deleted: { customers: number[]; banks: number[] };
}

/** The signed-in user with what they may do. */
export type SessionUser = Me['user'] & { permissions: Permission[] };

/** One row of Customer::statement() with texts (GET /customers/{id}). */
export interface ApiStatementRow {
  number: number;
  date: string;
  description: string;
  note: string | null;
  transfer: string | null;
  transfer_reference: string | null;
  debit: string | null;
  credit: string | null;
  balance: string;
  recorded_by: string | null;
  debit_text: string | null;
  credit_text: string | null;
  balance_text: string;
}

export interface ApiStatement {
  opening: string;
  opening_text: string;
  closing: string;
  closing_text: string;
  rows: ApiStatementRow[];
}

/** GET /customers/{id}: the website's customer page. */
export interface CustomerDetail {
  customer: ApiCustomer;
  range: { from: string | null; to: string | null };
  money_statement: ApiStatement | null;
  flour_statement: ApiStatement | null;
  sales: ApiSale[] | null;
  unpaid_sales: ApiSale[];
  banks: ApiBank[];
  can_adjust: boolean;
  flour_exchange_fee: string;
}
