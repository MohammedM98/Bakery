import { router } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Field } from '@/components/form-field';
import { FlourBalance, KgText } from '@/components/kg-text';
import { MoneyBalance, MoneyText } from '@/components/money-text';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Statement, StatementRow } from '@/types';

export type Ledger = 'money' | 'flour';

export interface Range {
    from: string | null;
    to: string | null;
}

function Balance({ ledger, value, className }: { ledger: Ledger; value: string; className?: string }) {
    return ledger === 'money' ? <MoneyBalance amount={value} className={className} /> : <FlourBalance kg={value} className={className} />;
}

function Amount({ ledger, value, className }: { ledger: Ledger; value: string | null; className?: string }) {
    if (value === null) return <span className="text-muted-foreground">—</span>;
    return ledger === 'money' ? <MoneyText amount={value} className={className} /> : <KgText kg={value} className={className} />;
}

function Transfer({ row }: { row: StatementRow }) {
    if (!row.transfer) return <span className="text-muted-foreground">—</span>;
    return (
        <span>
            {row.transfer}
            {row.transfer_reference && <span className="block text-xs text-muted-foreground">حوالة {row.transfer_reference}</span>}
        </span>
    );
}

function iso(date: Date) {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** "From / to" dates with the shortcuts. Reloads only the statements. */
export function RangePicker({ range, today, customerId }: { range: Range; today: string; customerId: number }) {
    const [from, setFrom] = useState(range.from ?? '');
    const [to, setTo] = useState(range.to ?? '');
    const [y, m] = today.split('-').map(Number);
    const shortcuts: [string, Range][] = [
        ['هذا الشهر', { from: iso(new Date(y, m - 1, 1)), to: today }],
        ['آخر 3 أشهر', { from: iso(new Date(y, m - 3, 1)), to: today }],
        ['السنة', { from: `${y}-01-01`, to: today }],
        ['الكل', { from: null, to: null }],
    ];
    const visit = (next: Range) => {
        setFrom(next.from ?? '');
        setTo(next.to ?? '');
        router.get(
            route('panel.customers.show', customerId),
            { from: next.from ?? '', to: next.to ?? '' },
            { preserveState: true, preserveScroll: true, replace: true, only: ['range', 'moneyStatement', 'flourStatement', 'flash'] },
        );
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        visit({ from: from || null, to: to || null });
    };
    const active = (r: Range) => r.from === range.from && r.to === range.to;

    return (
        <Card className="gap-4 p-4">
            <div className="flex flex-wrap gap-2" role="group" aria-label="الفترة">
                {shortcuts.map(([label, r]) => (
                    <Button key={label} type="button" size="sm" variant={active(r) ? 'default' : 'outline'} aria-pressed={active(r)} className="h-9" onClick={() => visit(r)}>
                        {label}
                    </Button>
                ))}
            </div>
            <form onSubmit={submit} className="grid grid-cols-2 items-end gap-3 sm:flex">
                <Field id="range_from" label="من">
                    <Input id="range_from" type="date" className="h-11" value={from} max={to || undefined} onChange={(e) => setFrom(e.target.value)} />
                </Field>
                <Field id="range_to" label="إلى">
                    <Input id="range_to" type="date" className="h-11" value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} />
                </Field>
                <Button type="submit" variant="secondary" className="col-span-2 h-11">
                    عرض الكشف
                </Button>
            </form>
        </Card>
    );
}

/**
 * One ledger's statement: "رصيد سابق", each movement with the running balance, the closing balance.
 * Cards on the phone, a table from lg up (eight columns need the width).
 */
export function StatementView({ ledger, statement, range }: { ledger: Ledger; statement: Statement; range: Range }) {
    const unit = ledger === 'flour' ? ' (كغ)' : '';
    const period = range.from ? `قبل ${formatDate(range.from)}` : 'لا حركات قبل الفترة';

    return (
        <div className="grid gap-3">
            <div className="grid grid-cols-2 gap-3">
                <Card className="gap-1 p-4">
                    <span className="text-sm text-muted-foreground">رصيد سابق</span>
                    <Balance ledger={ledger} value={statement.opening} className="text-lg font-semibold" />
                    <span className="text-xs text-muted-foreground">{period}</span>
                </Card>
                <Card className="gap-1 p-4">
                    <span className="text-sm text-muted-foreground">الرصيد الختامي</span>
                    <Balance ledger={ledger} value={statement.closing} className="text-lg font-semibold" />
                    <span className="text-xs text-muted-foreground">{range.to ? `حتى ${formatDate(range.to)}` : 'حتى آخر حركة'}</span>
                </Card>
            </div>

            {statement.rows.length === 0 ? (
                <EmptyState icon={ScrollText} title="لا توجد حركات في هذه الفترة" description="اختر فترة أخرى أو «الكل»" />
            ) : (
                <>
                    <ol className="grid gap-3 lg:hidden">
                        {statement.rows.map((row) => (
                            <li key={row.number}>
                                <Card className="gap-2 p-4">
                                    <div className="flex items-center justify-between gap-3 text-sm text-muted-foreground">
                                        <span className="tabular-nums">
                                            #{row.number} · {formatDate(row.date)}
                                        </span>
                                        <span>{row.recorded_by}</span>
                                    </div>
                                    <p className="font-medium">{row.description}</p>
                                    {row.note && <p className="text-sm whitespace-pre-line text-muted-foreground">{row.note}</p>}
                                    {ledger === 'money' && row.transfer && (
                                        <p className="text-sm">
                                            <Transfer row={row} />
                                        </p>
                                    )}
                                    <div className="flex items-end justify-between gap-3 border-t pt-2">
                                        <div className="text-sm">
                                            <span className="text-muted-foreground">{row.debit ? 'عليه ' : 'له '}</span>
                                            <Amount ledger={ledger} value={row.debit ?? row.credit} className={cn('font-semibold', row.debit ? 'text-destructive' : 'text-success')} />
                                        </div>
                                        <div className="text-end text-sm">
                                            <span className="block text-xs text-muted-foreground">الرصيد</span>
                                            <Balance ledger={ledger} value={row.balance} className="font-semibold" />
                                        </div>
                                    </div>
                                </Card>
                            </li>
                        ))}
                    </ol>

                    <Card className="hidden py-0 lg:block">
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="ps-6">رقم الحركة</TableHead>
                                        <TableHead>التاريخ</TableHead>
                                        <TableHead>البيان</TableHead>
                                        {ledger === 'money' && <TableHead>التحويل</TableHead>}
                                        <TableHead className="text-end">عليه{unit}</TableHead>
                                        <TableHead className="text-end">له{unit}</TableHead>
                                        <TableHead className="text-end">الرصيد</TableHead>
                                        <TableHead className="pe-6">سجّلها</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow className="bg-muted/40 hover:bg-muted/40">
                                        <TableCell className="ps-6" />
                                        <TableCell className="tabular-nums">{range.from ? formatDate(range.from) : ''}</TableCell>
                                        <TableCell className="font-medium" colSpan={ledger === 'money' ? 4 : 3}>
                                            رصيد سابق
                                        </TableCell>
                                        <TableCell className="text-end">
                                            <Balance ledger={ledger} value={statement.opening} className="font-medium" />
                                        </TableCell>
                                        <TableCell className="pe-6" />
                                    </TableRow>
                                    {statement.rows.map((row) => (
                                        <TableRow key={row.number}>
                                            <TableCell className="ps-6 tabular-nums">{row.number}</TableCell>
                                            <TableCell className="tabular-nums">{formatDate(row.date)}</TableCell>
                                            <TableCell className="min-w-48 whitespace-normal">
                                                {row.description}
                                                {row.note && <span className="block text-xs text-muted-foreground">{row.note}</span>}
                                            </TableCell>
                                            {ledger === 'money' && (
                                                <TableCell className="whitespace-normal">
                                                    <Transfer row={row} />
                                                </TableCell>
                                            )}
                                            <TableCell className="text-end">
                                                {row.debit && <Amount ledger={ledger} value={row.debit} className="text-destructive" />}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                {row.credit && <Amount ledger={ledger} value={row.credit} className="text-success" />}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <Balance ledger={ledger} value={row.balance} className="font-medium" />
                                            </TableCell>
                                            <TableCell className="pe-6 text-muted-foreground">{row.recorded_by}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                                <TableFooter>
                                    <TableRow>
                                        <TableCell className="ps-6" />
                                        <TableCell className="tabular-nums">{range.to ? formatDate(range.to) : ''}</TableCell>
                                        <TableCell className="font-semibold" colSpan={ledger === 'money' ? 4 : 3}>
                                            الرصيد الختامي
                                        </TableCell>
                                        <TableCell className="text-end">
                                            <Balance ledger={ledger} value={statement.closing} className="font-semibold" />
                                        </TableCell>
                                        <TableCell className="pe-6" />
                                    </TableRow>
                                </TableFooter>
                            </Table>
                        </div>
                    </Card>
                </>
            )}
        </div>
    );
}
