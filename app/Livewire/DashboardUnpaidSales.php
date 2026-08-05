<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Component;

class DashboardUnpaidSales extends Component
{
    public function markPaid(int $saleId): void
    {
        $sale = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->where('id', $saleId)
            ->firstOrFail();

        abort_if($sale->isPaid(), 409);

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        $this->dispatch('toast', message: 'تم تأكيد استلام الدفع لهذه العملية بمبلغ '.money($sale->total_amount).'. لا يمكن التراجع عن هذا الإجراء.');

        $this->dispatch('sale-marked-paid');
    }

    public function render()
    {
        $unpaidSales = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->with('customer')
            ->latest('sale_date')
            ->take(10)
            ->get();

        return view('livewire.dashboard-unpaid-sales', compact('unpaidSales'));
    }
}
