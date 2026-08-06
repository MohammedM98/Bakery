<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SalesIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $date = '';

    public ?int $confirmingPaymentSaleId = null;

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'date');
    }

    public function confirmMarkPaid(int $saleId): void
    {
        $this->confirmingPaymentSaleId = $saleId;
        $this->dispatch('open-modal', 'confirm-payment');
    }

    public function markPaid(int $saleId): void
    {
        $sale = $this->findOwnedSale($saleId);

        abort_if($sale->isPaid(), 409);

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        $this->confirmingPaymentSaleId = null;
        $this->dispatch('toast', message: 'تم تأكيد استلام الدفع لهذه العملية بمبلغ '.money($sale->total_amount).'. لا يمكن التراجع عن هذا الإجراء.');
        $this->dispatch('close-modal', 'confirm-payment');
    }

    protected function findConfirmingSale(): ?Sale
    {
        if (! $this->confirmingPaymentSaleId) {
            return null;
        }

        return Sale::where('bakery_id', auth()->user()->bakery_id)->find($this->confirmingPaymentSaleId);
    }

    #[On('sale-saved')]
    public function refreshAfterModal(): void
    {
        // No-op: handling the event triggers a fresh render(), enough to
        // show a sale created via the modal.
    }

    protected function findOwnedSale(int $saleId): Sale
    {
        return Sale::where('bakery_id', auth()->user()->bakery_id)->findOrFail($saleId);
    }

    public function render()
    {
        $sales = Sale::where('bakery_id', auth()->user()->bakery_id)
            ->with('customer')
            ->when($this->status, fn ($query) => $query->where('payment_status', $this->status))
            ->when($this->date, fn ($query) => $query->whereDate('sale_date', $this->date))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(20);

        return view('livewire.sales-index', [
            'sales' => $sales,
            'confirmingSale' => $this->findConfirmingSale(),
        ]);
    }
}
