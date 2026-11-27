import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import type { ComponentProps, FormEvent } from 'react';
import { Field } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export interface BakeryDetails {
    id: number;
    name: string;
    phone: string | null;
    address: string | null;
}

/** "إلغاء": closes the dialog, or goes back to the list on the full page. */
function Cancel({ onDone }: { onDone?: () => void }) {
    return onDone ? (
        <Button type="button" variant="outline" className="h-11 px-6" onClick={onDone}>
            إلغاء
        </Button>
    ) : (
        <Button type="button" variant="outline" className="h-11 px-6" asChild>
            <Link href={route('admin.bakeries.index')}>إلغاء</Link>
        </Button>
    );
}

/** A new bakery with its owner's account. In a dialog `onDone` closes it on cancel and after saving. */
export function BakeryCreateForm({ onDone }: { onDone?: () => void }) {
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
        form.post(route('admin.bakeries.store'), { preserveScroll: true, onError: () => form.reset('owner_password'), onSuccess: () => onDone?.() });
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
        <form onSubmit={submit} className="grid gap-6" noValidate>
            <fieldset className="grid gap-5">
                <legend className="mb-1 font-semibold">بيانات المخبز</legend>
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
                <Field id="subscription_months" label="مدة الاشتراك الأولى (بالأشهر)" hint="من 1 إلى 24 شهرًا، تبدأ من اليوم" error={form.errors.subscription_months}>
                    {input('subscription_months', { type: 'number', inputMode: 'numeric', min: 1, max: 24, dir: 'ltr', className: 'h-11 w-32 text-center tabular-nums' })}
                </Field>
            </fieldset>

            <fieldset className="grid gap-5 border-t pt-5">
                <legend className="sr-only">حساب صاحب المخبز</legend>
                <div>
                    <p className="font-semibold">حساب صاحب المخبز</p>
                    <p className="text-sm text-muted-foreground">يدخل بهذا البريد وكلمة المرور إلى لوحة مخبزه</p>
                </div>
                <Field id="owner_name" label="الاسم" error={form.errors.owner_name}>
                    {input('owner_name', { autoComplete: 'off' })}
                </Field>
                <Field id="owner_email" label="البريد الإلكتروني" error={form.errors.owner_email}>
                    {input('owner_email', { type: 'email', dir: 'ltr', autoComplete: 'off', className: 'h-11 text-end' })}
                </Field>
                <Field id="owner_password" label="كلمة المرور" hint="8 أحرف على الأقل" error={form.errors.owner_password}>
                    {input('owner_password', { type: 'password', dir: 'ltr', autoComplete: 'new-password', className: 'h-11 text-end' })}
                </Field>
            </fieldset>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Cancel onDone={onDone} />
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    إنشاء المخبز
                </Button>
            </div>
        </form>
    );
}

export function BakeryDetailsForm({ bakery, onDone }: { bakery: BakeryDetails; onDone?: () => void }) {
    const form = useForm({ name: bakery.name, phone: bakery.phone ?? '', address: bakery.address ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put(route('admin.bakeries.update', bakery.id), { preserveScroll: true, onSuccess: () => onDone?.() });
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
            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Cancel onDone={onDone} />
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    حفظ التعديلات
                </Button>
            </div>
        </form>
    );
}

