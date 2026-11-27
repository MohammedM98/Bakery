<?php

namespace App\Actions;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateBakeryWithOwner
{
    /**
     * @param  array{name: string, phone?: ?string, address?: ?string, subscription_months: int|string, owner_name: string, owner_email: string, owner_password: string}  $data
     */
    public function handle(array $data, User $admin): Bakery
    {
        return DB::transaction(function () use ($data, $admin) {
            $bakery = Bakery::create([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'subscription_status' => Bakery::STATUS_ACTIVE,
                'subscription_expires_at' => local_today()->addMonths((int) $data['subscription_months'])->toDateString(),
                'regular_price_per_kg' => 0,
                'flour_exchange_fee_per_kg' => 0,
                'created_by' => $admin->id,
            ]);

            User::create([
                'bakery_id' => $bakery->id,
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => Hash::make($data['owner_password']),
                'role' => User::ROLE_BAKERY_OWNER,
            ]);

            return $bakery;
        });
    }
}
