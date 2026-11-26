<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Movement;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $bakery = $request->user()->bakery;
        $today = now()->startOfDay();

        $todaySales = $bakery->sales()->whereDate('sale_date', $today)->get();
        $todayUnpaid = $todaySales->where('payment_status', Sale::STATUS_UNPAID);

        $byDay = $bakery->sales()
            ->whereDate('sale_date', '>=', $today->copy()->subDays(6))
            ->get(['sale_date', 'kg_amount', 'total_amount', 'payment_status'])
            ->groupBy(fn (Sale $sale) => $sale->sale_date->toDateString());

        // Takings = money actually received (payment movements), as in the sales log: a sale settled
        // from the customer's earlier credit brings in nothing, a payment on account does.
        $takingsCents = $bakery->movements()
            ->where('type', Movement::TYPE_PAYMENT)
            ->whereDate('movement_date', '>=', $today->copy()->subDays(6))
            ->get(['movement_date', 'amount'])
            ->groupBy(fn (Movement $m) => $m->movement_date->toDateString())
            ->map(fn ($rows) => -$rows->sum(fn (Movement $m) => Movement::cents($m->amount)));

        // All seven days, zeros included, so the chart keeps its shape.
        $last7Days = collect(range(6, 0))->map(function (int $daysAgo) use ($today, $byDay, $takingsCents) {
            $date = $today->copy()->subDays($daysAgo)->toDateString();
            $sales = $byDay->get($date, collect());

            return [
                'date' => $date,
                'kg' => round((float) $sales->sum('kg_amount'), 2),
                'takings' => $takingsCents->get($date, 0) / 100,
            ];
        });

        $rows = fn ($query) => $query->with('customer:id,name')->latest('sale_date')->latest('id')->take(10)->get()
            ->map(fn (Sale $sale) => $sale->toRow());

        return Inertia::render('Owner/Dashboard', [
            'today' => [
                'kg' => round((float) $todaySales->sum('kg_amount'), 2),
                'takings' => $last7Days->last()['takings'],
                'unpaid' => round($todayUnpaid->sum(fn (Sale $sale) => $sale->remainingAmount()), 2),
                'salesCount' => $todaySales->count(),
                'unpaidCount' => $todayUnpaid->count(),
            ],
            'last7Days' => $last7Days,
            'unpaidSales' => $rows($bakery->sales()->where('payment_status', Sale::STATUS_UNPAID)),
            'recentSales' => $rows($bakery->sales()),
            'pricePerKg' => $bakery->regular_price_per_kg,
        ]);
    }
}
