import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { MoneyInput } from '@/components/unit-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/Layouts/AppLayout';

interface Props {
    settings: { regular_price_per_kg: string; flour_exchange_fee_per_kg: string };
}

export default function Edit({ settings }: Props) {
    const form = useForm(settings);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('panel.settings.update'), { preserveScroll: true });
    };

    return (
        <AppLayout title="الإعدادات">
            <Card className="mx-auto max-w-2xl">
                <CardHeader>
                    <CardTitle>أسعار الخبز</CardTitle>
                    <CardDescription>تُستخدم هذه الأسعار تلقائيًا عند تسجيل البيع، ولا تغيّر المبيعات المسجّلة سابقًا</CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit} className="grid gap-5" noValidate>
                        <Field
                            id="regular_price_per_kg"
                            label="سعر الكيلو للبيع النقدي"
                            hint="سعر كيلو الخبز عند البيع العادي"
                            error={form.errors.regular_price_per_kg}
                        >
                            <MoneyInput
                                id="regular_price_per_kg"
                                value={form.data.regular_price_per_kg}
                                onChange={(e) => form.setData('regular_price_per_kg', e.target.value)}
                                aria-invalid={!!form.errors.regular_price_per_kg}
                                aria-describedby="regular_price_per_kg-error"
                            />
                        </Field>
                        <Field
                            id="flour_exchange_fee_per_kg"
                            label="رسم تبديل الطحين للكيلو"
                            hint="ما يدفعه العميل عن كل كيلو خبز يأخذه مقابل الطحين الذي أودعه"
                            error={form.errors.flour_exchange_fee_per_kg}
                        >
                            <MoneyInput
                                id="flour_exchange_fee_per_kg"
                                value={form.data.flour_exchange_fee_per_kg}
                                onChange={(e) => form.setData('flour_exchange_fee_per_kg', e.target.value)}
                                aria-invalid={!!form.errors.flour_exchange_fee_per_kg}
                                aria-describedby="flour_exchange_fee_per_kg-error"
                            />
                        </Field>
                        <div>
                            <Button type="submit" className="h-11 w-full px-6 sm:w-auto" disabled={form.processing}>
                                {form.processing && <LoaderCircle className="animate-spin" />}
                                حفظ الأسعار
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
