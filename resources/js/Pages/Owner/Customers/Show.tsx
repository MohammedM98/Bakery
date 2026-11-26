import { Link, useForm, usePage } from '@inertiajs/react';
import { Banknote, HandCoins, Pencil, Receipt, Wheat, WheatOff } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { KgText } from '@/components/kg-text';
import { MoneyText } from '@/components/money-text';
import { DateAndNotes, PaymentStatusField, SaleList, type PaymentStatus } from '@/components/sales';
import { KgInput, MoneyInput } from '@/components/unit-input';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatKg, formatMoney, newSubmissionId } from '@/lib/format';
import { cn } from '@/lib/utils';
import AppLayout from '@/Layouts/AppLayout';
import type { SaleRow } from '@/types';

interface Customer {
    id: number;
    name: string;
    mobile_number: string;
    notes: string | null;
    flour_balance_kg: string;
}

interface Deposit {
    id: number;
    deposit_date: string;
    kg_amount: string;
    notes: string | null;
}

interface Payment {
    id: number;
    payment_date: string;
    amount: string;
    notes: string | null;
}

interface Props {
    customer: Customer;
    outstandingBalance: string;
    flourExchangeFee: string;
    sales: SaleRow[];
    deposits: Deposit[];
    payments: Payment[];
}

type Open = 'deposit' | 'bread' | 'payment' | null;

interface DialogProps {
    customer: Customer;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

function closeHandler(form: { clearErrors: () => void }, onOpenChange: (open: boolean) => void) {
    return (next: boolean) => {
        if (!next) form.clearErrors();
        onOpenChange(next);
    };
}

function DepositDialog({ customer, open, onOpenChange }: DialogProps) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({ kg_amount: '', deposit_date: today, notes: '', submission_id: newSubmissionId() });
    const form = useForm(blank());
    const submit = () =>
        form.post(route('panel.customers.deposits.store', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="تسجيل استلام قمح"
            description="تُضاف الكمية إلى رصيد القمح لدى العميل"
            submitLabel="إضافة للرصيد"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field id="deposit_kg" label="الكمية" error={form.errors.kg_amount}>
                <KgInput
                    id="deposit_kg"
                    autoFocus
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

function BreadDialog({ customer, open, onOpenChange, fee }: DialogProps & { fee: string }) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({
        kg_amount: '',
        payment_status: 'paid' as PaymentStatus,
        sale_date: today,
        notes: '',
        submission_id: newSubmissionId(),
    });
    const form = useForm(blank());
    const submit = () =>
        form.post(route('panel.customers.flour-sales.store', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });
    const total = (Number(form.data.kg_amount) || 0) * Number(fee);

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="تسليم خبز مقابل القمح"
            description="تُخصم الكمية من رصيد القمح، ويُحسب رسم التبديل لكل كيلو"
            submitLabel="تسجيل التسليم"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field id="bread_kg" label="الكمية" hint={`الرصيد المتاح: ${formatKg(customer.flour_balance_kg)}`} error={form.errors.kg_amount}>
                <KgInput
                    id="bread_kg"
                    autoFocus
                    min="0.01"
                    value={form.data.kg_amount}
                    onChange={(e) => form.setData('kg_amount', e.target.value)}
                    aria-invalid={!!form.errors.kg_amount}
                    aria-describedby="bread_kg-error"
                />
            </Field>
            <div className="flex items-center justify-between rounded-xl bg-secondary px-4 py-3 text-secondary-foreground">
                <span className="text-sm">
                    رسم التبديل <span className="text-muted-foreground">(<MoneyText amount={fee} /> للكيلو)</span>
                </span>
                <MoneyText amount={total} className="text-lg font-semibold" />
            </div>
            <PaymentStatusField value={form.data.payment_status} onChange={(v) => form.setData('payment_status', v)} error={form.errors.payment_status} />
            <DateAndNotes
                dateId="bread_date"
                date={form.data.sale_date}
                onDate={(v) => form.setData('sale_date', v)}
                dateError={form.errors.sale_date}
                notes={form.data.notes}
                onNotes={(v) => form.setData('notes', v)}
                notesError={form.errors.notes}
            />
        </FormDialog>
    );
}

function PaymentDialog({ customer, open, onOpenChange, outstanding }: DialogProps & { outstanding: string }) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({ amount: '', payment_date: today, notes: '', submission_id: newSubmissionId() });
    const form = useForm(blank());
    const submit = () =>
        form.post(route('panel.customers.payments.store', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="تسجيل دفعة"
            description="تُسدَّد بها العمليات غير المدفوعة بدءًا من الأقدم"
            submitLabel="تسجيل الدفعة"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field id="payment_amount" label="المبلغ" hint={`المبلغ المستحق: ${formatMoney(outstanding)}`} error={form.errors.amount}>
                <div className="flex gap-2">
                    <div className="flex-1">
                        <MoneyInput
                            id="payment_amount"
                            autoFocus
                            min="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            aria-invalid={!!form.errors.amount}
                            aria-describedby="payment_amount-error"
                        />
                    </div>
                    <Button type="button" variant="outline" className="h-11" onClick={() => form.setData('amount', outstanding)}>
                        كامل المستحق
                    </Button>
                </div>
            </Field>
            <DateAndNotes
                dateId="payment_date"
                date={form.data.payment_date}
                onDate={(v) => form.setData('payment_date', v)}
                dateError={form.errors.payment_date}
                notes={form.data.notes}
                onNotes={(v) => form.setData('notes', v)}
                notesError={form.errors.notes}
            />
        </FormDialog>
    );
}

function Stat({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <Card className={cn('gap-1 p-5', className)}>
            <span className="text-sm opacity-75">{label}</span>
            {children}
        </Card>
    );
}

export default function Show({ customer, outstandingBalance, flourExchangeFee, sales, deposits, payments }: Props) {
    const [open, setOpen] = useState<Open>(null);
    const owes = Number(outstandingBalance) > 0;
    const dialog = (name: Exclude<Open, null>) => ({ customer, open: open === name, onOpenChange: (o: boolean) => setOpen(o ? name : null) });

    return (
        <AppLayout title={customer.name}>
            <div className="grid gap-6">
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <Button className="h-11" onClick={() => setOpen('bread')}>
                        <Receipt />
                        تسليم خبز مقابل القمح
                    </Button>
                    <Button variant="secondary" className="h-11" onClick={() => setOpen('deposit')}>
                        <Wheat />
                        تسجيل استلام قمح
                    </Button>
                    {owes && (
                        <Button variant="secondary" className="h-11" onClick={() => setOpen('payment')}>
                            <HandCoins />
                            تسجيل دفعة
                        </Button>
                    )}
                    <Button variant="outline" asChild className="h-11 sm:ms-auto">
                        <Link href={route('panel.customers.edit', customer.id)}>
                            <Pencil />
                            تعديل
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <Stat label="رصيد القمح الحالي" className="bg-primary text-primary-foreground">
                        <KgText kg={customer.flour_balance_kg} className="text-2xl font-semibold sm:text-3xl" />
                    </Stat>
                    <Stat label="المبلغ المستحق" className={cn(owes && 'ring-destructive/30')}>
                        <MoneyText amount={outstandingBalance} className={cn('text-2xl font-semibold sm:text-3xl', owes ? 'text-destructive' : 'text-muted-foreground')} />
                    </Stat>
                    <Stat label="رقم الجوال" className="col-span-2 sm:col-span-1">
                        <span dir="ltr" className="text-end text-lg font-medium tabular-nums">
                            {customer.mobile_number}
                        </span>
                    </Stat>
                    <Stat label="ملاحظات" className="col-span-2 sm:col-span-1">
                        <p className="text-sm whitespace-pre-line">{customer.notes || '—'}</p>
                    </Stat>
                </div>

                <Tabs defaultValue="sales" dir="rtl">
                    <TabsList className="w-full sm:w-auto group-data-horizontal/tabs:h-11">
                        <TabsTrigger value="sales" className="px-4">
                            المبيعات ({sales.length})
                        </TabsTrigger>
                        <TabsTrigger value="deposits" className="px-4">
                            استلام القمح ({deposits.length})
                        </TabsTrigger>
                        <TabsTrigger value="payments" className="px-4">
                            الدفعات ({payments.length})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="sales" className="mt-2">
                        {sales.length ? (
                            <SaleList sales={sales} showType />
                        ) : (
                            <EmptyState icon={Receipt} title="لا يوجد سجل مبيعات" />
                        )}
                    </TabsContent>
                    <TabsContent value="deposits" className="mt-2">
                        {deposits.length ? (
                            <RecordTable
                                rows={deposits.map((d) => ({ id: d.id, date: d.deposit_date, value: <KgText kg={d.kg_amount} />, notes: d.notes }))}
                                valueLabel="الكمية"
                            />
                        ) : (
                            <EmptyState
                                icon={WheatOff}
                                title="لا يوجد سجل استلام قمح"
                                action={
                                    <Button variant="outline" className="h-11" onClick={() => setOpen('deposit')}>
                                        تسجيل استلام قمح
                                    </Button>
                                }
                            />
                        )}
                    </TabsContent>
                    <TabsContent value="payments" className="mt-2">
                        {payments.length ? (
                            <RecordTable
                                rows={payments.map((p) => ({ id: p.id, date: p.payment_date, value: <MoneyText amount={p.amount} className="text-success" />, notes: p.notes }))}
                                valueLabel="المبلغ"
                            />
                        ) : (
                            <EmptyState icon={Banknote} title="لا يوجد سجل دفعات" />
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            <DepositDialog {...dialog('deposit')} />
            <BreadDialog {...dialog('bread')} fee={flourExchangeFee} />
            <PaymentDialog {...dialog('payment')} outstanding={outstandingBalance} />
        </AppLayout>
    );
}

/** Date, value and notes: deposits and payments share this shape. */
function RecordTable({ rows, valueLabel }: { rows: { id: number; date: string; value: ReactNode; notes: string | null }[]; valueLabel: string }) {
    return (
        <Card className="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="ps-6">التاريخ</TableHead>
                        <TableHead className="text-end">{valueLabel}</TableHead>
                        <TableHead className="pe-6">ملاحظات</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="ps-6 tabular-nums">{formatDate(row.date)}</TableCell>
                            <TableCell className="text-end">{row.value}</TableCell>
                            <TableCell className="pe-6 whitespace-normal text-muted-foreground">{row.notes || '—'}</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </Card>
    );
}
