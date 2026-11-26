import { usePage } from '@inertiajs/react';
import type { Permission } from '@/types';

/** `can('record-sale')`: whether to show a link or button. The server enforces every permission on its own. */
export function useCan() {
    const { permissions } = usePage().props.auth;
    return (permission: Permission) => permissions.includes(permission);
}
