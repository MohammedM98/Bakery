<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    public string $kg_amount = '';

    public string $deposit_date = '';

    public ?string $deposit_notes = '';

    public string $bread_kg_amount = '';

    public string $bread_sale_date = '';

    public string $bread_payment_status = 'paid';

    public ?string $bread_notes = '';

    public function mount(Customer $customer): void
    {
        abort_unless($customer->bakery_id === auth()->user()->bakery_id, 403);

        $this->customer = $customer;
        $this->deposit_date = now()->toDateString();
        $this->bread_sale_date = now()->toDateString();
    }

    public function addDeposit(): void
    {
        $this->validate([
            'kg_amount' => 'required|numeric|min:0.01',
            'deposit_date' => 'required|date',
            'deposit_notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () {
            FlourDeposit::create([
                'bakery_id' => $this->customer->bakery_id,
                'customer_id' => $this->customer->id,
                'kg_amount' => $this->kg_amount,
                'deposit_date' => $this->deposit_date,
                'notes' => $this->deposit_notes ?: null,
                'created_by' => auth()->id(),
            ]);

            $this->customer->increment('flour_balance_kg', $this->kg_amount);
        });

        $this->reset('kg_amount', 'deposit_notes');
        $this->deposit_date = now()->toDateString();
        $this->customer->refresh();

        $this->dispatch('toast', message: 'تم تسجيل استلام القمح وإضافته لرصيد العميل.');
        $this->dispatch('close-modal', 'flour-deposit');
    }

    public function deleteDeposit(int $depositId): void
    {
        $deposit = FlourDeposit::where('bakery_id', $this->customer->bakery_id)->findOrFail($depositId);

        DB::transaction(function () use ($deposit) {
            $this->customer->decrement('flour_balance_kg', $deposit->kg_amount);
            $deposit->delete();
        });

        $this->customer->refresh();

        $this->dispatch('toast', message: 'تم حذف عملية استلام القمح.');
    }

    public function takeBreadForFlour(): void
    {
        $this->validate([
            'bread_kg_amount' => 'required|numeric|min:0.01',
            'bread_sale_date' => 'required|date',
            'bread_payment_status' => 'required|in:paid,unpaid',
            'bread_notes' => 'nullable|string|max:1000',
        ]);

        if ($this->customer->flour_balance_kg < $this->bread_kg_amount) {
            $this->addError('bread_kg_amount', 'رصيد القمح لدى العميل غير كافٍ لهذه الكمية.');

            return;
        }

        $pricePerKg = $this->customer->bakery->flour_exchange_fee_per_kg;

        DB::transaction(function () use ($pricePerKg) {
            Sale::create([
                'bakery_id' => $this->customer->bakery_id,
                'customer_id' => $this->customer->id,
                'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
                'kg_amount' => $this->bread_kg_amount,
                'price_per_kg' => $pricePerKg,
                'total_amount' => round($this->bread_kg_amount * $pricePerKg, 2),
                'payment_status' => $this->bread_payment_status,
                'paid_at' => $this->bread_payment_status === Sale::STATUS_PAID ? now() : null,
                'sale_date' => $this->bread_sale_date,
                'notes' => $this->bread_notes ?: null,
                'created_by' => auth()->id(),
            ]);

            $this->customer->decrement('flour_balance_kg', $this->bread_kg_amount);
        });

        $this->reset('bread_kg_amount', 'bread_notes');
        $this->bread_sale_date = now()->toDateString();
        $this->bread_payment_status = 'paid';
        $this->customer->refresh();

        $this->dispatch('toast', message: 'تم تسجيل تسليم الخبز مقابل رصيد القمح بنجاح.');
        $this->dispatch('close-modal', 'bread-delivery');
    }

    public function render()
    {
        $flourDeposits = $this->customer->flourDeposits()->latest('deposit_date')->latest('id')->get();
        $sales = $this->customer->sales()->latest('sale_date')->latest('id')->get();

        return view('livewire.customer-show', compact('flourDeposits', 'sales'));
    }
}
