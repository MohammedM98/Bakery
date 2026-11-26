<?php

namespace Tests\Feature\Owner;

use App\Actions\RecordCustomerPayment;
use App\Actions\RecordSale;
use App\Models\Bakery;
use App\Models\Movement;
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
        $sale = Sale::create([
            'bakery_id' => $owner->bakery_id,
            'sale_type' => Sale::TYPE_CASH,
            'price_per_kg' => 6,
            'paid_amount' => 0,
            'sale_date' => now()->toDateString(),
            ...$attributes,
        ]);

        // Takings come from payment movements, as the actions record them.
        if ($sale->paid_amount > 0) {
            $this->payment($owner, $sale->paid_amount, $sale->sale_date->toDateString(), $sale->id);
        }

        return $sale;
    }

    private function payment(User $owner, string|float $amount, string $date, ?int $saleId = null): void
    {
        Movement::create([
            'bakery_id' => $owner->bakery_id,
            'number' => Movement::where('bakery_id', $owner->bakery_id)->max('number') + 1,
            'sale_id' => $saleId,
            'ledger' => Movement::LEDGER_MONEY,
            'type' => Movement::TYPE_PAYMENT,
            'amount' => -$amount,
            'description' => 'دفعة',
            'recorded_by_name' => $owner->name,
            'movement_date' => $date,
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
        // A payment on account yesterday is money received; the partial 10 today is too.
        $this->payment($owner, 50, now()->subDay()->toDateString());

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Dashboard')
                ->where('today', ['kg' => 7, 'takings' => 22, 'unpaid' => 20, 'salesCount' => 2, 'unpaidCount' => 1])
                ->has('last7Days', 7)
                ->where('last7Days.6', ['date' => now()->toDateString(), 'kg' => 7, 'takings' => 22])
                ->where('last7Days.5', ['date' => now()->subDay()->toDateString(), 'kg' => 100, 'takings' => 650])
                ->where('last7Days.0.kg', 0)
                ->has('unpaidSales', 2)
                ->where('unpaidSales.0.id', $unpaid->id)
                ->has('recentSales', 4)
                ->where('pricePerKg', '6.00'));
    }

    public function test_another_bakerys_sales_never_reach_the_dashboard(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->sale($other, ['kg_amount' => 50, 'total_amount' => 300, 'payment_status' => Sale::STATUS_UNPAID]);
        $this->payment($other, 80, now()->toDateString());

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('today', ['kg' => 0, 'takings' => 0, 'unpaid' => 0, 'salesCount' => 0, 'unpaidCount' => 0])
                ->where('last7Days.6.kg', 0)
                ->has('unpaidSales', 0)
                ->has('recentSales', 0));
    }

    public function test_a_sale_settled_from_earlier_credit_is_not_counted_as_takings(): void
    {
        $owner = $this->owner();
        $customer = $owner->bakery->customers()->create(['name' => 'زبون', 'mobile_number' => '0590000000']);
        app(RecordCustomerPayment::class)->handle($customer, ['amount' => '200', 'payment_date' => now()->subDay()->toDateString()], $owner);
        app(RecordSale::class)->handle($owner->bakery, $customer, Sale::TYPE_CASH, [
            'kg_amount' => '10', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->toDateString(),
        ], $owner);

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.takings', 0)
                ->where('last7Days.5.takings', 200)
                ->where('today.unpaidCount', 0));
    }

    public function test_the_servers_date_is_shared_for_date_field_defaults(): void
    {
        $this->travelTo('2026-11-26 23:30:00');

        $this->actingAs($this->owner())
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('currentDate', '2026-11-26'));
    }

    public function test_admin_cannot_open_the_owner_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertForbidden();
    }
}
