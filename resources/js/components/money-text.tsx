import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Amount in shekels. Money balances keep their sign: negative = credit to the customer. */
export function MoneyText({ amount, className }: { amount: number | string | null; className?: string }) {
    return (
        <span dir="ltr" className={cn('tabular-nums', className)}>
            {formatMoney(amount)}
        </span>
    );
}

/** A money balance with its sign: positive = عليه (red), negative = له (green). */
export function MoneyBalance({ amount, className, plain = false }: { amount: number | string | null; className?: string; plain?: boolean }) {
    const n = Number(amount) || 0;
    const tone = plain ? '' : n < 0 ? 'text-success' : n > 0 ? 'text-destructive' : 'text-muted-foreground';
    return <MoneyText amount={amount} className={cn(tone, className)} />;
}
