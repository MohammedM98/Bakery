import type { ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

type Props = Omit<ComponentProps<typeof Input>, 'type'>;

function UnitInput({ unit, className, ...props }: Props & { unit: string }) {
    return (
        <div className="relative">
            <Input
                type="number"
                inputMode="decimal"
                min="0"
                step="0.01"
                dir="ltr"
                className={cn('h-11 pe-3 ps-12 text-end tabular-nums', className)}
                {...props}
            />
            <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-4 text-sm text-muted-foreground" aria-hidden>
                {unit}
            </span>
        </div>
    );
}

/** Amount in shekels. */
export function MoneyInput(props: Props) {
    return <UnitInput unit="₪" {...props} />;
}

/** Quantity in kilograms. */
export function KgInput(props: Props) {
    return <UnitInput unit="كغ" {...props} />;
}
