<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 6,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function sale(User $owner, array $attributes): Sale
    {
        return Sale::create([
            'bakery_id' => $owner->bakery_id,
            'sale_type' => Sale::TYPE_CASH,
            'price_per_kg' => 6,
            'paid_amount' => 0,
            'sale_date' => now()->toDateString(),
            ...$attributes,
        ]);
    }

    public function test_todays_figures_the_week_and_the_lists(): void
    {
        $owner = $this->owner();
        $unpaid = $this->sale($owner, ['kg_amount' => 5, 'total_amount' => 30, 'paid_amount' => 10, 'payment_status' => Sale::STATUS_UNPAID]);
        $this->sale($owner, ['kg_amount' => 2, 'total_amount' => 12, 'paid_amount' => 12, 'payment_status' => Sale::STATUS_PAID]);
        $this->sale($owner, ['kg_amount' => 100, 'total_amount' => 600, 'paid_amount' => 600, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => now()->subDay()->toDateString()]);
        $this->sale($owner, ['kg_amount' => 9, 'total_amount' => 54, 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->subDays(8)->toDateString()]);
        $this->sale($this->owner(), ['kg_amount' => 50, 'total_amount' => 300, 'payment_status' => Sale::STATUS_UNPAID]);

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Dashboard')
                ->where('today', ['kg' => 7, 'takings' => 12, 'unpaid' => 20, 'salesCount' => 2, 'unpaidCount' => 1])
                ->has('last7Days', 7)
                ->where('last7Days.6', ['date' => now()->toDateString(), 'kg' => 7, 'takings' => 12])
                ->where('last7Days.5', ['date' => now()->subDay()->toDateString(), 'kg' => 100, 'takings' => 600])
                ->where('last7Days.0.kg', 0)
                ->has('unpaidSales', 2)
                ->where('unpaidSales.0.id', $unpaid->id)
                ->has('recentSales', 4)
                ->where('pricePerKg', '6.00'));
    }

    public function test_admin_cannot_open_the_owner_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertForbidden();
    }
}
