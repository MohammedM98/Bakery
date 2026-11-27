import { Link, router, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogShell } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { KgInput } from '@/components/unit-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/lib/can';
import { newSubmissionId } from '@/lib/format';

export interface CustomerFields {
    id: number;
    name: string;
    mobile_number: string;
    notes: string | null;
}

/** Create when `customer` is absent (with an optional opening flour balance), edit otherwise. */
export function CustomerForm({ customer, onDone }: { customer?: CustomerFields; /** In a dialog: closes it on cancel and after saving. */ onDone?: () => void }) {
    const can = useCan();
    const form = useForm({
        name: customer?.name ?? '',
        mobile_number: customer?.mobile_number ?? '',
        notes: customer?.notes ?? '',
        flour_balance_kg: '',
        submission_id: newSubmissionId(),
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        if (customer) {
            form.transform(({ flour_balance_kg: _, submission_id: __, ...data }) => data);
            form.put(route('panel.customers.update', customer.id), { preserveScroll: true, onSuccess: () => onDone?.() });
        } else {
            // A new id for the next customer when the form stays open after saving.
            form.post(route('panel.customers.store'), {
                preserveScroll: true,
                onSuccess: () => {
                    form.setData('submission_id', newSubmissionId());
                    onDone?.();
                },
            });
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
            {!customer && can('record-deposit') && (
                <Field
                    id="flour_balance_kg"
                    label="رصيد طحين ابتدائي له (اختياري)"
                    hint="الطحين الموجود للعميل عند المخبز قبل استخدام النظام"
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
                {onDone ? (
                    <Button type="button" variant="outline" className="h-11 px-6" onClick={onDone}>
                        إلغاء
                    </Button>
                ) : (
                    can('view-customers') && (
                        <Button type="button" variant="outline" className="h-11 px-6" asChild>
                            <Link href={route('panel.customers.index')}>إلغاء</Link>
                        </Button>
                    )
                )}
                <Button type="submit" className="h-11 px-6" disabled={form.processing}>
                    {form.processing && <LoaderCircle className="animate-spin" />}
                    {customer ? 'حفظ التعديلات' : 'حفظ العميل'}
                </Button>
            </div>
        </form>
    );
}

/** Add a customer (no `customer`) or edit one, in a dialog; editing also offers the owner's delete. */
export function CustomerDialog({ customer, open, onOpenChange }: { customer?: CustomerFields; open: boolean; onOpenChange: (open: boolean) => void }) {
    const can = useCan();
    const [deleting, setDeleting] = useState(false);
    const destroy = () =>
        customer &&
        router.delete(route('panel.customers.destroy', customer.id), {
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
        });

    return (
        <DialogShell open={open} onOpenChange={onOpenChange} title={customer ? `تعديل ${customer.name}` : 'إضافة عميل'}>
            <CustomerForm customer={customer} onDone={() => onOpenChange(false)} />
            {customer && can('delete-customers') && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <p className="text-sm text-muted-foreground">يُحذف العميل مع كل مبيعاته وحركات كشفيه</p>
                    <ConfirmDialog
                        trigger={
                            <Button type="button" variant="destructive" className="h-10">
                                حذف العميل
                            </Button>
                        }
                        title={`حذف «${customer.name}» نهائيًا؟`}
                        description="تُحذف كل مبيعات العميل وكل حركات كشفيه المالي والطحين. لا يمكن التراجع عن هذا الإجراء."
                        confirmLabel="حذف نهائيًا"
                        processing={deleting}
                        onConfirm={destroy}
                    />
                </div>
            )}
        </DialogShell>
    );
}
