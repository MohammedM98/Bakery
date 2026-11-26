<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
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

        // All seven days, zeros included, so the chart keeps its shape.
        $last7Days = collect(range(6, 0))->map(function (int $daysAgo) use ($today, $byDay) {
            $date = $today->copy()->subDays($daysAgo)->toDateString();
            $sales = $byDay->get($date, collect());

            return [
                'date' => $date,
                'kg' => round((float) $sales->sum('kg_amount'), 2),
                'takings' => round((float) $sales->where('payment_status', Sale::STATUS_PAID)->sum('total_amount'), 2),
            ];
        });

        $rows = fn ($query) => $query->with('customer:id,name')->latest('sale_date')->latest('id')->take(10)->get()
            ->map(fn (Sale $sale) => $sale->toRow());

        return Inertia::render('Owner/Dashboard', [
            'today' => [
                'kg' => round((float) $todaySales->sum('kg_amount'), 2),
                'takings' => round((float) $todaySales->where('payment_status', Sale::STATUS_PAID)->sum('total_amount'), 2),
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
