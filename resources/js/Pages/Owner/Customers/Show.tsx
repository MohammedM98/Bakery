import { Link, useForm, usePage } from '@inertiajs/react';
import { HandCoins, Pencil, Receipt, Scale, Wheat } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { Field } from '@/components/form-field';
import { FlourBalance } from '@/components/kg-text';
import { MoneyBalance, MoneyText } from '@/components/money-text';
import { RangePicker, StatementView, type Ledger, type Range } from '@/components/statement';
import { DateAndNotes, PaymentStatusField, SaleList, type PaymentStatus } from '@/components/sales';
import { KgInput, MoneyInput } from '@/components/unit-input';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatFlourBalance, formatMoney, newSubmissionId } from '@/lib/format';
import { cn } from '@/lib/utils';
import AppLayout from '@/Layouts/AppLayout';
import type { SaleRow, Statement } from '@/types';

interface Customer {
    id: number;
    name: string;
    mobile_number: string;
    notes: string | null;
    /** Positive = عليه, negative = له (kg). */
    flour_balance_kg: string;
    /** Positive = عليه, negative = له (₪). */
    money_balance: string;
}

interface Props {
    customer: Customer;
    flourExchangeFee: string;
    range: Range;
    moneyStatement: Statement;
    flourStatement: Statement;
    sales: SaleRow[];
    banks: Bank[];
    canAdjust: boolean;
    canExport: boolean;
}

interface Bank {
    id: number;
    name: string;
}

type Open = 'deposit' | 'bread' | 'payment' | 'adjustment' | null;

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
            title="إيداع طحين"
            description="تُسجَّل الكمية «له» في كشف الطحين، وتُخصم أولًا مما عليه إن وُجد"
            submitLabel="تسجيل الإيداع"
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
    const after = Number(customer.flour_balance_kg) + (Number(form.data.kg_amount) || 0);

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="تسليم خبز مقابل الطحين"
            description="تُسجَّل الكمية «عليه» في كشف الطحين، ورسم التبديل «عليه» في الكشف المالي"
            submitLabel="تسجيل التسليم"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field id="bread_kg" label="الكمية" hint={`الرصيد الحالي: ${formatFlourBalance(customer.flour_balance_kg)}${form.data.kg_amount ? ` · بعد التسليم: ${formatFlourBalance(after)}` : ''}`} error={form.errors.kg_amount}>
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

function BankSelect({ id, label, banks, value, onChange }: { id: string; label: string; banks: Bank[]; value: string; onChange: (v: string) => void }) {
    return (
        <Select value={value} onValueChange={onChange} dir="rtl">
            <SelectTrigger id={id} aria-label={label} className="w-full min-w-0 data-[size=default]:h-11">
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                {banks.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                        {b.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function PaymentDialog({ customer, open, onOpenChange, unpaidSales, banks }: DialogProps & { unpaidSales: SaleRow[]; banks: Bank[] }) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({
        amount: '',
        sale_id: '',
        payment_date: today,
        notes: '',
        method: 'cash' as 'cash' | 'transfer',
        from_bank_id: '',
        to_bank_id: '',
        transfer_reference: '',
        submission_id: newSubmissionId(),
    });
    const form = useForm(blank());
    const submit = () => {
        form.transform((data) => ({ ...data, sale_id: data.sale_id || null }));
        form.post(route('panel.customers.payments.store', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });
    };
    const sale = unpaidSales.find((s) => String(s.id) === form.data.sale_id);
    const owed = Number(customer.money_balance);
    const fill = sale ? sale.remaining_amount : owed > 0 ? customer.money_balance : null;

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="تسجيل دفعة"
            description="تُسجَّل «له» في الكشف المالي. يجوز أن تزيد عمّا عليه فيصبح الرصيد له"
            submitLabel="تسجيل الدفعة"
            processing={form.processing}
            onSubmit={submit}
        >
            {unpaidSales.length > 0 && (
                <Field id="payment_sale" label="الدفعة عن" error={form.errors.sale_id}>
                    <Select value={form.data.sale_id || 'account'} onValueChange={(v) => form.setData('sale_id', v === 'account' ? '' : v)} dir="rtl">
                        <SelectTrigger id="payment_sale" className="w-full data-[size=default]:h-11" aria-describedby="payment_sale-error">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="account">دفعة على الحساب</SelectItem>
                            {unpaidSales.map((s) => (
                                <SelectItem key={s.id} value={String(s.id)}>
                                    بيع {formatDate(s.sale_date)} · متبقٍ {formatMoney(s.remaining_amount)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            )}
            <Field id="payment_amount" label="المبلغ" hint={`الرصيد المالي الحالي: ${formatMoney(customer.money_balance)}`} error={form.errors.amount}>
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
                    {fill && (
                        <Button type="button" variant="outline" className="h-11" onClick={() => form.setData('amount', fill)}>
                            {sale ? 'متبقي البيع' : 'كامل المستحق'}
                        </Button>
                    )}
                </div>
            </Field>
            <Choice
                id="payment_method"
                label="طريقة الدفع"
                value={form.data.method}
                options={[
                    ['cash', 'نقدًا'],
                    ['transfer', 'تحويل'],
                ]}
                onChange={(v) => form.setData('method', v)}
                error={form.errors.method}
            />
            {form.data.method === 'transfer' &&
                (banks.length === 0 ? (
                    <p className="rounded-xl bg-secondary px-4 py-3 text-sm text-secondary-foreground">
                        لا توجد بنوك أو محافظ في القائمة.{' '}
                        <Link href={route('panel.banks.index')} className="font-medium underline underline-offset-4">
                            أضفها من صفحة البنوك
                        </Link>
                    </p>
                ) : (
                    <>
                        <Field id="payment_from_bank" label="التحويل (من ← إلى)" error={form.errors.from_bank_id || form.errors.to_bank_id}>
                            <div className="flex items-center gap-2" aria-describedby="payment_from_bank-error">
                                <BankSelect id="payment_from_bank" label="من" banks={banks} value={form.data.from_bank_id} onChange={(v) => form.setData('from_bank_id', v)} />
                                <span aria-hidden className="text-muted-foreground">
                                    ←
                                </span>
                                <BankSelect id="payment_to_bank" label="إلى" banks={banks} value={form.data.to_bank_id} onChange={(v) => form.setData('to_bank_id', v)} />
                            </div>
                        </Field>
                        <Field id="payment_reference" label="رقم الحوالة (اختياري)" error={form.errors.transfer_reference}>
                            <Input
                                id="payment_reference"
                                dir="ltr"
                                maxLength={100}
                                className="h-11 text-end"
                                value={form.data.transfer_reference}
                                onChange={(e) => form.setData('transfer_reference', e.target.value)}
                                aria-invalid={!!form.errors.transfer_reference}
                                aria-describedby="payment_reference-error"
                            />
                        </Field>
                    </>
                ))}
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

function Choice<T extends string>({ id, label, value, options, onChange, error }: { id: string; label: string; value: T; options: [T, string][]; onChange: (v: T) => void; error?: string }) {
    return (
        <Field id={id} label={label} error={error}>
            <div id={id} role="group" aria-label={label} className="grid grid-cols-2 gap-2">
                {options.map(([v, text]) => (
                    <Button key={v} type="button" variant={value === v ? 'default' : 'outline'} className="h-11" aria-pressed={value === v} onClick={() => onChange(v)}>
                        {text}
                    </Button>
                ))}
            </div>
        </Field>
    );
}

function AdjustmentDialog({ customer, open, onOpenChange }: DialogProps) {
    const { currentDate: today } = usePage().props;
    const blank = () => ({
        ledger: 'money' as Ledger,
        direction: 'debit' as 'debit' | 'credit',
        amount: '',
        reason: '',
        corrects_number: '',
        movement_date: today,
        submission_id: newSubmissionId(),
    });
    const form = useForm(blank());
    const submit = () => {
        form.transform((data) => ({ ...data, corrects_number: data.corrects_number || null }));
        form.post(route('panel.customers.adjustments.store', customer.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(blank());
                onOpenChange(false);
            },
        });
    };
    const AmountInput = form.data.ledger === 'money' ? MoneyInput : KgInput;

    return (
        <FormDialog
            open={open}
            onOpenChange={closeHandler(form, onOpenChange)}
            title="قيد تسوية"
            description="الطريقة الوحيدة لتصحيح رصيد. يظهر سطرًا مستقلًا في الكشف ولا يُحذف ولا يُعدَّل"
            submitLabel="تسجيل القيد"
            processing={form.processing}
            onSubmit={submit}
        >
            <Choice
                id="adjustment_ledger"
                label="الكشف"
                value={form.data.ledger}
                options={[
                    ['money', 'الحساب المالي'],
                    ['flour', 'الطحين'],
                ]}
                onChange={(v) => form.setData('ledger', v)}
                error={form.errors.ledger}
            />
            <Choice
                id="adjustment_direction"
                label="الاتجاه"
                value={form.data.direction}
                options={[
                    ['debit', 'عليه'],
                    ['credit', 'له'],
                ]}
                onChange={(v) => form.setData('direction', v)}
                error={form.errors.direction}
            />
            <Field id="adjustment_amount" label={form.data.ledger === 'money' ? 'المبلغ' : 'الكمية'} error={form.errors.amount}>
                <AmountInput
                    id="adjustment_amount"
                    min="0.01"
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                    aria-invalid={!!form.errors.amount}
                    aria-describedby="adjustment_amount-error"
                />
            </Field>
            <Field id="adjustment_reason" label="السبب" hint="يظهر في البيان: «تسوية: السبب»" error={form.errors.reason}>
                <Input
                    id="adjustment_reason"
                    className="h-11"
                    required
                    maxLength={200}
                    value={form.data.reason}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    aria-invalid={!!form.errors.reason}
                    aria-describedby="adjustment_reason-error"
                />
            </Field>
            <div className="grid grid-cols-2 gap-3">
                <Field id="adjustment_corrects" label="يصحّح الحركة (اختياري)" error={form.errors.corrects_number}>
                    <Input
                        id="adjustment_corrects"
                        type="number"
                        inputMode="numeric"
                        min="1"
                        dir="ltr"
                        className="h-11 text-end"
                        value={form.data.corrects_number}
                        onChange={(e) => form.setData('corrects_number', e.target.value)}
                        aria-invalid={!!form.errors.corrects_number}
                        aria-describedby="adjustment_corrects-error"
                    />
                </Field>
                <Field id="adjustment_date" label="التاريخ" error={form.errors.movement_date}>
                    <Input
                        id="adjustment_date"
                        type="date"
                        max={today}
                        className="h-11"
                        value={form.data.movement_date}
                        onChange={(e) => form.setData('movement_date', e.target.value)}
                        aria-invalid={!!form.errors.movement_date}
                        aria-describedby="adjustment_date-error"
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

function Stat({ label, children, className }: { label: string; children: ReactNode; className?: string }) {
    return (
        <Card className={cn('gap-1 p-5', className)}>
            <span className="text-sm text-muted-foreground">{label}</span>
            {children}
        </Card>
    );
}

export default function Show({ customer, flourExchangeFee, range, moneyStatement, flourStatement, sales, banks, canAdjust, canExport }: Props) {
    const { currentDate } = usePage().props;
    const [open, setOpen] = useState<Open>(null);
    const dialog = (name: Exclude<Open, null>) => ({ customer, open: open === name, onOpenChange: (o: boolean) => setOpen(o ? name : null) });
    const unpaidSales = sales.filter((s) => s.payment_status === 'unpaid');

    return (
        <AppLayout title={customer.name}>
            <div className="grid gap-6">
                <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                    <Button className="h-11" onClick={() => setOpen('bread')}>
                        <Receipt />
                        خبز مقابل الطحين
                    </Button>
                    <Button variant="secondary" className="h-11" onClick={() => setOpen('deposit')}>
                        <Wheat />
                        إيداع طحين
                    </Button>
                    <Button variant="secondary" className="h-11" onClick={() => setOpen('payment')}>
                        <HandCoins />
                        تسجيل دفعة
                    </Button>
                    {canAdjust && (
                        <Button variant="outline" className="h-11" onClick={() => setOpen('adjustment')}>
                            <Scale />
                            قيد تسوية
                        </Button>
                    )}
                    <Button variant="outline" asChild className={cn('h-11 sm:ms-auto', canAdjust && 'col-span-2 sm:col-span-1')}>
                        <Link href={route('panel.customers.edit', customer.id)}>
                            <Pencil />
                            تعديل
                        </Link>
                    </Button>
                </div>

                <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    <Stat label="رصيد الطحين">
                        <FlourBalance kg={customer.flour_balance_kg} className="text-2xl font-semibold sm:text-3xl" />
                    </Stat>
                    <Stat label="الرصيد المالي">
                        <MoneyBalance amount={customer.money_balance} className="text-start text-2xl font-semibold sm:text-3xl" />
                        <span className="text-xs text-muted-foreground">{Number(customer.money_balance) > 0 ? 'عليه' : Number(customer.money_balance) < 0 ? 'له' : 'لا شيء'}</span>
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

                <Tabs defaultValue="money" dir="rtl" className="gap-3">
                    <TabsList className="w-full sm:w-auto group-data-horizontal/tabs:h-11">
                        <TabsTrigger value="money" className="px-4">
                            الحساب المالي
                        </TabsTrigger>
                        <TabsTrigger value="flour" className="px-4">
                            الطحين
                        </TabsTrigger>
                        <TabsTrigger value="sales" className="px-4">
                            المبيعات ({sales.length})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="money" className="grid gap-3">
                        <RangePicker range={range} today={currentDate} customerId={customer.id} />
                        <StatementView ledger="money" statement={moneyStatement} range={range} exportUrl={canExport ? (format) => route('panel.customers.statement.export', { customer: customer.id, ledger: 'money', format, from: range.from ?? '', to: range.to ?? '' }) : undefined} />
                    </TabsContent>
                    <TabsContent value="flour" className="grid gap-3">
                        <RangePicker range={range} today={currentDate} customerId={customer.id} />
                        <StatementView ledger="flour" statement={flourStatement} range={range} exportUrl={canExport ? (format) => route('panel.customers.statement.export', { customer: customer.id, ledger: 'flour', format, from: range.from ?? '', to: range.to ?? '' }) : undefined} />
                    </TabsContent>
                    <TabsContent value="sales">
                        {sales.length ? <SaleList sales={sales} showType /> : <EmptyState icon={Receipt} title="لا يوجد سجل مبيعات" />}
                    </TabsContent>
                </Tabs>
            </div>

            <DepositDialog {...dialog('deposit')} />
            <BreadDialog {...dialog('bread')} fee={flourExchangeFee} />
            <PaymentDialog {...dialog('payment')} unpaidSales={unpaidSales} banks={banks} />
            {canAdjust && <AdjustmentDialog {...dialog('adjustment')} />}
        </AppLayout>
    );
}
