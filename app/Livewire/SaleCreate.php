<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SaleCreate extends Component
{
    public bool $isModal = false;

    #[Validate('required|in:cash,flour_exchange')]
    public string $sale_type = 'cash';

    #[Validate('nullable|exists:customers,id')]
    public ?int $customer_id = null;

    #[Validate('required|numeric|min:0.01')]
    public string $kg_amount = '';

    #[Validate('required|in:paid,unpaid')]
    public string $payment_status = 'paid';

    #[Validate('required|date')]
    public string $sale_date = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = '';

    public function mount(?int $customerId = null): void
    {
        $this->sale_date = now()->toDateString();
        $this->customer_id = $customerId ?? (request()->integer('customer_id') ?: null);
    }

    #[Computed]
    public function bakery()
    {
        return auth()->user()->bakery;
    }

    #[Computed]
    public function customers()
    {
        return Customer::where('bakery_id', $this->bakery->id)->orderBy('name')->get();
    }

    #[Computed]
    public function pricePerKg()
    {
        return $this->sale_type === Sale::TYPE_FLOUR_EXCHANGE
            ? $this->bakery->flour_exchange_fee_per_kg
            : $this->bakery->regular_price_per_kg;
    }

    #[Computed]
    public function total()
    {
        return round((float) ($this->kg_amount ?: 0) * $this->pricePerKg, 2);
    }

    public function save()
    {
        $this->validate();

        if ($this->sale_type === Sale::TYPE_FLOUR_EXCHANGE && empty($this->customer_id)) {
            $this->addError('customer_id', 'يجب اختيار العميل عند التسليم مقابل رصيد القمح.');

            return;
        }

        $customer = null;
        if (! empty($this->customer_id)) {
            $customer = Customer::where('bakery_id', $this->bakery->id)->findOrFail($this->customer_id);
        }

        if ($this->sale_type === Sale::TYPE_FLOUR_EXCHANGE && $customer->flour_balance_kg < $this->kg_amount) {
            $this->addError('kg_amount', 'رصيد القمح لدى العميل غير كافٍ لهذه الكمية.');

            return;
        }

        $pricePerKg = $this->pricePerKg;
        $bakery = $this->bakery;

        DB::transaction(function () use ($customer, $pricePerKg, $bakery) {
            Sale::create([
                'bakery_id' => $bakery->id,
                'customer_id' => $customer?->id,
                'sale_type' => $this->sale_type,
                'kg_amount' => $this->kg_amount,
                'price_per_kg' => $pricePerKg,
                'total_amount' => round($this->kg_amount * $pricePerKg, 2),
                'payment_status' => $this->payment_status,
                'paid_at' => $this->payment_status === Sale::STATUS_PAID ? now() : null,
                'sale_date' => $this->sale_date,
                'notes' => $this->notes ?: null,
                'created_by' => auth()->id(),
            ]);

            if ($this->sale_type === Sale::TYPE_FLOUR_EXCHANGE) {
                $customer->decrement('flour_balance_kg', $this->kg_amount);
            }
        });

        if ($this->isModal) {
            $this->reset('sale_type', 'customer_id', 'kg_amount', 'payment_status', 'notes');
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
