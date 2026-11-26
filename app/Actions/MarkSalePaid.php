<?php

namespace App\Actions;

use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkSalePaid
{
    public function __construct(private RecordMovements $movements) {}

    /**
     * Confirms the rest of the sale as received: a payment movement for what was left, linked to
     * the sale. Returns the amount confirmed, or null when the sale was already paid (a second click).
     */
    public function handle(Sale $sale, User $user): ?string
    {
        return DB::transaction(function () use ($sale, $user) {
            $sale = $sale->bakery->sales()->whereKey($sale->id)->lockForUpdate()->first();

            if ($sale->isPaid()) {
                return null;
            }

            $remaining = Movement::cents($sale->remainingAmount());
            $sale->update(['payment_status' => Sale::STATUS_PAID, 'paid_amount' => $sale->total_amount, 'paid_at' => now()]);

            $this->movements->handle($sale->bakery, $user, [[
                'customer_id' => $sale->customer_id,
                'sale_id' => $sale->id,
                'ledger' => Movement::LEDGER_MONEY,
                'type' => Movement::TYPE_PAYMENT,
                'amount_cents' => -$remaining,
                'description' => 'دفعة عن بيع '.$sale->sale_date->toDateString(),
                'movement_date' => now()->toDateString(),
            ]]);

            return Movement::decimal($remaining);
        });
    }
}
