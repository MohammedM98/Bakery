import { Link, router } from '@inertiajs/react';
import { CalendarPlus, LoaderCircle, Pencil, Power } from 'lucide-react';
import { useState } from 'react';
import { BakeryDetailsForm } from '@/components/bakery-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogShell } from '@/components/form-dialog';
import { RenewDialog } from '@/components/renew-dialog';
import { daysLeftText, SubscriptionBadge, type Subscription } from '@/components/subscription';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/lib/format';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    bakery: Subscription & { id: number; name: string; phone: string | null; address: string | null };
    owners: { name: string; email: string }[];
}

export default function Edit({ bakery, owners }: Props) {
    const [renewing, setRenewing] = useState(false);
    const [editing, setEditing] = useState(false);
    const [toggling, setToggling] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const stopped = bakery.subscription_status !== 'active';

    const toggle = () =>
        router.post(route('admin.bakeries.status', bakery.id), { status: stopped ? 'active' : 'inactive' }, {
            preserveScroll: true,
            onStart: () => setToggling(true),
            onFinish: () => setToggling(false),
        });

    const destroy = () =>
        router.delete(route('admin.bakeries.destroy', bakery.id), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });

    return (
        <AppLayout title={bakery.name}>
            <div className="mx-auto grid max-w-2xl gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>الاشتراك</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <dl className="grid grid-cols-2 gap-4">
                            <div className="grid gap-1">
                                <dt className="text-sm text-muted-foreground">الحالة</dt>
                                <dd>
                                    <SubscriptionBadge subscription={bakery} />
                                </dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-sm text-muted-foreground">ينتهي في</dt>
                                <dd className="text-lg font-semibold tabular-nums">
                                    {bakery.expires_at ? formatDate(bakery.expires_at) : '—'}
                                </dd>
                                <dd className="text-sm text-muted-foreground">{daysLeftText(bakery)}</dd>
                            </div>
                        </dl>
                        <div className="flex flex-col gap-3 sm:flex-row">
                            <Button className="h-11 px-6" onClick={() => setRenewing(true)}>
                                <CalendarPlus />
                                تجديد الاشتراك
                            </Button>
                            <Button variant="outline" className="h-11 px-6" onClick={toggle} disabled={toggling}>
                                {toggling ? <LoaderCircle className="animate-spin" /> : <Power />}
                                {stopped ? 'تفعيل الاشتراك' : 'إيقاف الاشتراك'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>بيانات المخبز</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        <dl className="grid gap-3 text-sm sm:grid-cols-3">
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">الاسم</dt>
                                <dd className="font-medium">{bakery.name}</dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">الهاتف</dt>
                                <dd dir="ltr" className="text-end tabular-nums sm:text-start">
                                    {bakery.phone || '—'}
                                </dd>
                            </div>
                            <div className="grid gap-1">
                                <dt className="text-muted-foreground">العنوان</dt>
                                <dd>{bakery.address || '—'}</dd>
                            </div>
                        </dl>
                        <div>
                            <Button variant="outline" className="h-11 px-6" onClick={() => setEditing(true)}>
                                <Pencil />
                                تعديل البيانات
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>حساب صاحب المخبز</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {owners.length === 0 ? (
                            <p className="text-muted-foreground">لا يوجد حساب مرتبط بهذا المخبز</p>
                        ) : (
                            <ul className="grid gap-3">
                                {owners.map((o) => (
                                    <li key={o.email} className="grid">
                                        <span className="font-medium">{o.name}</span>
                                        <span className="text-sm text-muted-foreground">
                                            <bdi dir="ltr">{o.email}</bdi>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-destructive/30">
                    <CardHeader>
                        <CardTitle className="text-destructive">حذف المخبز</CardTitle>
                        <CardDescription>
                            يُحذف المخبز وحساب صاحبه وكل عملائه ومبيعاته ودفعاته نهائيًا
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive" className="h-11 px-6">
                                    حذف المخبز نهائيًا
                                </Button>
                            }
                            title={`حذف مخبز «${bakery.name}» نهائيًا؟`}
                            description="يُحذف المخبز وحساب صاحبه وكل عملائه ومبيعاته ودفعاته. لا يمكن التراجع عن هذا الإجراء."
                            confirmLabel="حذف نهائيًا"
                            processing={deleting}
                            onConfirm={destroy}
                        />
                    </CardContent>
                </Card>

                <div>
                    <Button variant="ghost" className="h-11" asChild>
                        <Link href={route('admin.bakeries.index')}>العودة إلى المخابز</Link>
                    </Button>
                </div>
            </div>

            <RenewDialog bakery={renewing ? bakery : null} onOpenChange={setRenewing} />
            <DialogShell open={editing} onOpenChange={setEditing} title="تعديل بيانات المخبز">
                <BakeryDetailsForm bakery={bakery} onDone={() => setEditing(false)} />
            </DialogShell>
        </AppLayout>
    );
}
