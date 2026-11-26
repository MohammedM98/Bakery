import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { ComponentProps, FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/Layouts/AppLayout';

export default function Create() {
    const form = useForm({
        name: '',
        phone: '',
        address: '',
        subscription_months: '1',
        owner_name: '',
        owner_email: '',
        owner_password: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('admin.bakeries.store'), { onError: () => form.reset('owner_password') });
    };

    const input = (id: keyof typeof form.data, props: ComponentProps<typeof Input> = {}) => (
        <Input
            id={id}
            className="h-11"
            value={form.data[id]}
            onChange={(e) => form.setData(id, e.target.value)}
            aria-invalid={!!form.errors[id]}
            aria-describedby={`${id}-error`}
            {...props}
        />
    );

    return (
        <AppLayout title="إضافة مخبز">
            <form onSubmit={submit} className="mx-auto grid max-w-2xl gap-6" noValidate>
                <Card>
                    <CardHeader>
                        <CardTitle>بيانات المخبز</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Field id="name" label="اسم المخبز" error={form.errors.name}>
                            {input('name', { autoFocus: true })}
                        </Field>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field id="phone" label="رقم الهاتف (اختياري)" error={form.errors.phone}>
                                {input('phone', { type: 'tel', inputMode: 'tel', dir: 'ltr', className: 'h-11 text-end' })}
                            </Field>
                            <Field id="address" label="العنوان (اختياري)" error={form.errors.address}>
                                {input('address')}
                            </Field>
                        </div>
                        <Field
                            id="subscription_months"
                            label="مدة الاشتراك الأولى (بالأشهر)"
                            hint="من 1 إلى 24 شهرًا، تبدأ من اليوم."
                            error={form.errors.subscription_months}
                        >
                            {input('subscription_months', {
                                type: 'number',
                                inputMode: 'numeric',
                                min: 1,
                                max: 24,
                                dir: 'ltr',
                                className: 'h-11 w-32 text-center tabular-nums',
                            })}
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>حساب صاحب المخبز</CardTitle>
                        <CardDescription>يدخل بهذا البريد وكلمة المرور إلى لوحة مخبزه.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        <Field id="owner_name" label="الاسم" error={form.errors.owner_name}>
                            {input('owner_name', { autoComplete: 'off' })}
                        </Field>
                        <Field id="owner_email" label="البريد الإلكتروني" error={form.errors.owner_email}>
                            {input('owner_email', { type: 'email', dir: 'ltr', autoComplete: 'off', className: 'h-11 text-end' })}
                        </Field>
                        <Field id="owner_password" label="كلمة المرور" hint="8 أحرف على الأقل." error={form.errors.owner_password}>
                            {input('owner_password', { type: 'password', dir: 'ltr', autoComplete: 'new-password', className: 'h-11 text-end' })}
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Button type="button" variant="outline" className="h-11 px-6" asChild>
                        <Link href={route('admin.bakeries.index')}>إلغاء</Link>
                    </Button>
                    <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                        {form.processing && <LoaderCircle className="animate-spin" />}
                        إنشاء المخبز
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
