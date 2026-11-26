<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordFlourDeposit
{
    /**
     * Records the deposit and adds it to the customer's flour balance.
     *
     * @param  array{kg_amount: string, deposit_date: string, notes?: ?string}  $data
     */
    public function handle(Customer $customer, array $data, User $user): FlourDeposit
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $deposit = FlourDeposit::create([
                'bakery_id' => $customer->bakery_id,
                'customer_id' => $customer->id,
                'kg_amount' => $data['kg_amount'],
                'deposit_date' => $data['deposit_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $customer->increment('flour_balance_kg', $data['kg_amount']);

            return $deposit;
        });
    }
}
