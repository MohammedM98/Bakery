<?php

namespace App\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomer
{
    /**
     * Deletes the customer with all their records. Deposits and payments cascade at the
     * database level; sales are only nulled there, so they are deleted here explicitly.
     */
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer->sales()->delete();
            $customer->delete();
        });
    }
}
