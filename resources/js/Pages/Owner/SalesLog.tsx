import { Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, History, SearchX } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Field } from '@/components/form-field';
import { KgText } from '@/components/kg-text';
import { MoneyText } from '@/components/money-text';
import { PeriodPills, type Period } from '@/components/period-pills';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatKg } from '@/lib/format';
import { cn } from '@/lib/utils';
import { useCan } from '@/lib/can';
import AppLayout from '@/Layouts/AppLayout';
import type { Paginated } from '@/types';

type TypeFilter = '' | 'sale' | 'flour_sale' | 'payment' | 'deposit' | 'adjustment' | 'opening_balance';

interface Filters {
    period: Period;
    date: string;
    type: TypeFilter;
    /** A customer id, "walk_in", or "" for all. */
    customer: string;
    recorded_by: string;
}

interface LogRow {
    number: number;
    type: string;
    type_label: string;
    date: string;
    time: string | null;
    customer_id: number | null;
    customer: string | null;
    ledger: 'money' | 'flour';
    /** Signed: positive = عليه, negative = له. */
    amount: string;
    description: string;
    recorded_by: string | null;
}

interface Props {
    filters: Filters;
    range: { from: string; to: string; label: string };
    previousDate: string;
    nextDate: string | null;
    summary: {
        bread_kg: string;
        bread_regular_kg: string;
        bread_flour_kg: string;
        money_received: string;
        money_cash: string;
        money_transfer: string;
        flour_spent_kg: string;
        flour_received_kg: string;
    };
    movements: Paginated<LogRow>;
    options: { customers: { id: number; name: string }[]; recordedBy: string[] };
}

const types: [TypeFilter, string][] = [
    ['sale', 'بيع عادي'],
    ['flour_sale', 'بيع مقابل طحين'],
    ['payment', 'دفعة'],
    ['deposit', 'إيداع طحين'],
    ['adjustment', 'تسوية'],
    ['opening_balance', 'رصيد افتتاحي'],
];

const ALL = 'all';

/** `lead` is the period's headline figure: the dark card, as on the dashboard. */
function SummaryCard({ label, children, parts, lead = false }: { label: string; children: ReactNode; parts?: [string, ReactNode][]; lead?: boolean }) {
    return (
        <Card className={cn('gap-3 p-4 sm:p-5', lead && 'border-0 bg-hero text-hero-foreground ring-0')}>
            <div className="grid gap-1">
                <p className={cn('text-sm', lead ? 'text-hero-muted' : 'text-muted-foreground')}>{label}</p>
                <p className={cn('text-2xl font-semibold tabular-nums', lead && 'text-3xl')}>{children}</p>
            </div>
            {parts && (
                <dl className={cn('grid gap-1 border-t pt-3 text-sm', lead && 'border-hero-foreground/10')}>
                    {parts.map(([name, value]) => (
                        <div key={name} className="flex items-center justify-between gap-2">
                            <dt className={lead ? 'text-hero-muted' : 'text-muted-foreground'}>{name}</dt>
                            <dd className="font-medium tabular-nums">{value}</dd>
                        </div>
                    ))}
                </dl>
            )}
        </Card>
    );
}

/** "عليه / له" with the quantity or amount, never a sign. */
function Amount({ row }: { row: LogRow }) {
    const n = Number(row.amount);
    const abs = Math.abs(n);
    return (
        <span className="whitespace-nowrap">
            <span className="text-muted-foreground">{n > 0 ? 'عليه ' : 'له '}</span>
            {row.ledger === 'money' ? <MoneyText amount={abs} className="font-medium" /> : <KgText kg={abs} className="font-medium" />}
        </span>
    );
}

function Who({ row }: { row: LogRow }) {
    const can = useCan();
    if (row.customer_id && !can('view-customers')) return <span className="font-medium">{row.customer}</span>;
    if (row.customer_id) {
        return (
            <Link href={route('panel.customers.show', row.customer_id)} className="font-medium underline-offset-4 hover:underline">
                {row.customer}
            </Link>
        );
    }
    return <span className="text-muted-foreground">{row.customer ? `${row.customer} (عابر)` : 'مشترٍ عابر'}</span>;
}

function FilterSelect({ id, label, value, onChange, items }: { id: string; label: string; value: string; onChange: (v: string) => void; items: [string, string][] }) {
    return (
        <Field id={id} label={label}>
            <Select value={value || ALL} onValueChange={(v) => onChange(v === ALL ? '' : v)} dir="rtl">
                <SelectTrigger id={id} className="w-full data-[size=default]:h-11">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={ALL}>الكل</SelectItem>
                    {items.map(([v, text]) => (
                        <SelectItem key={v} value={v}>
                            {text}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

export default function SalesLog({ filters, range, previousDate, nextDate, summary, movements, options }: Props) {
    const filtered = filters.type !== '' || filters.customer !== '' || filters.recorded_by !== '';
    const visit = (next: Partial<Filters>) =>
        router.get(
            route('panel.sales-log'),
            Object.fromEntries(Object.entries({ ...filters, ...next }).filter(([, v]) => v !== '')),
            { preserveState: true, preserveScroll: true, replace: true, only: ['filters', 'range', 'previousDate', 'nextDate', 'summary', 'movements', 'flash'] },
        );
    const clear = () => visit({ type: '', customer: '', recorded_by: '' });
    const when = (row: LogRow) => (filters.period === 'day' ? row.time : `${formatDate(row.date)}${row.time ? ` · ${row.time}` : ''}`);

    return (
        <AppLayout title="سجل المبيعات" toolbar={<PeriodPills value={filters.period} onChange={(period) => visit({ period })} />}>
            <div className="grid gap-6">
                <div className="flex items-center justify-between gap-2 sm:justify-start">
                    <Button variant="outline" size="icon" className="size-11" aria-label="الفترة السابقة" onClick={() => visit({ date: previousDate })}>
                        <ChevronRight />
                    </Button>
                    <p className="min-w-40 text-center font-semibold tabular-nums" aria-live="polite">
                        {range.label}
                    </p>
                    <Button variant="outline" size="icon" className="size-11" aria-label="الفترة التالية" disabled={!nextDate} onClick={() => nextDate && visit({ date: nextDate })}>
                        <ChevronLeft />
                    </Button>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-4">
                    <SummaryCard
                        lead
                        label="الخبز المباع"
                        parts={[
                            ['بيع عادي', formatKg(summary.bread_regular_kg)],
                            ['مقابل طحين', formatKg(summary.bread_flour_kg)],
                        ]}
                    >
                        {formatKg(summary.bread_kg)}
                    </SummaryCard>
                    <SummaryCard
                        label="الدخل المالي"
                        parts={[
                            ['نقدًا', <MoneyText key="cash" amount={summary.money_cash} />],
                            ['تحويل', <MoneyText key="transfer" amount={summary.money_transfer} />],
                        ]}
                    >
                        <MoneyText amount={summary.money_received} />
                    </SummaryCard>
                    <SummaryCard label="الطحين المصروف">{formatKg(summary.flour_spent_kg)}</SummaryCard>
                    <SummaryCard label="الطحين المستلَم">{formatKg(summary.flour_received_kg)}</SummaryCard>
                </div>

                <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-3 lg:flex lg:flex-wrap">
                    <div className="lg:w-48">
                        <FilterSelect id="log_type" label="نوع الحركة" value={filters.type} onChange={(v) => visit({ type: v as TypeFilter })} items={types} />
                    </div>
                    <div className="lg:w-56">
                        <FilterSelect
                            id="log_customer"
                            label="الزبون"
                            value={filters.customer}
                            onChange={(v) => visit({ customer: v })}
                            items={[['walk_in', 'مشترٍ عابر'], ...options.customers.map((c): [string, string] => [String(c.id), c.name])]}
                        />
                    </div>
                    <div className="lg:w-48">
                        <FilterSelect
                            id="log_recorded_by"
                            label="سجّلها"
                            value={filters.recorded_by}
                            onChange={(v) => visit({ recorded_by: v })}
                            items={options.recordedBy.map((name): [string, string] => [name, name])}
                        />
                    </div>
                    {filtered && (
                        <Button variant="ghost" className="h-11" onClick={clear}>
                            مسح التصفية
                        </Button>
                    )}
                </div>

                {movements.data.length === 0 ? (
                    filtered ? (
                        <EmptyState
                            icon={SearchX}
                            title="لا توجد حركات مطابقة للتصفية"
                            action={
                                <Button variant="outline" className="h-11" onClick={clear}>
                                    مسح التصفية
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState icon={History} title="لا توجد حركات في هذه الفترة" description="اختر فترة أخرى أو انتقل للفترة السابقة" />
                    )
                ) : (
                    <>
                        <ol className="grid gap-3 lg:hidden">
                            {movements.data.map((row) => (
                                <li key={row.number}>
                                    <Card className="gap-2 p-4">
                                        <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                                            <span className="tabular-nums">
                                                #{row.number} · {when(row)}
                                            </span>
                                            <span>{row.recorded_by}</span>
                                        </div>
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-medium">{row.type_label}</span>
                                            <Amount row={row} />
                                        </div>
                                        <p className="text-sm">
                                            <Who row={row} />
                                        </p>
                                        <p className="text-sm text-muted-foreground">{row.description}</p>
                                    </Card>
                                </li>
                            ))}
                        </ol>

                        <Card className="hidden py-0 lg:block">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-6">رقم الحركة</TableHead>
                                        <TableHead>{filters.period === 'day' ? 'الوقت' : 'التاريخ والوقت'}</TableHead>
                                        <TableHead>النوع</TableHead>
                                        <TableHead>الزبون</TableHead>
                                        <TableHead>البيان</TableHead>
                                        <TableHead className="text-end">الكمية أو المبلغ</TableHead>
                                        <TableHead className="pe-6">سجّلها</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {movements.data.map((row) => (
                                        <TableRow key={row.number}>
                                            <TableCell className="ps-6 tabular-nums">{row.number}</TableCell>
                                            <TableCell className="tabular-nums">{when(row)}</TableCell>
                                            <TableCell>{row.type_label}</TableCell>
                                            <TableCell>
                                                <Who row={row} />
                                            </TableCell>
                                            <TableCell className="min-w-48 whitespace-normal text-muted-foreground">{row.description}</TableCell>
                                            <TableCell className="text-end">
                                                <Amount row={row} />
                                            </TableCell>
                                            <TableCell className="pe-6 text-muted-foreground">{row.recorded_by}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </Card>
                        <p className="text-sm text-muted-foreground">
                            {movements.total} حركة في الفترة
                        </p>
                        <PaginationLinks page={movements} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
