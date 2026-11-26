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
