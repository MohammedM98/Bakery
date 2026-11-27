<?php

namespace App\Actions;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records a sale with its movements, in one transaction:
 * - cash sale: money "عليه" for the bread (customer may be null: a walk-in buyer);
 * - bread against flour: flour "عليه" for the kilos and money "عليه" for the exchange fee.
 * Never refused for an insufficient flour balance: the balance just goes to "عليه".
 * When marked paid, a separate payment movement "له" for the total, linked to the sale.
 */
class RecordSale
{
    public function __construct(private RecordMovements $movements, private SettleSales $settle) {}

    /**
     * $pricePerKg: the price or fee an offline device used (section 13), already checked against the bakery's
     * price history by the API. Null (the website, and online API calls) = the bakery's current setting.
     *
     * @param  array{kg_amount: string, payment_status: string, sale_date: string, notes?: ?string, buyer_name?: ?string, buyer_mobile?: ?string}  $data
     */
    public function handle(Bakery $bakery, ?Customer $customer, string $type, array $data, User $user, ?string $pricePerKg = null): Sale
    {
        return DB::transaction(function () use ($bakery, $customer, $type, $data, $user, $pricePerKg) {
            if ($customer) {
                // Serialise this customer's writes so the settle step below sees a stable balance.
                $customer = $bakery->customers()->lockForUpdate()->findOrFail($customer->id);
            }
            // Lock the bakery before inserting the sale: the insert's foreign-key check takes a shared
            // lock on the bakery row, and two sales upgrading it in RecordMovements would deadlock (MySQL).
            Bakery::whereKey($bakery->id)->lockForUpdate()->value('id');

            $exchange = $type === Sale::TYPE_FLOUR_EXCHANGE;
            $pricePerKg ??= $exchange ? $bakery->flour_exchange_fee_per_kg : $bakery->regular_price_per_kg;
            $totalCents = Movement::cents($data['kg_amount'] * $pricePerKg);
            $paid = $data['payment_status'] === Sale::STATUS_PAID;

            $sale = $bakery->sales()->create([
                'customer_id' => $customer?->id,
                'buyer_name' => $customer ? null : ($data['buyer_name'] ?? null),
                'buyer_mobile' => $customer ? null : ($data['buyer_mobile'] ?? null),
                'sale_type' => $type,
                'kg_amount' => $data['kg_amount'],
                'price_per_kg' => $pricePerKg,
                'total_amount' => Movement::decimal($totalCents),
                // A free sale (price 0) has nothing to collect, so it is paid.
                'paid_amount' => $paid ? Movement::decimal($totalCents) : 0,
                'payment_status' => $paid || $totalCents === 0 ? Sale::STATUS_PAID : Sale::STATUS_UNPAID,
                'paid_at' => $paid || $totalCents === 0 ? now() : null,
                'sale_date' => $data['sale_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $base = ['customer_id' => $customer?->id, 'sale_id' => $sale->id, 'movement_date' => $data['sale_date']];
            $kg = kg($data['kg_amount']);

            $this->movements->handle($bakery, $user, [
                ...($exchange ? [[...$base, 'ledger' => Movement::LEDGER_FLOUR, 'type' => Movement::TYPE_FLOUR_WITHDRAWAL,
                    'amount_cents' => Movement::cents($data['kg_amount']), 'description' => 'خبز مقابل طحين '.$kg, 'note' => $data['notes'] ?? null]] : []),
                [...$base, 'ledger' => Movement::LEDGER_MONEY, 'type' => $exchange ? Movement::TYPE_EXCHANGE_FEE : Movement::TYPE_SALE,
                    'amount_cents' => $totalCents, 'description' => ($exchange ? 'رسم تبديل طحين ' : 'خبز ').$kg.' × '.money($pricePerKg),
                    'note' => $data['notes'] ?? null],
                ...($paid ? [[...$base, 'ledger' => Movement::LEDGER_MONEY, 'type' => Movement::TYPE_PAYMENT,
                    'amount_cents' => -$totalCents, 'description' => 'دفعة عن بيع '.$sale->sale_date->format('d/m/Y')]] : []),
            ]);

            if ($customer && ! $paid) {
                $this->settle->handle($customer);
            }

            return $sale;
        });
    }
}
