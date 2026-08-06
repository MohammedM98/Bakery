<?php

namespace App\Livewire;

use App\Models\Sale;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SaleCreate extends Component
{
    public bool $isModal = false;

    #[Validate('nullable|string|max:255')]
    public ?string $buyer_name = '';

    #[Validate('nullable|string|max:50')]
    public ?string $buyer_mobile = '';

    #[Validate('required|numeric|min:0.01')]
    public string $kg_amount = '';

    #[Validate('required|in:paid,unpaid')]
    public string $payment_status = 'paid';

    #[Validate('required|date')]
    public string $sale_date = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = '';

    public function mount(): void
    {
        $this->sale_date = now()->toDateString();
    }

    #[Computed]
    public function bakery()
    {
        return auth()->user()->bakery;
    }

    #[Computed]
    public function pricePerKg()
    {
        return $this->bakery->regular_price_per_kg;
    }

    #[Computed]
    public function total()
    {
        return round((float) ($this->kg_amount ?: 0) * $this->pricePerKg, 2);
    }

    public function save()
    {
        $this->validate();

        $pricePerKg = $this->pricePerKg;
        $bakery = $this->bakery;

        Sale::create([
            'bakery_id' => $bakery->id,
            'buyer_name' => $this->buyer_name ?: null,
            'buyer_mobile' => $this->buyer_mobile ?: null,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => $this->kg_amount,
            'price_per_kg' => $pricePerKg,
            'total_amount' => round($this->kg_amount * $pricePerKg, 2),
            'payment_status' => $this->payment_status,
            'paid_at' => $this->payment_status === Sale::STATUS_PAID ? now() : null,
            'sale_date' => $this->sale_date,
            'notes' => $this->notes ?: null,
            'created_by' => auth()->id(),
        ]);

        if ($this->isModal) {
            $this->reset('buyer_name', 'buyer_mobile', 'kg_amount', 'payment_status', 'notes');
            $this->sale_date = now()->toDateString();
            $this->dispatch('sale-saved');
            $this->dispatch('close-modal', 'sale-create');

            return;
        }

        session()->flash('status', 'تم تسجيل عملية البيع بنجاح.');

        return redirect()->route('panel.sales.index');
    }

    public function render()
    {
        return view('livewire.sale-create');
    }
}
