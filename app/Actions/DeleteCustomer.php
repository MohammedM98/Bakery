<?php

namespace App\Actions;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class DeleteCustomer
{
    /**
     * Deletes the customer with all their records, movements included. This is the only path
     * in the app that deletes movements (owner only, after a confirmation dialog).
     * Old deposits and payments cascade at the database level; sales only null there.
     */
    public function handle(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            // The customer's row first, like every other write (customer, then bakery): the tombstone takes the bakery lock.
            $customer->newQuery()->whereKey($customer->id)->lockForUpdate()->value('id');
            $customer->movements()->delete();
            $customer->sales()->delete();
            $customer->delete();
        });
    }
}
