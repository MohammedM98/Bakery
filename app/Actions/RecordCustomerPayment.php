<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerPayment
{
    /**
     * Records the payment and applies it to the customer's unpaid sales, oldest first;
     * a sale becomes paid once it is covered in full. Refuses more than the amount owed.
     *
     * @param  array{amount: string, payment_date: string, notes?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            // Serialise payments per customer so two of them cannot both pass the check below.
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);

            $unpaidSales = $customer->sales()
                ->where('payment_status', Sale::STATUS_UNPAID)
                ->oldest('sale_date')
                ->oldest('id')
                ->lockForUpdate()
                ->get();

            // Whole agorot (cents) so the comparisons are exact.
            $cents = fn ($amount) => (int) round((float) $amount * 100);
            $remaining = $cents($data['amount']);
            $outstanding = $unpaidSales->sum(fn (Sale $sale) => $cents($sale->remainingAmount()));

            if ($remaining > $outstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'المبلغ أكبر من المبلغ المستحق على العميل ('.money($outstanding / 100).').',
                ]);
            }

            $payment = Payment::create([
                'bakery_id' => $customer->bakery_id,
                'customer_id' => $customer->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            foreach ($unpaidSales as $sale) {
                if ($remaining <= 0) {
                    break;
                }

                $applied = min($remaining, $cents($sale->remainingAmount()));
                $paidCents = $cents($sale->paid_amount) + $applied;
                $sale->paid_amount = $paidCents / 100;

                if ($paidCents >= $cents($sale->total_amount)) {
                    $sale->payment_status = Sale::STATUS_PAID;
                    $sale->paid_at = now();
                }

                $sale->save();
                $remaining -= $applied;
            }

            return $payment;
        });
    }
}
