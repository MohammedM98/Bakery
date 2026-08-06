<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\Payment;
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

    public string $payment_amount = '';

    public string $payment_date = '';

    public ?string $payment_notes = '';

    public function mount(Customer $customer): void
    {
        abort_unless($customer->bakery_id === auth()->user()->bakery_id, 403);

        $this->customer = $customer;
        $this->deposit_date = now()->toDateString();
        $this->bread_sale_date = now()->toDateString();
        $this->payment_date = now()->toDateString();
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
            $totalAmount = round($this->bread_kg_amount * $pricePerKg, 2);

            Sale::create([
                'bakery_id' => $this->customer->bakery_id,
                'customer_id' => $this->customer->id,
                'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
                'kg_amount' => $this->bread_kg_amount,
                'price_per_kg' => $pricePerKg,
                'total_amount' => $totalAmount,
                'paid_amount' => $this->bread_payment_status === Sale::STATUS_PAID ? $totalAmount : 0,
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

    public function recordPayment(): void
    {
        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_notes' => 'nullable|string|max:1000',
        ]);

        $outstanding = $this->customer->outstandingBalance();

        if ((float) $this->payment_amount > $outstanding) {
            $this->addError('payment_amount', 'المبلغ أكبر من المبلغ المستحق على العميل ('.money($outstanding).').');

            return;
        }

        $paidAmount = $this->payment_amount;

        DB::transaction(function () {
            Payment::create([
                'bakery_id' => $this->customer->bakery_id,
                'customer_id' => $this->customer->id,
                'amount' => $this->payment_amount,
                'payment_date' => $this->payment_date,
                'notes' => $this->payment_notes ?: null,
                'created_by' => auth()->id(),
            ]);

            $remaining = (float) $this->payment_amount;

            $unpaidSales = $this->customer->sales()
                ->where('payment_status', Sale::STATUS_UNPAID)
                ->oldest('sale_date')
                ->oldest('id')
                ->get();

            foreach ($unpaidSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, $sale->remainingAmount());
                $sale->paid_amount += $applied;

                if ($sale->paid_amount >= $sale->total_amount) {
                    $sale->payment_status = Sale::STATUS_PAID;
                    $sale->paid_at = now();
                }

                $sale->save();

                $remaining -= $applied;
            }
        });

        $this->reset('payment_amount', 'payment_notes');
        $this->payment_date = now()->toDateString();

        $this->dispatch('toast', message: 'تم تسجيل الدفعة بمبلغ '.money($paidAmount).'.');
        $this->dispatch('close-modal', 'record-payment');
    }

    public function render()
    {
        $flourDeposits = $this->customer->flourDeposits()->latest('deposit_date')->latest('id')->get();
        $sales = $this->customer->sales()->latest('sale_date')->latest('id')->get();
        $payments = $this->customer->payments()->latest('payment_date')->latest('id')->get();

        return view('livewire.customer-show', [
            'flourDeposits' => $flourDeposits,
            'sales' => $sales,
            'payments' => $payments,
            'outstandingBalance' => $this->customer->outstandingBalance(),
        ]);
    }
}
