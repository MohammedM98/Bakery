import { Link, router } from '@inertiajs/react';
import { ArrowLeftRight, Banknote, BookOpen, History, Plus, Scale, ShoppingBag, Wheat, type LucideIcon } from 'lucide-react';
import { useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { Area, AreaChart, Bar, BarChart, Cell, LabelList, PolarAngleAxis, RadialBar, RadialBarChart, ReferenceDot, ReferenceLine, XAxis, YAxis } from 'recharts';
import { DepositDialog } from '@/components/deposit-dialog';
import { EmptyState } from '@/components/empty-state';
import { PeriodPills, type Period } from '@/components/period-pills';
import { CashSaleDialog } from '@/components/sales';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ChartContainer, ChartTooltip, ChartTooltipContent, type ChartConfig } from '@/components/ui/chart';
import { formatFlourBalance, formatKg, formatMoney } from '@/lib/format';
import { useCan } from '@/lib/can';
import { cn } from '@/lib/utils';
import AppLayout from '@/Layouts/AppLayout';

type MovementType = 'sale' | 'flour_withdrawal' | 'payment' | 'deposit' | 'adjustment' | 'opening_balance';

interface Recent {
    number: number;
    type: MovementType;
    who: string;
    detail: string;
    ledger: 'money' | 'flour';
    /** Signed: positive = عليه, negative = له. */
    amount: string;
}

interface Bucket {
    label: string;
    kg: number;
    received: number;
}

interface Props {
    period: Period;
    rangeLabel: string;
    bread: { kg: string; regular: string; flour: string };
    takings: { received: string; cash: string; transfer: string; unpaid: string; paidPercent: number | null };
    recent: Recent[];
    chart: Bucket[];
    pricePerKg: string;
    customers: { id: number; name: string }[];
}

const words: Record<Period, { bread: string; chip: string; range: string }> = {
    day: { bread: 'خبز مباع اليوم', chip: 'اليوم', range: 'آخر 7 أيام' },
    week: { bread: 'خبز مباع هذا الأسبوع', chip: 'هذا الأسبوع', range: 'آخر 7 أسابيع' },
    month: { bread: 'خبز مباع هذا الشهر', chip: 'هذا الشهر', range: 'آخر 7 أشهر' },
    year: { bread: 'خبز مباع هذه السنة', chip: 'هذه السنة', range: 'آخر 7 سنوات' },
};

const icons: Record<MovementType, [LucideIcon, string]> = {
    sale: [ShoppingBag, 'bg-accent text-slate'],
    flour_withdrawal: [ArrowLeftRight, 'bg-wheat/20 text-warning'],
    payment: [Banknote, 'bg-success/12 text-success'],
    deposit: [Wheat, 'bg-wheat/20 text-warning'],
    adjustment: [Scale, 'bg-accent text-slate'],
    opening_balance: [BookOpen, 'bg-accent text-slate'],
};

const number = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });

function Chip({ children, className }: { children: ReactNode; className?: string }) {
    return <span className={cn('rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground', className)}>{children}</span>;
}

function Head({ title, children }: { title: string; children?: ReactNode }) {
    return (
        <div className="mb-4 flex items-center justify-between gap-3">
            <h2 className="text-[17px] font-semibold">{title}</h2>
            {children}
        </div>
    );
}

function Amount({ row }: { row: Recent }) {
    const n = Number(row.amount);
    if (row.type === 'payment') return <span className="text-success tabular-nums">{formatMoney(-n, { plus: true })}</span>;
    if (row.type === 'sale' || row.type === 'flour_withdrawal') return <span className="tabular-nums">{formatMoney(n)}</span>;
    if (row.ledger === 'flour') return <span className="tabular-nums">{formatFlourBalance(n)}</span>;
    return (
        <span className="whitespace-nowrap tabular-nums">
            {n > 0 ? 'عليه' : 'له'} {formatMoney(Math.abs(n))}
        </span>
    );
}

/** The small dark tag above the current value in both charts, sized to its text and kept inside the chart. */
function Tag({ x, y, text, center = false }: { x: number; y: number; text: string; center?: boolean }) {
    const ref = useRef<SVGTextElement>(null);
    const [w, setW] = useState(text.length * 7 + 18);
    useLayoutEffect(() => {
        const measure = () => ref.current && setW(ref.current.getComputedTextLength() + 18);
        measure();
        // Measure again once the web font has loaded: the fallback font has other widths.
        document.fonts?.ready.then(measure);
    }, [text]);
    const left = Math.max(0, center ? x - w / 2 : x - 4);
    return (
        <g>
            <rect x={left} y={y - 30} width={w} height={22} rx={7} className="fill-foreground" />
            <text ref={ref} x={left + w / 2} y={y - 15} textAnchor="middle" className="fill-background text-xs font-semibold">
                {text}
            </text>
        </g>
    );
}

const lineConfig = { kg: { label: 'الخبز', color: 'var(--chart-1)' } } satisfies ChartConfig;
const barConfig = { received: { label: 'الدخل', color: 'var(--chart-1)' } } satisfies ChartConfig;
const ringConfig = { paid: { label: 'مدفوع', color: 'var(--chart-1)' } } satisfies ChartConfig;
// Room for the tag above the highest point.
const chartMargin = { top: 34, right: 18, left: 18, bottom: 0 };

export default function Dashboard({ period, rangeLabel, bread, takings, recent, chart, pricePerKg, customers }: Props) {
    const can = useCan();
    const [open, setOpen] = useState<'sale' | 'deposit' | null>(null);
    const current = chart[chart.length - 1];
    const w = words[period];
    const change = (next: Period) =>
        router.get(route('panel.dashboard'), next === 'day' ? {} : { period: next }, { preserveState: true, preserveScroll: true, replace: true });
    const stack: [string, string, string, string][] = [
        ['نقدًا', takings.cash, 'bg-primary', ''],
        ['تحويل', takings.transfer, 'bg-slate', ''],
        ['غير مدفوع', takings.unpaid, 'bg-chart-3', 'text-destructive'],
    ];

    return (
        <AppLayout title="لوحة التحكم" toolbar={<PeriodPills value={period} onChange={change} />}>
            <div className="grid gap-3.5 md:grid-cols-2 md:gap-5 xl:grid-cols-[1.15fr_1fr_340px]">
                {/* The page's one dark card. */}
                <Card className="relative gap-0 border-0 bg-hero p-[22px] text-hero-foreground ring-0 after:pointer-events-none after:absolute after:start-[-30px] after:-bottom-10 after:size-[220px] after:rounded-full after:border-[28px] after:border-hero-foreground/[0.035] xl:col-start-1 xl:row-start-1">
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-[15px] text-hero-muted">{w.bread}</span>
                        <Chip className="bg-hero-foreground/8 text-hero-muted tabular-nums">{rangeLabel}</Chip>
                    </div>
                    <p className="mt-1.5 text-5xl leading-tight font-semibold md:text-[56px]">
                        <span className="tabular-nums">{number.format(Number(bread.kg))}</span>
                        <small className="ms-1.5 text-[22px] font-medium opacity-90">كغ</small>
                    </p>
                    <div className="mt-1.5 flex flex-wrap gap-x-[22px] gap-y-1 text-sm text-hero-muted">
                        <span>
                            بيع عادي <b className="font-semibold tabular-nums">{number.format(Number(bread.regular))}</b> كغ
                        </span>
                        <span>
                            مقابل طحين <b className="font-semibold tabular-nums">{number.format(Number(bread.flour))}</b> كغ
                        </span>
                    </div>
                    {(can('record-sale') || (can('record-deposit') && customers.length > 0)) && (
                        <div className="relative z-10 mt-auto flex flex-wrap gap-2.5 pt-[22px]">
                            {can('record-sale') && (
                                <Button className="h-11 rounded-[14px] px-[18px] text-[15px] font-semibold" onClick={() => setOpen('sale')}>
                                    <Plus />
                                    تسجيل عملية بيع
                                </Button>
                            )}
                            {can('record-deposit') && customers.length > 0 && (
                                <Button
                                    variant="ghost"
                                    className="h-11 rounded-[14px] bg-hero-foreground/9 px-[18px] text-[15px] text-hero-foreground hover:bg-hero-foreground/15 hover:text-hero-foreground"
                                    onClick={() => setOpen('deposit')}
                                >
                                    إيداع طحين
                                </Button>
                            )}
                        </div>
                    )}
                </Card>

                <Card className="gap-0 p-[22px] xl:col-start-2 xl:row-start-1">
                    <Head title="آخر الحركات">
                        {can('view-sales-log') && (
                            <Link href={route('panel.sales-log', { period })} className="text-sm text-muted-foreground underline-offset-4 hover:underline">
                                عرض الكل
                            </Link>
                        )}
                    </Head>
                    {recent.length ? (
                        <ul className="divide-y">
                            {recent.map((row) => {
                                const [Icon, tone] = icons[row.type];
                                return (
                                    <li key={row.number} className="flex items-center gap-3 py-[9px]">
                                        <span className={cn('grid size-[38px] shrink-0 place-items-center rounded-xl', tone)}>
                                            <Icon className="size-[18px]" aria-hidden />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <b className="block truncate text-[15px] font-medium">{row.who}</b>
                                            <span className="block truncate text-[13px] text-muted-foreground">{row.detail}</span>
                                        </span>
                                        <span className="text-[15px] font-medium">
                                            <Amount row={row} />
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    ) : (
                        <EmptyState icon={History} title="لا توجد حركات في هذه الفترة" />
                    )}
                </Card>

                <Card className="gap-0 p-[22px] md:col-span-2 xl:col-span-1 xl:col-start-3 xl:row-span-2 xl:row-start-1">
                    <Head title="المقبوضات">
                        <Chip>{w.chip}</Chip>
                    </Head>
                    <div className="relative mx-auto my-1.5 size-[180px] md:size-[210px]">
                        <ChartContainer config={ringConfig} className="aspect-square size-full" aria-hidden>
                            <RadialBarChart data={[{ paid: takings.paidPercent ?? 0 }]} startAngle={90} endAngle={-270} innerRadius="80%" outerRadius="100%">
                                <PolarAngleAxis type="number" domain={[0, 100]} tick={false} />
                                <RadialBar dataKey="paid" fill="var(--color-paid)" background cornerRadius={12} isAnimationActive={false} />
                            </RadialBarChart>
                        </ChartContainer>
                        <div className="absolute inset-0 grid place-content-center text-center">
                            <span className="text-[40px] leading-none font-semibold tabular-nums">{takings.paidPercent === null ? '—' : `${takings.paidPercent}%`}</span>
                            <span className="mt-1.5 text-[13px] text-muted-foreground">من المبيعات مدفوع</span>
                        </div>
                    </div>
                    <div className="mt-3.5 mb-1 text-center">
                        <b className="block text-[32px] font-semibold tabular-nums">
                            {formatMoney(takings.received)}
                        </b>
                        <span className="text-sm text-muted-foreground">دخل فعلًا</span>
                    </div>
                    <div className="my-5 flex h-2.5 gap-[3px] overflow-hidden rounded-full bg-chart-3" aria-hidden>
                        {stack.map(([label, value, color]) => (
                            <i key={label} className={cn('block h-full rounded-full', color)} style={{ flex: Number(value) }} />
                        ))}
                    </div>
                    <ul className="grid gap-3">
                        {stack.map(([label, value, color, text]) => (
                            <li key={label} className="flex items-center gap-2.5 text-sm">
                                <span className={cn('size-2.5 shrink-0 rounded', color)} />
                                {label}
                                <span className={cn('ms-auto font-medium tabular-nums', text)}>
                                    {formatMoney(value)}
                                </span>
                            </li>
                        ))}
                    </ul>
                    {can('view-sales') && (
                        <div className="mt-auto pt-[18px]">
                            <Button variant="outline" asChild className="h-11 w-full rounded-[14px] bg-muted text-[15px]">
                                <Link href={route('panel.sales.index', { status: 'unpaid' })}>عرض العمليات غير المدفوعة</Link>
                            </Button>
                        </div>
                    )}
                </Card>

                <Card className="gap-0 p-[22px] xl:col-start-1 xl:row-start-2">
                    <Head title="الخبز المباع">
                        <Chip>{w.range}</Chip>
                    </Head>
                    <ChartContainer config={lineConfig} className="aspect-auto h-[190px] w-full" role="img" aria-label={`الخبز المباع، ${w.range}: ${chart.map((b) => `${b.label} ${formatKg(b.kg)}`).join('، ')}`}>
                        <AreaChart data={chart} margin={chartMargin}>
                            <defs>
                                <linearGradient id="kgFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0" stopColor="var(--color-kg)" stopOpacity={0.14} />
                                    <stop offset="1" stopColor="var(--color-kg)" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            {/* Right to left: the latest bucket on the left. */}
                            <XAxis dataKey="label" reversed tickLine={false} axisLine={false} interval={0} tickMargin={8} fontSize={11} />
                            <YAxis hide domain={[(min: number) => min * 0.85, (max: number) => max * 1.1 || 1]} />
                            <ChartTooltip cursor={false} content={<ChartTooltipContent formatter={(v) => formatKg(Number(v))} hideIndicator />} />
                            <Area type="monotone" dataKey="kg" stroke="var(--color-kg)" strokeWidth={3} fill="url(#kgFill)" isAnimationActive={false} />
                            <ReferenceLine x={current.label} stroke="var(--color-kg)" strokeDasharray="3 4" strokeOpacity={0.6} />
                            <ReferenceDot
                                x={current.label}
                                y={current.kg}
                                r={6}
                                fill="var(--card)"
                                stroke="var(--color-kg)"
                                strokeWidth={3}
                                label={({ viewBox }: { viewBox?: { x?: number; y?: number } }) => <Tag x={viewBox?.x ?? 0} y={viewBox?.y ?? 0} text={formatKg(current.kg)} />}
                            />
                        </AreaChart>
                    </ChartContainer>
                </Card>

                <Card className="gap-0 p-[22px] xl:col-start-2 xl:row-start-2">
                    <Head title="الدخل المالي">
                        <Chip>{w.range}</Chip>
                    </Head>
                    <ChartContainer config={barConfig} className="aspect-auto h-[190px] w-full" role="img" aria-label={`الدخل المالي، ${w.range}: ${chart.map((b) => `${b.label} ${formatMoney(b.received)}`).join('، ')}`}>
                        <BarChart data={chart} margin={chartMargin}>
                            <XAxis dataKey="label" reversed tickLine={false} axisLine={false} interval={0} tickMargin={8} fontSize={11} />
                            <YAxis hide domain={[0, (max: number) => max * 1.05 || 1]} />
                            <ChartTooltip cursor={false} content={<ChartTooltipContent formatter={(v) => formatMoney(Number(v))} hideIndicator />} />
                            <Bar dataKey="received" radius={9} maxBarSize={30} minPointSize={4} isAnimationActive={false}>
                                {chart.map((b, i) => (
                                    <Cell key={b.label} fill={i === chart.length - 1 ? 'var(--color-received)' : 'var(--chart-3)'} />
                                ))}
                                <LabelList
                                    dataKey="received"
                                    content={({ x, y, width, index }) =>
                                        index === chart.length - 1 ? <Tag x={Number(x) + Number(width) / 2} y={Number(y)} text={formatMoney(current.received)} center /> : null
                                    }
                                />
                            </Bar>
                        </BarChart>
                    </ChartContainer>
                </Card>
            </div>

            {can('record-sale') && <CashSaleDialog open={open === 'sale'} onOpenChange={(o) => setOpen(o ? 'sale' : null)} pricePerKg={pricePerKg} />}
            {can('record-deposit') && <DepositDialog customers={customers} open={open === 'deposit'} onOpenChange={(o) => setOpen(o ? 'deposit' : null)} />}
        </AppLayout>
    );
}
