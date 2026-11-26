import type { route as routeFn } from 'ziggy-js';

export type Role = 'super_admin' | 'bakery_owner' | 'bakery_staff';

/** A permission key from User::PERMISSIONS or User::OWNER_ONLY in PHP. */
export type Permission =
    | 'view-dashboard'
    | 'view-customers'
    | 'create-customers'
    | 'edit-customers'
    | 'view-sales'
    | 'record-sale'
    | 'record-flour-sale'
    | 'record-payment'
    | 'record-deposit'
    | 'view-money-statement'
    | 'view-flour-statement'
    | 'export-statements'
    | 'view-sales-log'
    | 'record-adjustment'
    | 'view-banks'
    | 'manage-banks'
    | 'delete-customers'
    | 'delete-banks'
    | 'manage-users'
    | 'manage-settings';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: Role;
}

export interface Flash {
    success: string | null;
    error: string | null;
    status: string | null;
}

export interface SharedProps {
    appName: string;
    /** The server's date (YYYY-MM-DD): default for date fields. */
    currentDate: string;
    auth: {
        user: AuthUser | null;
        bakery: { name: string } | null;
        /** What the signed-in user may do. Only hides links and buttons: the server checks again. */
        permissions: Permission[];
    };
    flash: Flash;
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps;
    }
}

declare global {
    const route: typeof routeFn;
}

/** Laravel's LengthAwarePaginator as JSON (the fields we read). */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

/** A sale as the server's Sale::toRow() sends it. Amounts are decimal strings. */
export interface SaleRow {
    id: number;
    sale_date: string;
    sale_type: 'cash' | 'flour_exchange';
    customer_id: number | null;
    buyer: string;
    buyer_mobile: string | null;
    kg_amount: string;
    total_amount: string;
    remaining_amount: string;
    payment_status: 'paid' | 'unpaid';
    is_partially_paid: boolean;
}

/** One movement in a customer statement (Customer::statement()). Amounts are positive decimal strings; balance is signed. */
export interface StatementRow {
    number: number;
    date: string;
    description: string;
    note: string | null;
    transfer: string | null;
    transfer_reference: string | null;
    debit: string | null;
    credit: string | null;
    balance: string;
    recorded_by: string;
}

export interface Statement {
    opening: string;
    closing: string;
    rows: StatementRow[];
}
