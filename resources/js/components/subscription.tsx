import { Badge } from '@/components/ui/badge';
import { formatDate, formatDays } from '@/lib/format';
import { cn } from '@/lib/utils';

export interface Subscription {
    subscription_status: 'active' | 'inactive';
    is_active: boolean;
    expires_at: string | null;
    days_left: number | null;
}

/** Stopped by the admin, expired by date, ending within a week, or active. */
export function SubscriptionBadge({ subscription: s }: { subscription: Subscription }) {
    if (s.subscription_status !== 'active') return <Badge variant="secondary">موقوف</Badge>;
    if (!s.is_active) return <Badge variant="destructive">منتهٍ</Badge>;
    const soon = s.days_left !== null && s.days_left <= 7;
    return (
        <Badge className={cn(soon ? 'bg-warning/15 text-warning' : 'bg-success/15 text-success')}>
            {soon ? 'ينتهي قريبًا' : 'فعّال'}
        </Badge>
    );
}

export function daysLeftText({ days_left, expires_at }: Subscription): string {
    if (days_left === null || !expires_at) return 'بلا تاريخ انتهاء';
    if (days_left > 0) return `متبقٍ ${formatDays(days_left)}`;
    if (days_left === 0) return 'ينتهي اليوم';
    return `انتهى منذ ${formatDays(days_left)}`;
}

export function ExpiryText({ subscription }: { subscription: Subscription }) {
    return (
        <span className="flex flex-col">
            <span className="tabular-nums">{subscription.expires_at ? formatDate(subscription.expires_at) : '—'}</span>
            <span className="text-sm text-muted-foreground">{daysLeftText(subscription)}</span>
        </span>
    );
}
