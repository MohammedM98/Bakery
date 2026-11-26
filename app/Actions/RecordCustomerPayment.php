<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordCustomerPayment
{
    public function __construct(private RecordMovements $movements, private SettleSales $settle) {}

    /**
     * A money "له" movement: a plain payment on account, or "دفعة عن بيع ..." when it names one
     * of the customer's sales. It may be more than the customer owes: the balance goes to "له".
     *
     * A transfer stores the bank names as text, so deleting a bank later leaves the statement as it was.
     *
     * @param  array{amount: string, payment_date: string, sale_id?: ?int, notes?: ?string, method?: string, from_bank_id?: int, to_bank_id?: int, transfer_reference?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, User $user): Movement
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $customer = $customer->bakery->customers()->lockForUpdate()->findOrFail($customer->id);
            $sale = isset($data['sale_id'])
                ? $customer->sales()->where('payment_status', Sale::STATUS_UNPAID)->find($data['sale_id'])
                    ?? throw ValidationException::withMessages(['sale_id' => 'هذا البيع غير موجود في مبيعات هذا الزبون غير المدفوعة.'])
                : null;
            $cents = Movement::cents($data['amount']);
            $bank = fn (string $key) => isset($data[$key]) ? $customer->bakery->banks()->findOrFail($data[$key])->name : null;

            $movement = $this->movements->handle($customer->bakery, $user, [[
                'customer_id' => $customer->id,
                'sale_id' => $sale?->id,
                'ledger' => Movement::LEDGER_MONEY,
                'type' => Movement::TYPE_PAYMENT,
                'amount_cents' => -$cents,
                'description' => $sale ? 'دفعة عن بيع '.$sale->sale_date->format('d/m/Y') : 'دفعة',
                'note' => $data['notes'] ?? null,
                'movement_date' => $data['payment_date'],
                'from_bank' => $bank('from_bank_id'),
                'to_bank' => $bank('to_bank_id'),
                'transfer_reference' => isset($data['from_bank_id']) ? ($data['transfer_reference'] ?? null) : null,
            ]])->first();

            $this->settle->handle($customer, $cents, $sale);

            return $movement;
        });
    }
}
