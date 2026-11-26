import { formatFlourBalance, formatKg } from '@/lib/format';
import { cn } from '@/lib/utils';

export function KgText({ kg, className }: { kg: number | string | null; className?: string }) {
    return <span className={cn('tabular-nums', className)}>{formatKg(kg)}</span>;
}

/** Flour balance in words, never a sign: "له 20 كغ" (green), "عليه 20 كغ" (red), "0 كغ". */
export function FlourBalance({ kg, className, plain = false }: { kg: number | string | null; className?: string; plain?: boolean }) {
    const n = Number(kg) || 0;
    const tone = plain ? '' : n < 0 ? 'text-success' : n > 0 ? 'text-destructive' : 'text-muted-foreground';
    return <span className={cn('tabular-nums', tone, className)}>{formatFlourBalance(kg)}</span>;
}
