import { formatKg } from '@/lib/format';
import { cn } from '@/lib/utils';

export function KgText({ kg, className }: { kg: number | string | null; className?: string }) {
    return <span className={cn('tabular-nums', className)}>{formatKg(kg)}</span>;
}
