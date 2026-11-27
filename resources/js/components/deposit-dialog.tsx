import { useForm, usePage } from '@inertiajs/react';
import { FormDialog } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { DateAndNotes } from '@/components/sales';
import { KgInput } from '@/components/unit-input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { newSubmissionId } from '@/lib/format';

/** "إيداع طحين" for one customer, or (on the dashboard) for a customer picked in the dialog. */
export function DepositDialog({
    customerId,
    customers,
    open,
    onOpenChange,
}: {
    customerId?: number;
    customers?: { id: number; name: string }[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({ customer: customerId ? String(customerId) : '', kg_amount: '', deposit_date: today, notes: '', submission_id: newSubmissionId() });
    const form = useForm(blank());
    const submit = () => {
        if (!form.data.customer) return form.setError('customer', 'اختر الزبون');
        form.transform(({ customer, ...data }) => data);
        form.post(route('panel.customers.deposits.store', form.data.customer), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => {
                if (!next) form.clearErrors();
                onOpenChange(next);
            }}
            title="إيداع طحين"
            description="تُسجَّل الكمية «له» في كشف الطحين، وتُخصم أولًا مما عليه إن وُجد"
            submitLabel="تسجيل الإيداع"
            processing={form.processing}
            onSubmit={submit}
        >
            {customers && (
                <Field id="deposit_customer" label="الزبون" error={form.errors.customer}>
                    <Select value={form.data.customer} onValueChange={(v) => form.setData('customer', v)} dir="rtl">
                        <SelectTrigger id="deposit_customer" className="w-full data-[size=default]:h-11" aria-invalid={!!form.errors.customer} aria-describedby="deposit_customer-error">
                            <SelectValue placeholder="اختر الزبون" />
                        </SelectTrigger>
                        <SelectContent>
                            {customers.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {c.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            )}
            <Field id="deposit_kg" label="الكمية" error={form.errors.kg_amount}>
                <KgInput
                    id="deposit_kg"
                    autoFocus={!customers}
                    min="0.01"
                    value={form.data.kg_amount}
                    onChange={(e) => form.setData('kg_amount', e.target.value)}
                    aria-invalid={!!form.errors.kg_amount}
                    aria-describedby="deposit_kg-error"
                />
            </Field>
            <DateAndNotes
                dateId="deposit_date"
                date={form.data.deposit_date}
                onDate={(v) => form.setData('deposit_date', v)}
                dateError={form.errors.deposit_date}
                notes={form.data.notes}
                onNotes={(v) => form.setData('notes', v)}
                notesError={form.errors.notes}
            />
        </FormDialog>
    );
}
