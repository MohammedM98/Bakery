<?php

namespace App\Actions;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCustomer
{
    public function __construct(private RecordMovements $movements) {}

    /**
     * The opening flour balance (flour the customer already has at the bakery) becomes an
     * opening-balance movement "له", so the balance is still the sum of the movements.
     *
     * @param  array{name: string, mobile_number: string, notes?: ?string, flour_balance_kg?: ?string}  $data
     */
    public function handle(Bakery $bakery, array $data, User $user): Customer
    {
        return DB::transaction(function () use ($bakery, $data, $user) {
            $customer = $bakery->customers()->create([
                'name' => $data['name'],
                'mobile_number' => $data['mobile_number'],
                'notes' => $data['notes'] ?? null,
            ]);

            $this->movements->handle($bakery, $user, [[
                'customer_id' => $customer->id,
                'ledger' => Movement::LEDGER_FLOUR,
                'type' => Movement::TYPE_OPENING_BALANCE,
                'amount_cents' => -Movement::cents($data['flour_balance_kg'] ?? 0),
                'description' => 'رصيد افتتاحي',
                'movement_date' => now()->toDateString(),
            ]]);

            return $customer;
        });
    }
}
