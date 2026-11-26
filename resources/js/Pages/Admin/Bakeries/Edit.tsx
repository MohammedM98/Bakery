import { Link, router, useForm } from '@inertiajs/react';
import { CalendarPlus, LoaderCircle, Power } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Field } from '@/components/form-field';
import { RenewDialog } from '@/components/renew-dialog';
import { daysLeftText, SubscriptionBadge, type Subscription } from '@/components/subscription';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { formatDate } from '@/lib/format';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    bakery: Subscription & { id: number; name: string; phone: string | null; address: string | null };
    owners: { name: string; email: string }[];
}

function DetailsForm({ bakery }: Pick<Props, 'bakery'>) {
    const form = useForm({ name: bakery.name, phone: bakery.phone ?? '', address: bakery.address ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('admin.bakeries.update', bakery.id), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <Field id="name" label="اسم المخبز" error={form.errors.name}>
                <Input
                    id="name"
                    className="h-11"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    aria-invalid={!!form.errors.name}
                    aria-describedby="name-error"
                />
            </Field>
            <div className="grid gap-5 sm:grid-cols-2">
                <Field id="phone" label="رقم الهاتف (اختياري)" error={form.errors.phone}>
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        dir="ltr"
                        className="h-11 text-end"
                        value={form.data.phone}
                        onChange={(e) => form.setData('phone', e.target.value)}
                        aria-invalid={!!form.errors.phone}
                        aria-describedby="phone-error"
                    />
                </Field>
                <Field id="address" label="العنوان (اختياري)" error={form.errors.address}>
                    <Input
                        id="address"
                        className="h-11"
                        value={form.data.address}
                        onChange={(e) => form.setData('address', e.target.value)}
                        aria-invalid={!!form.errors.address}
                        aria-describedby="address-error"
                    />
                </Field>
            </div>
            <div>
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    حفظ التعديلات
                </Button>
            </div>
        </form>
    );
}

export default function Edit({ bakery, owners }: Props) {
    const [renewing, setRenewing] = useState(false);
    const [toggling, setToggling] = useState(false);
    const [deleting, setDeleting] = useState(false);
    const stopped = bakery.subscription_status !== 'active';

    const toggle = () =>
        router.post(route('admin.bakeries.toggle-status', bakery.id), {}, {
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
                    <CardContent>
                        <DetailsForm bakery={bakery} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>حساب صاحب المخبز</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {owners.length === 0 ? (
                            <p className="text-muted-foreground">لا يوجد حساب مرتبط بهذا المخبز.</p>
                        ) : (
                            <ul className="grid gap-3">
                                {owners.map((o) => (
                                    <li key={o.email} className="grid">
                                        <span className="font-medium">{o.name}</span>
                                        <span dir="ltr" className="text-end text-sm text-muted-foreground sm:text-start">
                                            {o.email}
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
                            يُحذف المخبز وحساب صاحبه وكل عملائه ومبيعاته ودفعاته نهائيًا.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ConfirmDialog
                            trigger={
                                <Button variant="destructive" className="h-11 px-6">
                                    حذف المخبز نهائيًا
                                </Button>
                            }
                            title={`حذف مخبز "${bakery.name}" نهائيًا؟`}
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
        </AppLayout>
    );
}
