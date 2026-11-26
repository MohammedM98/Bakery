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
