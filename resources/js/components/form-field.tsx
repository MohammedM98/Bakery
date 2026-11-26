import type { ReactNode } from 'react';
import { InputError } from '@/components/input-error';
import { Label } from '@/components/ui/label';

/** Label, control, optional hint and the server's validation error. Give the control `aria-describedby={`${id}-error`}`. */
export function Field({
    id,
    label,
    error,
    hint,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && !error && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
