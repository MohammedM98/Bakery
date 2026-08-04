<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Component;

class DashboardUnpaidSales extends Component
{
    public ?string $message = null;

    public function markPaid(int $saleId): void
    {
        $sale = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->where('id', $saleId)
            ->firstOrFail();

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        $this->message = "تم تأكيد استلام الدفع لهذه العملية بمبلغ {$sale->total_amount}.";

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
