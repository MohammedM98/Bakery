<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Component;

class DashboardUnpaidSales extends Component
{
    public ?int $confirmingPaymentSaleId = null;

    public function confirmMarkPaid(int $saleId): void
    {
        $this->confirmingPaymentSaleId = $saleId;
        $this->dispatch('open-modal', 'dashboard-confirm-payment');
    }

    public function markPaid(int $saleId): void
    {
        $sale = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->where('id', $saleId)
            ->firstOrFail();

        abort_if($sale->isPaid(), 409);

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        $this->confirmingPaymentSaleId = null;
        $this->dispatch('toast', message: 'تم تأكيد استلام الدفع لهذه العملية بمبلغ '.money($sale->total_amount).'. لا يمكن التراجع عن هذا الإجراء.');
        $this->dispatch('close-modal', 'dashboard-confirm-payment');

        $this->dispatch('sale-marked-paid');
    }

    protected function findConfirmingSale(): ?Sale
    {
        if (! $this->confirmingPaymentSaleId) {
            return null;
        }

        return Sale::where('bakery_id', auth()->user()->bakery_id)->find($this->confirmingPaymentSaleId);
    }

    public function render()
    {
        $unpaidSales = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->with('customer')
            ->latest('sale_date')
            ->take(10)
            ->get();

        return view('livewire.dashboard-unpaid-sales', [
            'unpaidSales' => $unpaidSales,
            'confirmingSale' => $this->findConfirmingSale(),
        ]);
    }
}
