import type { route as routeFn } from 'ziggy-js';

export type Role = 'super_admin' | 'bakery_owner';

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
