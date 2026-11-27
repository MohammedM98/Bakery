import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<typeof Input>, 'type'>;

function UnitInput({ unit, className, ...props }: Props & { unit: string }) {
    return (
        // The input is LTR (digits), so its ps-* is the left side; the unit sits there too (end-0 in the RTL page).
        <div className="relative">
            <Input
                type="number"
                inputMode="decimal"
                min="0"
                step="0.01"
                dir="ltr"
                className={cn('h-11 pe-3 ps-16 text-end tabular-nums', className)}
                {...props}
            />
            <span className="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-4 text-sm text-muted-foreground" aria-hidden>
                {unit}
            </span>
        </div>
    );
}

/** Amount in shekels. */
export function MoneyInput(props: Props) {
    return <UnitInput unit="شيكل" {...props} />;
}

/** Quantity in kilograms. */
export function KgInput(props: Props) {
    return <UnitInput unit="كغ" {...props} />;
}
