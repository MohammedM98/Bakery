import { Link } from '@inertiajs/react';
import { CircleAlert, Plus, ReceiptText } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import { Bar, BarChart, XAxis } from 'recharts';
import { EmptyState } from '@/components/empty-state';
import { KgText } from '@/components/kg-text';
import { MoneyText } from '@/components/money-text';
import { CashSaleDialog, MarkPaidButton, SaleStatusBadge } from '@/components/sales';
import { Button } from '@/components/ui/button';
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, type ChartConfig } from '@/components/ui/chart';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatKg, formatMoney } from '@/lib/format';
import AppLayout from '@/Layouts/AppLayout';
import type { SaleRow } from '@/types';

interface Day {
    date: string;
    kg: number;
    takings: number;
}

interface Props {
    today: { kg: number; takings: number; unpaid: number; salesCount: number; unpaidCount: number };
    last7Days: Day[];
    unpaidSales: SaleRow[];
    recentSales: SaleRow[];
    pricePerKg: string;
}

const chartConfig = { kg: { label: 'الكمية', color: 'var(--sidebar-primary)' } } satisfies ChartConfig;

/** "26/11" for the chart axis. */
const shortDate = (date: string) => formatDate(date).slice(0, 5);

function DayTooltip({ active, payload }: { active?: boolean; payload?: { payload: Day }[] }) {
    if (!active || !payload?.length) return null;
    const day = payload[0].payload;
    return (
        <div className="grid gap-1 rounded-lg border bg-popover px-3 py-2 text-xs text-popover-foreground shadow-md">
            <span className="font-medium tabular-nums">{formatDate(day.date)}</span>
            <span>{formatKg(day.kg)}</span>
            <span className="text-muted-foreground">مقبوضات {formatMoney(day.takings)}</span>
        </div>
    );
}

/** A label and its figure: no icon, the number is the content. */
function SmallStat({ label, children }: { label: string; children: ReactNode }) {
    return (
        <Card className="gap-1 p-4 sm:p-5">
            <p className="text-xs text-muted-foreground sm:text-sm">{label}</p>
            <p className="text-lg font-semibold tabular-nums sm:text-xl">{children}</p>
        </Card>
    );
}

function SaleLine({ sale, children }: { sale: SaleRow; children: ReactNode }) {
    return (
        <li className="flex items-center justify-between gap-3 py-3">
            <div className="min-w-0">
                <p className="truncate font-medium">{sale.buyer}</p>
                <p className="text-sm text-muted-foreground tabular-nums">
                    {formatDate(sale.sale_date)} · {sale.sale_type === 'flour_exchange' ? 'مقابل طحين' : 'بيع نقدي'} · {formatKg(sale.kg_amount)}
                </p>
            </div>
            {children}
        </li>
    );
}

export default function Dashboard({ today, last7Days, unpaidSales, recentSales, pricePerKg }: Props) {
    const [recording, setRecording] = useState(false);
    const hasWeek = last7Days.some((d) => d.kg > 0);

    return (
        <AppLayout title="لوحة التحكم">
            <div className="grid gap-6">
                <Button className="h-12 w-full text-base sm:w-auto sm:justify-self-end sm:px-6" onClick={() => setRecording(true)}>
                    <Plus />
                    تسجيل عملية بيع
                </Button>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="border-0 bg-sidebar p-6 text-sidebar-foreground ring-0 lg:col-span-2">
                        <div>
                            <p className="text-sm text-sidebar-foreground/70">خبز مباع اليوم</p>
                            <KgText kg={today.kg} className="text-4xl font-semibold text-sidebar-accent-foreground" />
                        </div>
                        {hasWeek ? (
                            <ChartContainer config={chartConfig} className="aspect-auto h-32 w-full [&_.recharts-cartesian-axis-tick_text]:fill-sidebar-foreground/70 [&_.recharts-rectangle.recharts-tooltip-cursor]:fill-sidebar-accent">
                                <BarChart data={last7Days} margin={{ top: 8, left: 0, right: 0, bottom: 0 }}>
                                    <XAxis dataKey="date" tickFormatter={shortDate} tickLine={false} axisLine={false} interval={0} reversed />
                                    <ChartTooltip content={<DayTooltip />} />
                                    <Bar dataKey="kg" fill="var(--color-kg)" radius={[4, 4, 0, 0]} maxBarSize={36} />
                                </BarChart>
                            </ChartContainer>
                        ) : (
                            <p className="text-sm text-sidebar-foreground/70">لا توجد بيانات لآخر 7 أيام بعد</p>
                        )}
                    </Card>

                    <Card className="justify-between p-6">
                        <div>
                            <p className="text-sm text-muted-foreground">غير مدفوع اليوم</p>
                            <MoneyText amount={today.unpaid} className={today.unpaid > 0 ? 'text-3xl font-semibold text-destructive' : 'text-3xl font-semibold'} />
                        </div>
                        <Button variant="outline" asChild className="h-11">
                            <Link href={route('panel.sales.index', { status: 'unpaid' })}>عرض العمليات غير المدفوعة</Link>
                        </Button>
                    </Card>
                </div>

                <div className="grid grid-cols-3 gap-3 sm:gap-4">
                    <SmallStat label="مقبوضات اليوم">
                        <MoneyText amount={today.takings} />
                    </SmallStat>
                    <SmallStat label="عدد العمليات اليوم">
                        {today.salesCount}
                    </SmallStat>
                    <SmallStat label="عمليات غير مدفوعة اليوم">
                        {today.unpaidCount}
                    </SmallStat>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>عمليات غير مدفوعة</CardTitle>
                            <CardAction>
                                <Button variant="link" asChild className="h-auto p-0">
                                    <Link href={route('panel.sales.index', { status: 'unpaid' })}>عرض الكل</Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            {unpaidSales.length ? (
                                <ul className="divide-y">
                                    {unpaidSales.map((sale) => (
                                        <SaleLine key={sale.id} sale={sale}>
                                            <div className="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
                                                <MoneyText amount={sale.remaining_amount} className="font-semibold text-destructive" />
                                                <MarkPaidButton sale={sale} className="h-9" />
                                            </div>
                                        </SaleLine>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState icon={CircleAlert} title="لا توجد عمليات غير مدفوعة" />
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>آخر العمليات</CardTitle>
                            <CardAction>
                                <Button variant="link" asChild className="h-auto p-0">
                                    <Link href={route('panel.sales.index')}>عرض الكل</Link>
                                </Button>
                            </CardAction>
                        </CardHeader>
                        <CardContent>
                            {recentSales.length ? (
                                <ul className="divide-y">
                                    {recentSales.map((sale) => (
                                        <SaleLine key={sale.id} sale={sale}>
                                            <div className="flex shrink-0 flex-col items-end gap-1">
                                                <MoneyText amount={sale.total_amount} className="font-semibold" />
                                                <SaleStatusBadge sale={sale} />
                                            </div>
                                        </SaleLine>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState
                                    icon={ReceiptText}
                                    title="لا توجد عمليات بعد"
                                    action={
                                        <Button className="h-11" onClick={() => setRecording(true)}>
                                            تسجيل عملية بيع
                                        </Button>
                                    }
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card className="py-0">
                    <CardHeader className="pt-6">
                        <CardTitle>آخر 7 أيام</CardTitle>
                    </CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="ps-6">التاريخ</TableHead>
                                <TableHead className="text-end">الكيلوغرامات المباعة</TableHead>
                                <TableHead className="pe-6 text-end">المقبوضات</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {[...last7Days].reverse().map((day) => (
                                <TableRow key={day.date}>
                                    <TableCell className="ps-6 tabular-nums">{formatDate(day.date)}</TableCell>
                                    <TableCell className="text-end">
                                        <KgText kg={day.kg} />
                                    </TableCell>
                                    <TableCell className="pe-6 text-end">
                                        <MoneyText amount={day.takings} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
            </div>

            <CashSaleDialog open={recording} onOpenChange={setRecording} pricePerKg={pricePerKg} />
        </AppLayout>
    );
}
