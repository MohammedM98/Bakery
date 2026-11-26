<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordFlourDeposit
{
    public function __construct(private RecordMovements $movements) {}

    /**
     * A flour "له" movement. It nets against flour the customer already owes by plain arithmetic.
     *
     * @param  array{kg_amount: string, deposit_date: string, notes?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, User $user): Movement
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            // Same lock order as every other write (customer, then bakery), so it cannot deadlock with them.
            $customer = $customer->bakery->customers()->lockForUpdate()->findOrFail($customer->id);

            return $this->movements->handle($customer->bakery, $user, [[
                'customer_id' => $customer->id,
                'ledger' => Movement::LEDGER_FLOUR,
                'type' => Movement::TYPE_DEPOSIT,
                'amount_cents' => -Movement::cents($data['kg_amount']),
                'description' => 'إيداع طحين '.kg($data['kg_amount']),
                'note' => $data['notes'] ?? null,
                'movement_date' => $data['deposit_date'],
            ]])->first();
        });
    }
}
