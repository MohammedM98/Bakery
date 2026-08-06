<?php

namespace Database\Seeders;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with a super admin and a demo bakery.
     */
    public function run(): void
    {
        if (User::where('role', User::ROLE_SUPER_ADMIN)->exists()) {
            return;
        }

        $admin = User::create([
            'name' => 'مالك المنصة',
            'email' => 'admin@bakery.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $bakery = Bakery::create([
            'name' => 'مخبز النور',
            'phone' => '0555000111',
            'address' => 'الرياض - حي النخيل',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth()->toDateString(),
            'regular_price_per_kg' => 6,
            'flour_exchange_fee_per_kg' => 2,
            'created_by' => $admin->id,
        ]);

        $owner = User::create([
            'bakery_id' => $bakery->id,
            'name' => 'صاحب المخبز',
            'email' => 'owner@bakery.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_BAKERY_OWNER,
        ]);

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'أحمد المالكي',
            'mobile_number' => '0501234567',
            'flour_balance_kg' => 20,
            'notes' => 'عميل زوّد المخبز بـ 30 كجم قمح، استهلك 10 كجم حتى الآن.',
        ]);

        FlourDeposit::create([
            'bakery_id' => $bakery->id,
            'customer_id' => $customer->id,
            'kg_amount' => 30,
            'deposit_date' => now()->subDays(5)->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sale::create([
            'bakery_id' => $bakery->id,
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'kg_amount' => 10,
            'price_per_kg' => $bakery->flour_exchange_fee_per_kg,
            'total_amount' => 10 * $bakery->flour_exchange_fee_per_kg,
            'paid_amount' => 10 * $bakery->flour_exchange_fee_per_kg,
            'payment_status' => Sale::STATUS_PAID,
            'paid_at' => now()->subDays(2),
            'sale_date' => now()->subDays(2)->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sale::create([
            'bakery_id' => $bakery->id,
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'kg_amount' => 8,
            'price_per_kg' => $bakery->flour_exchange_fee_per_kg,
            'total_amount' => 8 * $bakery->flour_exchange_fee_per_kg,
            'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->subDay()->toDateString(),
            'created_by' => $owner->id,
        ]);

        Sale::create([
            'bakery_id' => $bakery->id,
            'customer_id' => null,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => $bakery->regular_price_per_kg,
            'total_amount' => 5 * $bakery->regular_price_per_kg,
            'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
            'created_by' => $owner->id,
        ]);
    }
}
