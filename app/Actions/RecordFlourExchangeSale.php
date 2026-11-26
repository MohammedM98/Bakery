<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordFlourExchangeSale
{
    /**
     * Records bread taken against the flour balance, charged the bakery's exchange fee per kilo,
     * and deducts the kilos from the balance. Refuses when the balance is not enough.
     *
     * @param  array{kg_amount: string, payment_status: string, sale_date: string, notes?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, User $user): Sale
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            // Lock the row so two requests cannot both spend the same balance.
            $customer = $customer->bakery->customers()->lockForUpdate()->findOrFail($customer->id);

            // Compared in hundredths of a kilo: exact, no float comparison.
            if (round((float) $customer->flour_balance_kg * 100) < round((float) $data['kg_amount'] * 100)) {
                throw ValidationException::withMessages([
                    'kg_amount' => 'رصيد القمح لدى العميل غير كافٍ لهذه الكمية.',
                ]);
            }

            $pricePerKg = $customer->bakery->flour_exchange_fee_per_kg;
            $total = round($data['kg_amount'] * $pricePerKg, 2);
            $paid = $data['payment_status'] === Sale::STATUS_PAID;

            $sale = Sale::create([
                'bakery_id' => $customer->bakery_id,
                'customer_id' => $customer->id,
                'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
                'kg_amount' => $data['kg_amount'],
                'price_per_kg' => $pricePerKg,
                'total_amount' => $total,
                'paid_amount' => $paid ? $total : 0,
                'payment_status' => $data['payment_status'],
                'paid_at' => $paid ? now() : null,
                'sale_date' => $data['sale_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $customer->decrement('flour_balance_kg', $data['kg_amount']);

            return $sale;
        });
    }
}
