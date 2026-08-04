<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Attributes\On;
use Livewire\Component;

class DashboardPage extends Component
{
    #[On('sale-marked-paid')]
    #[On('sale-saved')]
    public function refresh(): void
    {
        // No-op: handling the event alone triggers a fresh render(), which
        // is enough to pull the updated "today" totals after the child
        // dashboard-unpaid-sales widget marks a sale as paid in place, or
        // after a new sale is recorded via the create modal.
    }

    public function render()
    {
        $bakery = auth()->user()->bakery;
        $today = now()->startOfDay();

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

        $recentSales = Sale::where('bakery_id', $bakery->id)
            ->with('customer')
            ->latest('sale_date')
            ->latest('id')
            ->take(10)
            ->get();

        return view('livewire.dashboard-page', compact(
            'bakery', 'todayKg', 'todayTakings', 'todayUnpaid', 'todaySalesCount', 'todayUnpaidCount',
            'last7Days', 'recentSales'
        ));
    }
}
