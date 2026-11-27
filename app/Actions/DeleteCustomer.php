<?php

namespace App\Actions;

use App\Models\Bakery;
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
            // Customer, then bakery, like every other write, and both before deleting rows: the tombstone needs the
            // bakery lock, and taking it after the deletes' gap locks could deadlock with a concurrent sale (MySQL).
            $customer->newQuery()->whereKey($customer->id)->lockForUpdate()->value('id');
            Bakery::whereKey($customer->bakery_id)->lockForUpdate()->value('id');
            $customer->movements()->delete();
            $customer->sales()->delete();
            $customer->delete();
        });
    }
}
