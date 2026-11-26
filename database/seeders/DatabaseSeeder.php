<?php

namespace Database\Seeders;

use App\Actions\CreateCustomer;
use App\Actions\RecordFlourDeposit;
use App\Actions\RecordSale;
use App\Models\Bakery;
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

        // Through the actions, so every record has its movements.
        $customer = app(CreateCustomer::class)->handle($bakery, [
            'name' => 'أحمد المالكي',
            'mobile_number' => '0501234567',
            'notes' => 'زبون أودع 30 كغ طحين، وأخذ 18 كغ خبزًا حتى الآن.',
        ], $owner);

        app(RecordFlourDeposit::class)->handle($customer, ['kg_amount' => '30', 'deposit_date' => now()->subDays(5)->toDateString()], $owner);

        $sale = app(RecordSale::class);
        $sale->handle($bakery, $customer, Sale::TYPE_FLOUR_EXCHANGE, ['kg_amount' => '10', 'payment_status' => Sale::STATUS_PAID, 'sale_date' => now()->subDays(2)->toDateString()], $owner);
        $sale->handle($bakery, $customer, Sale::TYPE_FLOUR_EXCHANGE, ['kg_amount' => '8', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->subDay()->toDateString()], $owner);
        $sale->handle($bakery, null, Sale::TYPE_CASH, ['kg_amount' => '5', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->toDateString()], $owner);
    }
}
