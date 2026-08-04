<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\FlourDeposit;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CustomerShow extends Component
{
    public Customer $customer;

    #[Validate('required|numeric|min:0.01')]
    public string $kg_amount = '';

    #[Validate('required|date')]
    public string $deposit_date = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $deposit_notes = '';

    public ?string $depositMessage = null;

    public function mount(Customer $customer): void
    {
        abort_unless($customer->bakery_id === auth()->user()->bakery_id, 403);

        $this->customer = $customer;
        $this->deposit_date = now()->toDateString();
    }

    public function addDeposit(): void
    {
        $this->validate();

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

        $this->depositMessage = 'تم تسجيل استلام القمح وإضافته لرصيد العميل.';
    }

    public function deleteDeposit(int $depositId): void
    {
        $deposit = FlourDeposit::where('bakery_id', $this->customer->bakery_id)->findOrFail($depositId);

        DB::transaction(function () use ($deposit) {
            $this->customer->decrement('flour_balance_kg', $deposit->kg_amount);
            $deposit->delete();
        });

        $this->customer->refresh();

        $this->depositMessage = 'تم حذف عملية استلام القمح.';
    }

    #[On('sale-saved')]
    public function refreshAfterModal(): void
    {
        // No-op: handling the event triggers a fresh render(), enough to
        // show a sale created via the modal.
    }

    public function render()
    {
        $flourDeposits = $this->customer->flourDeposits()->latest('deposit_date')->latest('id')->get();
        $sales = $this->customer->sales()->latest('sale_date')->latest('id')->get();

        return view('livewire.customer-show', compact('flourDeposits', 'sales'));
    }
}
