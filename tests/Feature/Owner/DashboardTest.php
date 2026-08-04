<?php

namespace Tests\Feature\Owner;

use App\Livewire\DashboardPage;
use App\Models\Bakery;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_todays_sales_are_counted_correctly(): void
    {
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        // A sale dated today, stored the same way the seeder/component do
        // (via ->toDateString()), must still be picked up by the dashboard's
        // "today" aggregation regardless of how the date cast round-trips it.
        Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => 6,
            'total_amount' => 30,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);

        // A sale from yesterday must not be counted as "today".
        Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 100,
            'price_per_kg' => 6,
            'total_amount' => 600,
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->subDay()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(DashboardPage::class)
            ->assertViewHas('todayKg', fn ($kg) => (float) $kg === 5.0)
            ->assertViewHas('todayUnpaid', fn ($amount) => (float) $amount === 30.0)
            ->assertViewHas('todaySalesCount', 1);
    }
}
