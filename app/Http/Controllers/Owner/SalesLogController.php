<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\SalesLogRequest;
use App\Models\Movement;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The whole bakery's activity for a day, week, month or year: summary cards and the movements
 * table. Every figure comes from the same movements the customer statements are built from.
 */
class SalesLogController extends Controller
{
    const PERIODS = ['day' => 'يوم', 'week' => 'أسبوع', 'month' => 'شهر', 'year' => 'سنة'];

    /** Table filter → movement types. */
    const FILTERS = [
        'sale' => [Movement::TYPE_SALE],
        'flour_sale' => [Movement::TYPE_FLOUR_WITHDRAWAL, Movement::TYPE_EXCHANGE_FEE],
        'payment' => [Movement::TYPE_PAYMENT],
        'deposit' => [Movement::TYPE_DEPOSIT],
        'adjustment' => [Movement::TYPE_ADJUSTMENT],
        'opening_balance' => [Movement::TYPE_OPENING_BALANCE],
    ];

    const TYPE_LABELS = [
        Movement::TYPE_SALE => 'بيع عادي',
        Movement::TYPE_FLOUR_WITHDRAWAL => 'بيع مقابل طحين',
        Movement::TYPE_EXCHANGE_FEE => 'رسم تبديل',
        Movement::TYPE_PAYMENT => 'دفعة',
        Movement::TYPE_DEPOSIT => 'إيداع طحين',
        Movement::TYPE_ADJUSTMENT => 'تسوية',
        Movement::TYPE_OPENING_BALANCE => 'رصيد افتتاحي',
    ];

    private const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    private const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    public function __invoke(SalesLogRequest $request): Response
    {
        $bakery = $request->user()->bakery;
        $filters = [
            'period' => $request->validated('period') ?? 'day',
            'date' => $request->validated('date') ?? now()->toDateString(),
            'type' => $request->validated('type') ?? '',
            'customer' => $request->validated('customer') ?? '',
            'recorded_by' => $request->validated('recorded_by') ?? '',
        ];
        [$start, $end] = $this->bounds($filters['period'], Carbon::parse($filters['date']));
        $from = $start->toDateString();
        $to = $end->toDateString();

        $inPeriod = fn ($query, string $column = 'movement_date') => $query
            ->whereDate($column, '>=', $from)->whereDate($column, '<=', $to);

        $totals = $inPeriod($bakery->movements())
            ->whereIn('type', [Movement::TYPE_PAYMENT, Movement::TYPE_DEPOSIT, Movement::TYPE_FLOUR_WITHDRAWAL])
            ->selectRaw('type, from_bank IS NULL as cash, SUM(amount) as total')
            ->groupBy('type', 'cash')
            ->get();
        $sum = fn (string $type, ?bool $cash = null) => $totals
            ->where('type', $type)
            ->when($cash !== null, fn ($rows) => $rows->filter(fn ($r) => (bool) $r->cash === $cash))
            ->sum(fn ($r) => Movement::cents($r->total));
        $bread = $inPeriod($bakery->sales(), 'sale_date')
            ->selectRaw('sale_type, SUM(kg_amount) as kg')->groupBy('sale_type')->pluck('kg', 'sale_type')
            ->map(fn ($kg) => Movement::cents($kg));

        $movements = $inPeriod($bakery->movements())
            ->with(['customer:id,name', 'sale:id,buyer_name'])
            ->when($filters['type'], fn ($q) => $q->whereIn('type', self::FILTERS[$filters['type']]))
            ->when($filters['customer'] === 'walk_in', fn ($q) => $q->whereNull('customer_id'))
            ->when(ctype_digit($filters['customer']), fn ($q) => $q->where('customer_id', $filters['customer']))
            ->when($filters['recorded_by'], fn ($q) => $q->where('recorded_by_name', $filters['recorded_by']))
            ->orderByDesc('movement_date')->orderByDesc('number')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Movement $m) => [
                'number' => $m->number,
                'type' => $m->type,
                'type_label' => self::TYPE_LABELS[$m->type] ?? $m->type,
                'date' => $m->movement_date->toDateString(),
                'time' => $m->created_at?->format('H:i'),
                'customer_id' => $m->customer_id,
                'customer' => $m->customer?->name ?? $m->sale?->buyer_name,
                'ledger' => $m->ledger,
                'amount' => $m->amount,
                'description' => $m->description,
                'recorded_by' => $m->recorded_by_name,
            ]);

        $step = fn (int $n) => $this->shift($filters['period'], $start, $n)->toDateString();

        return Inertia::render('Owner/SalesLog', [
            'filters' => $filters,
            'range' => ['from' => $from, 'to' => $to, 'label' => $this->label($filters['period'], $start, $end)],
            'previousDate' => $step(-1),
            // No browsing into the future: nothing can be recorded there.
            'nextDate' => $end->lt(today()) ? $step(1) : null,
            'summary' => [
                'bread_kg' => Movement::decimal($bread->sum()),
                'bread_regular_kg' => Movement::decimal($bread[Sale::TYPE_CASH] ?? 0),
                'bread_flour_kg' => Movement::decimal($bread[Sale::TYPE_FLOUR_EXCHANGE] ?? 0),
                'money_received' => Movement::decimal(-$sum(Movement::TYPE_PAYMENT)),
                'money_cash' => Movement::decimal(-$sum(Movement::TYPE_PAYMENT, true)),
                'money_transfer' => Movement::decimal(-$sum(Movement::TYPE_PAYMENT, false)),
                'flour_spent_kg' => Movement::decimal($sum(Movement::TYPE_FLOUR_WITHDRAWAL)),
                'flour_received_kg' => Movement::decimal(-$sum(Movement::TYPE_DEPOSIT)),
            ],
            'movements' => $movements,
            'options' => [
                'customers' => $bakery->customers()->orderBy('name')->get(['id', 'name']),
                'recordedBy' => $bakery->movements()->whereNotNull('recorded_by_name')
                    ->distinct()->orderBy('recorded_by_name')->pluck('recorded_by_name'),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function bounds(string $period, Carbon $date): array
    {
        return match ($period) {
            // The week runs Saturday to Friday, as in Palestine.
            'week' => [$date->copy()->startOfWeek(Carbon::SATURDAY), $date->copy()->endOfWeek(Carbon::FRIDAY)],
            'month' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            'year' => [$date->copy()->startOfYear(), $date->copy()->endOfYear()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }

    private function shift(string $period, Carbon $start, int $n): Carbon
    {
        return match ($period) {
            'week' => $start->copy()->addWeeks($n),
            'month' => $start->copy()->addMonthsNoOverflow($n),
            'year' => $start->copy()->addYears($n),
            default => $start->copy()->addDays($n),
        };
    }

    private function label(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'week' => $start->format('d/m').' – '.$end->format('d/m/Y'),
            'month' => self::MONTHS[$start->month - 1].' '.$start->year,
            'year' => (string) $start->year,
            default => self::DAYS[$start->dayOfWeek].' '.$start->format('d/m/Y'),
        };
    }
}
