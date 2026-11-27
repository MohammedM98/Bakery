<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The bakery's day, week, month or year at a glance. Figures come from the same tables and rules as
 * the sales log: bread from `sales.kg_amount`, money received from payment movements.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $period = $request->validate([
            'period' => ['nullable', Rule::in(array_keys(SalesLogController::PERIODS))],
        ])['period'] ?? 'day';
        $bakery = $request->user()->bakery;
        [$start, $end] = SalesLogController::bounds($period, today());
        $from = $start->toDateString();
        $to = $end->toDateString();

        $sales = $bakery->sales()->whereDate('sale_date', '>=', $from)->whereDate('sale_date', '<=', $to)
            ->get(['sale_type', 'kg_amount', 'total_amount', 'paid_amount']);
        $payments = $bakery->movements()->where('type', Movement::TYPE_PAYMENT)
            ->whereDate('movement_date', '>=', $from)->whereDate('movement_date', '<=', $to)
            ->get(['amount', 'from_bank']);

        $kg = fn ($rows) => $rows->sum(fn (Sale $s) => Movement::cents($s->kg_amount));
        $received = fn ($rows) => -$rows->sum(fn (Movement $m) => Movement::cents($m->amount));
        $totalCents = $sales->sum(fn (Sale $s) => Movement::cents($s->total_amount));
        $paidCents = $sales->sum(fn (Sale $s) => min(Movement::cents($s->paid_amount), Movement::cents($s->total_amount)));

        return Inertia::render('Owner/Dashboard', [
            'period' => $period,
            'rangeLabel' => SalesLogController::label($period, $start, $end),
            'bread' => [
                'kg' => Movement::decimal($kg($sales)),
                'regular' => Movement::decimal($kg($sales->where('sale_type', Sale::TYPE_CASH))),
                'flour' => Movement::decimal($kg($sales->where('sale_type', Sale::TYPE_FLOUR_EXCHANGE))),
            ],
            'takings' => [
                'received' => Movement::decimal($received($payments)),
                'cash' => Movement::decimal($received($payments->whereNull('from_bank'))),
                'transfer' => Movement::decimal($received($payments->whereNotNull('from_bank'))),
                'unpaid' => Movement::decimal($totalCents - $paidCents),
                // Share of the period's sales value already paid; null when nothing was sold.
                'paidPercent' => $totalCents > 0 ? (int) floor($paidCents * 100 / $totalCents) : null,
            ],
            'recent' => $this->recent($bakery, $from, $to),
            'chart' => $this->chart($bakery, $period, $start),
            'pricePerKg' => $bakery->regular_price_per_kg,
            // For the flour-deposit dialog; deposits elsewhere also need the customers list.
            'customers' => Gate::allows('record-deposit') && Gate::allows('view-customers') ? $bakery->customers()->orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    /** The period's last movements, one line per operation (a flour sale's fee shows on its withdrawal line). */
    private function recent($bakery, string $from, string $to): array
    {
        return $bakery->movements()
            ->whereDate('movement_date', '>=', $from)->whereDate('movement_date', '<=', $to)
            ->where('type', '!=', Movement::TYPE_EXCHANGE_FEE)
            ->with(['customer:id,name', 'sale:id,buyer_name,kg_amount,total_amount'])
            ->orderByDesc('number')->take(5)->get()
            ->map(fn (Movement $m) => [
                'number' => $m->number,
                'type' => $m->type,
                'who' => $m->customer?->name ?? $m->sale?->buyer_name ?? 'مشترٍ عابر',
                'detail' => match ($m->type) {
                    Movement::TYPE_SALE => 'بيع عادي، '.kg($m->sale?->kg_amount ?? 0),
                    Movement::TYPE_FLOUR_WITHDRAWAL => 'خبز مقابل طحين، '.kg($m->amount),
                    Movement::TYPE_PAYMENT => 'دفعة، '.($m->from_bank ? 'تحويل '.$m->from_bank : 'نقدًا'),
                    Movement::TYPE_DEPOSIT => 'إيداع طحين',
                    Movement::TYPE_ADJUSTMENT => 'تسوية',
                    default => 'رصيد افتتاحي',
                },
                // The flour sale's line shows its fee, as the money the customer owes for it.
                'ledger' => $m->type === Movement::TYPE_FLOUR_WITHDRAWAL ? Movement::LEDGER_MONEY : $m->ledger,
                'amount' => $m->type === Movement::TYPE_FLOUR_WITHDRAWAL ? ($m->sale?->total_amount ?? '0.00') : $m->amount,
            ])->all();
    }

    /** Bread kg and money received for the current bucket and the six before it (days, weeks, months or years). */
    private function chart($bakery, string $period, Carbon $start): array
    {
        $starts = collect(range(-6, 0))->map(fn (int $n) => SalesLogController::shift($period, $start, $n));
        $from = $starts->first()->toDateString();
        // The bucket a date falls in: the last one starting on or before it.
        $bucket = fn (Carbon $date) => $starts->filter(fn (Carbon $s) => $s->lte($date))->keys()->last();

        $kg = $bakery->sales()->whereDate('sale_date', '>=', $from)->get(['sale_date', 'kg_amount'])
            ->groupBy(fn (Sale $s) => $bucket($s->sale_date))
            ->map(fn ($rows) => $rows->sum(fn (Sale $s) => Movement::cents($s->kg_amount)));
        $received = $bakery->movements()->where('type', Movement::TYPE_PAYMENT)->whereDate('movement_date', '>=', $from)
            ->get(['movement_date', 'amount'])
            ->groupBy(fn (Movement $m) => $bucket($m->movement_date))
            ->map(fn ($rows) => -$rows->sum(fn (Movement $m) => Movement::cents($m->amount)));

        return $starts->map(fn (Carbon $s, int $i) => [
            'label' => match ($period) {
                'month' => SalesLogController::MONTHS[$s->month - 1],
                'year' => (string) $s->year,
                default => $s->format('d/m'),
            },
            'kg' => $kg->get($i, 0) / 100,
            'received' => $received->get($i, 0) / 100,
        ])->all();
    }
}
