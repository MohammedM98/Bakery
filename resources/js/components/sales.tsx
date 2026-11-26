import { Link, router, useForm, usePage } from '@inertiajs/react';
import { CircleCheck, Lock } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { KgText } from '@/components/kg-text';
import { MoneyText } from '@/components/money-text';
import { KgInput } from '@/components/unit-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatMoney, newSubmissionId } from '@/lib/format';
import type { SaleRow } from '@/types';

export type PaymentStatus = 'paid' | 'unpaid';

/** Paid (final), partly paid with what is left, or unpaid. */
export function SaleStatusBadge({ sale }: { sale: SaleRow }) {
    if (sale.payment_status === 'paid') {
        return (
            <Badge className="bg-success/15 text-success" title="عملية مؤكدة، لا يمكن التراجع عنها">
                <Lock aria-hidden />
                مدفوع
            </Badge>
        );
    }
    if (sale.is_partially_paid) {
        return (
            <Badge className="bg-warning/15 text-warning">
                مدفوع جزئيًا، متبقٍ <MoneyText amount={sale.remaining_amount} />
            </Badge>
        );
    }
    return <Badge className="bg-destructive/10 text-destructive">غير مدفوع</Badge>;
}

/** Two large toggle buttons instead of radios: one tap, easy to hit on a phone. */
export function PaymentStatusField({
    value,
    onChange,
    error,
}: {
    value: PaymentStatus;
    onChange: (value: PaymentStatus) => void;
    error?: string;
}) {
    const options: [PaymentStatus, string][] = [
        ['paid', 'مدفوع'],
        ['unpaid', 'غير مدفوع'],
    ];
    return (
        <Field id="payment_status" label="حالة الدفع" error={error}>
            <div id="payment_status" role="group" aria-label="حالة الدفع" className="grid grid-cols-2 gap-2">
                {options.map(([status, label]) => (
                    <Button
                        key={status}
                        type="button"
                        variant={value === status ? 'default' : 'outline'}
                        className="h-11"
                        aria-pressed={value === status}
                        onClick={() => onChange(status)}
                    >
                        {label}
                    </Button>
                ))}
            </div>
        </Field>
    );
}

/** "Confirm payment" for an unpaid sale: confirms the rest as received, with no undo. */
export function MarkPaidButton({ sale, className }: { sale: SaleRow; className?: string }) {
    const [open, setOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={setOpen}
            trigger={
                <Button variant="outline" className={className ?? 'h-11'}>
                    <CircleCheck />
                    تأكيد الدفع
                </Button>
            }
            title="تأكيد استلام الدفع"
            description={`سيتم تأكيد استلام المتبقي وقدره ${formatMoney(sale.remaining_amount)} نهائيًا لهذه العملية. لا يمكن التراجع عن هذا الإجراء بعد ذلك.`}
            confirmLabel="تأكيد الدفع"
            variant="default"
            processing={processing}
            onConfirm={() =>
                router.post(route('panel.sales.mark-paid', sale.id), {}, {
                    preserveScroll: true,
                    onStart: () => setProcessing(true),
                    onFinish: () => setProcessing(false),
                    onSuccess: () => setOpen(false),
                })
            }
        />
    );
}

function BuyerName({ sale }: { sale: SaleRow }) {
    return sale.customer_id ? (
        <Link href={route('panel.customers.show', sale.customer_id)} className="font-medium underline-offset-4 hover:underline">
            {sale.buyer}
        </Link>
    ) : (
        <span className="font-medium">{sale.buyer}</span>
    );
}

const typeLabel = (sale: SaleRow) => (sale.sale_type === 'flour_exchange' ? 'مقابل قمح' : 'بيع نقدي');

/** Sales as cards on the phone and a table from md up. */
export function SaleList({ sales, showType = false }: { sales: SaleRow[]; showType?: boolean }) {
    return (
        <>
            <ul className="grid gap-3 md:hidden">
                {sales.map((sale) => (
                    <li key={sale.id}>
                        <Card className="gap-3 p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <BuyerName sale={sale} />
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {formatDate(sale.sale_date)}
                                        {showType && ` · ${typeLabel(sale)}`}
                                        {sale.buyer_mobile && (
                                            <>
                                                {' · '}
                                                <span dir="ltr">{sale.buyer_mobile}</span>
                                            </>
                                        )}
                                    </p>
                                </div>
                                <div className="text-end">
                                    <MoneyText amount={sale.total_amount} className="block font-semibold" />
                                    <KgText kg={sale.kg_amount} className="text-sm text-muted-foreground" />
                                </div>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <SaleStatusBadge sale={sale} />
                                {sale.payment_status === 'unpaid' && <MarkPaidButton sale={sale} />}
                            </div>
                        </Card>
                    </li>
                ))}
            </ul>

            <Card className="hidden py-0 md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="ps-6">التاريخ</TableHead>
                            <TableHead>العميل</TableHead>
                            {showType && <TableHead>النوع</TableHead>}
                            <TableHead className="text-end">الكمية</TableHead>
                            <TableHead className="text-end">المبلغ</TableHead>
                            <TableHead>الحالة</TableHead>
                            <TableHead className="pe-6">
                                <span className="sr-only">إجراء</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sales.map((sale) => (
                            <TableRow key={sale.id}>
                                <TableCell className="ps-6 tabular-nums">{formatDate(sale.sale_date)}</TableCell>
                                <TableCell>
                                    <BuyerName sale={sale} />
                                    {sale.buyer_mobile && (
                                        <span dir="ltr" className="block text-end text-xs text-muted-foreground tabular-nums">
                                            {sale.buyer_mobile}
                                        </span>
                                    )}
                                </TableCell>
                                {showType && <TableCell>{typeLabel(sale)}</TableCell>}
                                <TableCell className="text-end">
                                    <KgText kg={sale.kg_amount} />
                                </TableCell>
                                <TableCell className="text-end">
                                    <MoneyText amount={sale.total_amount} />
                                </TableCell>
                                <TableCell>
                                    <SaleStatusBadge sale={sale} />
                                </TableCell>
                                <TableCell className="pe-6 text-end">
                                    {sale.payment_status === 'unpaid' && <MarkPaidButton sale={sale} className="h-9" />}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </Card>
        </>
    );
}

function emptyCashSale(today: string) {
    return {
        kg_amount: '',
        payment_status: 'paid' as PaymentStatus,
        buyer_name: '',
        buyer_mobile: '',
        sale_date: today,
        notes: '',
        submission_id: newSubmissionId(),
    };
}

/**
 * Cash sale by the kilo. Only the quantity is needed: paid and today are the defaults,
 * the buyer is optional (walk-in). The total is a preview; the server prices the sale.
 */
export function useCashSaleForm(onSaved?: () => void) {
    const { currentDate: today } = usePage().props;
    const form = useForm(emptyCashSale(today));
    const submit = () =>
        form.post(route('panel.sales.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyCashSale(today));
                onSaved?.();
            },
        });
    return { form, submit };
}

export function CashSaleFields({ form, pricePerKg }: { form: ReturnType<typeof useCashSaleForm>['form']; pricePerKg: string }) {
    const { data, setData, errors } = form;
    const total = (Number(data.kg_amount) || 0) * Number(pricePerKg);

    return (
        <div className="grid gap-5">
            <Field id="kg_amount" label="الكمية" error={errors.kg_amount}>
                <KgInput
                    id="kg_amount"
                    autoFocus
                    min="0.01"
                    value={data.kg_amount}
                    onChange={(e) => setData('kg_amount', e.target.value)}
                    aria-invalid={!!errors.kg_amount}
                    aria-describedby="kg_amount-error"
                />
            </Field>

            <div className="flex items-center justify-between rounded-xl bg-secondary px-4 py-3 text-secondary-foreground">
                <span className="text-sm">
                    الإجمالي المتوقع <span className="text-muted-foreground">(<MoneyText amount={pricePerKg} /> للكيلو)</span>
                </span>
                <MoneyText amount={total} className="text-lg font-semibold" />
            </div>

            <PaymentStatusField value={data.payment_status} onChange={(v) => setData('payment_status', v)} error={errors.payment_status} />

            <div className="grid gap-5 sm:grid-cols-2">
                <Field id="buyer_name" label="اسم المشتري (اختياري)" error={errors.buyer_name}>
                    <Input
                        id="buyer_name"
                        className="h-11"
                        value={data.buyer_name}
                        onChange={(e) => setData('buyer_name', e.target.value)}
                        aria-invalid={!!errors.buyer_name}
                        aria-describedby="buyer_name-error"
                    />
                </Field>
                <Field id="buyer_mobile" label="جوال المشتري (اختياري)" error={errors.buyer_mobile}>
                    <Input
                        id="buyer_mobile"
                        type="tel"
                        dir="ltr"
                        className="h-11 text-end"
                        value={data.buyer_mobile}
                        onChange={(e) => setData('buyer_mobile', e.target.value)}
                        aria-invalid={!!errors.buyer_mobile}
                        aria-describedby="buyer_mobile-error"
                    />
                </Field>
            </div>

            <DateAndNotes
                dateId="sale_date"
                date={data.sale_date}
                onDate={(v) => setData('sale_date', v)}
                dateError={errors.sale_date}
                notes={data.notes}
                onNotes={(v) => setData('notes', v)}
                notesError={errors.notes}
            />
        </div>
    );
}

/** The date (defaults to today) and optional notes that every record form ends with. */
export function DateAndNotes({
    dateId,
    date,
    onDate,
    dateError,
    notes,
    onNotes,
    notesError,
}: {
    dateId: string;
    date: string;
    onDate: (value: string) => void;
    dateError?: string;
    notes: string;
    onNotes: (value: string) => void;
    notesError?: string;
}) {
    return (
        <>
            <Field id={dateId} label="التاريخ" error={dateError}>
                <Input
                    id={dateId}
                    type="date"
                    className="h-11"
                    value={date}
                    onChange={(e) => onDate(e.target.value)}
                    aria-invalid={!!dateError}
                    aria-describedby={`${dateId}-error`}
                />
            </Field>
            <Field id={`${dateId}_notes`} label="ملاحظات (اختياري)" error={notesError}>
                <Textarea
                    id={`${dateId}_notes`}
                    rows={2}
                    value={notes}
                    onChange={(e) => onNotes(e.target.value)}
                    aria-invalid={!!notesError}
                    aria-describedby={`${dateId}_notes-error`}
                />
            </Field>
        </>
    );
}

/** The "record a sale" dialog used on the dashboard and the sales list. */
export function CashSaleDialog({ open, onOpenChange, pricePerKg }: { open: boolean; onOpenChange: (open: boolean) => void; pricePerKg: string }) {
    const { form, submit } = useCashSaleForm(() => onOpenChange(false));

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => {
                if (!next) form.clearErrors();
                onOpenChange(next);
            }}
            title="تسجيل عملية بيع"
            submitLabel="حفظ العملية"
            processing={form.processing}
            onSubmit={submit}
        >
            <CashSaleFields form={form} pricePerKg={pricePerKg} />
        </FormDialog>
    );
}
