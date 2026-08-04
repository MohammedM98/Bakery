<?php

namespace App\Livewire;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;
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

    public ?string $message = null;

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

    public function markPaid(int $saleId): void
    {
        $sale = $this->findOwnedSale($saleId);

        $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_at' => now()]);

        $this->message = "تم تأكيد استلام الدفع لهذه العملية بمبلغ {$sale->total_amount}.";
    }

    public function markUnpaid(int $saleId): void
    {
        $sale = $this->findOwnedSale($saleId);

        $sale->update(['payment_status' => Sale::STATUS_UNPAID, 'paid_at' => null]);

        $this->message = 'تم تحويل حالة العملية إلى غير مدفوعة.';
    }

    public function delete(int $saleId): void
    {
        $sale = $this->findOwnedSale($saleId);

        DB::transaction(function () use ($sale) {
            if ($sale->isFlourExchange() && $sale->customer) {
                $sale->customer->increment('flour_balance_kg', $sale->kg_amount);
            }

            $sale->delete();
        });

        $this->message = 'تم حذف عملية البيع.';
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

        return view('livewire.sales-index', compact('sales'));
    }
}
