import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { KgInput } from '@/components/unit-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export interface CustomerFields {
    id: number;
    name: string;
    mobile_number: string;
    notes: string | null;
}

/** Create when `customer` is absent (with an optional opening flour balance), edit otherwise. */
export function CustomerForm({ customer }: { customer?: CustomerFields }) {
    const form = useForm({
        name: customer?.name ?? '',
        mobile_number: customer?.mobile_number ?? '',
        notes: customer?.notes ?? '',
        flour_balance_kg: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (customer) {
            form.transform(({ flour_balance_kg: _, ...data }) => data);
            form.put(route('panel.customers.update', customer.id));
        } else {
            form.post(route('panel.customers.store'));
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <Field id="name" label="اسم العميل" error={form.errors.name}>
                <Input
                    id="name"
                    className="h-11"
                    autoFocus={!customer}
                    autoComplete="off"
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    aria-invalid={!!form.errors.name}
                    aria-describedby="name-error"
                />
            </Field>
            <Field id="mobile_number" label="رقم الجوال" error={form.errors.mobile_number}>
                <Input
                    id="mobile_number"
                    type="tel"
                    inputMode="tel"
                    dir="ltr"
                    className="h-11 text-end"
                    autoComplete="off"
                    value={form.data.mobile_number}
                    onChange={(e) => form.setData('mobile_number', e.target.value)}
                    aria-invalid={!!form.errors.mobile_number}
                    aria-describedby="mobile_number-error"
                />
            </Field>
            {!customer && (
                <Field
                    id="flour_balance_kg"
                    label="رصيد قمح ابتدائي (اختياري)"
                    hint="القمح الموجود للعميل عند المخبز قبل استخدام النظام"
                    error={form.errors.flour_balance_kg}
                >
                    <KgInput
                        id="flour_balance_kg"
                        placeholder="0"
                        value={form.data.flour_balance_kg}
                        onChange={(e) => form.setData('flour_balance_kg', e.target.value)}
                        aria-invalid={!!form.errors.flour_balance_kg}
                        aria-describedby="flour_balance_kg-error"
                    />
                </Field>
            )}
            <Field id="notes" label="ملاحظات (اختياري)" error={form.errors.notes}>
                <Textarea
                    id="notes"
                    rows={3}
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    aria-invalid={!!form.errors.notes}
                    aria-describedby="notes-error"
                />
            </Field>
            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" className="h-11 px-6" asChild>
                    <Link href={route('panel.customers.index')}>إلغاء</Link>
                </Button>
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {customer ? 'حفظ التعديلات' : 'حفظ العميل'}
                </Button>
            </div>
        </form>
    );
}
