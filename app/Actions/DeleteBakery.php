<?php

namespace App\Actions;

use App\Models\Bakery;
use Illuminate\Support\Facades\DB;

class DeleteBakery
{
    /** Removes the owner accounts; customers, sales, deposits and payments go with the bakery via cascading foreign keys. */
    public function handle(Bakery $bakery): void
    {
        DB::transaction(function () use ($bakery) {
            $bakery->owners()->delete();
            $bakery->delete();
        });
    }
}
