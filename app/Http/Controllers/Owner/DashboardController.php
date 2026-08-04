<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the bakery owner's control panel: daily sales, daily takings and bread-by-kilo totals.
     */
    public function __invoke(Request $request): View
    {
        $bakery = $request->user()->bakery;
        $today = Carbon::today();

        $todaySales = Sale::where('bakery_id', $bakery->id)
            ->whereDate('sale_date', $today)
            ->get();

        $todayKg = $todaySales->sum('kg_amount');
        $todayTakings = $todaySales->where('payment_status', Sale::STATUS_PAID)->sum('total_amount');
        $todayUnpaid = $todaySales->where('payment_status', Sale::STATUS_UNPAID)->sum('total_amount');
        $todaySalesCount = $todaySales->count();
        $todayUnpaidCount = $todaySales->where('payment_status', Sale::STATUS_UNPAID)->count();

        $last7Days = Sale::where('bakery_id', $bakery->id)
            ->whereDate('sale_date', '>=', $today->copy()->subDays(6))
            ->selectRaw('sale_date, SUM(kg_amount) as kg_total, SUM(CASE WHEN payment_status = ? THEN total_amount ELSE 0 END) as takings', [Sale::STATUS_PAID])
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        $unpaidSales = Sale::where('bakery_id', $bakery->id)
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->with('customer')
            ->latest('sale_date')
            ->take(10)
            ->get();

        $recentSales = Sale::where('bakery_id', $bakery->id)
            ->with('customer')
            ->latest('sale_date')
            ->latest('id')
            ->take(10)
            ->get();

        return view('owner.dashboard', compact(
            'bakery', 'todayKg', 'todayTakings', 'todayUnpaid', 'todaySalesCount', 'todayUnpaidCount',
            'last7Days', 'unpaidSales', 'recentSales'
        ));
    }
}
