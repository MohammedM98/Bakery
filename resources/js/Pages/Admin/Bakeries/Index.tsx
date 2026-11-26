import { Link, router } from '@inertiajs/react';
import { Building2, CalendarPlus, MoreVertical, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { RenewDialog } from '@/components/renew-dialog';
import { ExpiryText, SubscriptionBadge, type Subscription } from '@/components/subscription';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/Layouts/AppLayout';
import type { Paginated } from '@/types';

interface BakeryRow extends Subscription {
    id: number;
    name: string;
    phone: string | null;
    owner: { name: string; email: string } | null;
}

function RowActions({ bakery, onRenew, onDelete }: { bakery: BakeryRow; onRenew: () => void; onDelete: () => void }) {
    const stopped = bakery.subscription_status !== 'active';
    return (
        // modal={false}: the menu opens dialogs; a modal menu closing under them can leave the page unclickable.
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-11" aria-label={`إجراءات ${bakery.name}`}>
                    <MoreVertical />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem asChild>
                    <Link href={route('admin.bakeries.edit', bakery.id)}>
                        <Pencil />
                        تعديل البيانات
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onRenew}>
                    <CalendarPlus />
                    تجديد الاشتراك
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={() =>
                        router.post(route('admin.bakeries.status', bakery.id), { status: stopped ? 'active' : 'inactive' }, { preserveScroll: true })
                    }
                >
                    <Power />
                    {stopped ? 'تفعيل الاشتراك' : 'إيقاف الاشتراك'}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2 />
                    حذف المخبز
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export default function Index({ bakeries }: { bakeries: Paginated<BakeryRow> }) {
    const [renewing, setRenewing] = useState<BakeryRow | null>(null);
    const [deleting, setDeleting] = useState<BakeryRow | null>(null);
    const [processing, setProcessing] = useState(false);

    const destroy = () => {
        if (!deleting) return;
        router.delete(route('admin.bakeries.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setDeleting(null),
        });
    };

    const addButton = (
        <Button asChild className="h-11 px-5">
            <Link href={route('admin.bakeries.create')}>
                <Plus />
                إضافة مخبز
            </Link>
        </Button>
    );

    return (
        <AppLayout title="المخابز والاشتراكات">
            <div className="grid gap-6">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {bakeries.total > 0 && `عدد المخابز: ${bakeries.total}`}
                    </p>
                    {bakeries.total > 0 && addButton}
                </div>

                {bakeries.data.length === 0 ? (
                    <EmptyState
                        icon={Building2}
                        title="لا توجد مخابز بعد"
                        description="أضف أول مخبز مع حساب صاحبه ومدة اشتراكه"
                        action={addButton}
                    />
                ) : (
                    <>
                        {/* Phones: one card per bakery. */}
                        <ul className="grid gap-3 md:hidden">
                            {bakeries.data.map((b) => (
                                <li key={b.id}>
                                    <Card className="gap-3 p-4">
                                        <div className="flex items-start gap-2">
                                            <Link href={route('admin.bakeries.edit', b.id)} className="min-w-0 flex-1">
                                                <span className="block truncate font-semibold">{b.name}</span>
                                                <span className="block truncate text-sm text-muted-foreground">
                                                    {b.owner?.name ?? 'بلا صاحب'}
                                                </span>
                                            </Link>
                                            <RowActions bakery={b} onRenew={() => setRenewing(b)} onDelete={() => setDeleting(b)} />
                                        </div>
                                        <div className="flex items-end justify-between gap-3 text-sm">
                                            <ExpiryText subscription={b} />
                                            <SubscriptionBadge subscription={b} />
                                        </div>
                                    </Card>
                                </li>
                            ))}
                        </ul>

                        {/* Tablet and up: a table. */}
                        <Card className="hidden py-0 md:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-6">المخبز</TableHead>
                                        <TableHead>صاحب المخبز</TableHead>
                                        <TableHead>الحالة</TableHead>
                                        <TableHead>ينتهي في</TableHead>
                                        <TableHead className="w-16 pe-6">
                                            <span className="sr-only">إجراءات</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {bakeries.data.map((b) => (
                                        <TableRow key={b.id}>
                                            <TableCell className="ps-6">
                                                <Link
                                                    href={route('admin.bakeries.edit', b.id)}
                                                    className="font-medium underline-offset-4 hover:underline"
                                                >
                                                    {b.name}
                                                </Link>
                                                {b.phone && (
                                                    <span dir="ltr" className="block text-end text-sm text-muted-foreground tabular-nums">
                                                        {b.phone}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {b.owner ? (
                                                    <>
                                                        <span className="block">{b.owner.name}</span>
                                                        <span dir="ltr" className="block text-end text-sm text-muted-foreground">
                                                            {b.owner.email}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <SubscriptionBadge subscription={b} />
                                            </TableCell>
                                            <TableCell>
                                                <ExpiryText subscription={b} />
                                            </TableCell>
                                            <TableCell className="pe-6">
                                                <RowActions bakery={b} onRenew={() => setRenewing(b)} onDelete={() => setDeleting(b)} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>

                        <PaginationLinks page={bakeries} />
                    </>
                )}
            </div>

            <RenewDialog bakery={renewing} onOpenChange={(open) => !open && setRenewing(null)} />
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && !processing && setDeleting(null)}
                title={`حذف مخبز "${deleting?.name ?? ''}" نهائيًا؟`}
                description="يُحذف المخبز وحساب صاحبه وكل عملائه ومبيعاته ودفعاته. لا يمكن التراجع عن هذا الإجراء."
                confirmLabel="حذف نهائيًا"
                processing={processing}
                onConfirm={destroy}
            />
        </AppLayout>
    );
}
