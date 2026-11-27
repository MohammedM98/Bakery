<?php

namespace Tests\Feature\Owner;

use App\Actions\RecordCustomerPayment;
use App\Actions\RecordSale;
use App\Models\Bakery;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'sale_date' => local_today()->toDateString(),
            ...$attributes,
        ]);

        // Takings come from payment movements, as the actions record them.
        if ($sale->paid_amount > 0) {
            $this->payment($owner, $sale->paid_amount, $sale->sale_date->toDateString(), $sale->id);
        }

        return $sale;
    }

    private function payment(User $owner, string|float $amount, string $date, ?int $saleId = null, ?string $bank = null): void
    {
        Movement::create([
            'bakery_id' => $owner->bakery_id,
            'number' => Movement::where('bakery_id', $owner->bakery_id)->max('number') + 1,
            'sale_id' => $saleId,
            'from_bank' => $bank,
            'to_bank' => $bank,
            'ledger' => Movement::LEDGER_MONEY,
            'type' => Movement::TYPE_PAYMENT,
            'amount' => -$amount,
            'description' => 'دفعة',
            'recorded_by_name' => $owner->name,
            'movement_date' => $date,
        ]);
    }

    public function test_todays_figures_the_chart_and_the_recent_movements(): void
    {
        $this->travelTo('2026-11-27 10:00:00'); // a Friday
        $owner = $this->owner();
        $this->sale($owner, ['kg_amount' => 5, 'total_amount' => 30, 'paid_amount' => 10, 'payment_status' => Sale::STATUS_UNPAID]);
        $this->sale($owner, ['kg_amount' => 2, 'total_amount' => 12, 'paid_amount' => 12, 'payment_status' => Sale::STATUS_PAID]);
        $this->sale($owner, ['kg_amount' => 3, 'total_amount' => 6, 'paid_amount' => 0, 'payment_status' => Sale::STATUS_UNPAID, 'sale_type' => Sale::TYPE_FLOUR_EXCHANGE]);
        $this->sale($owner, ['kg_amount' => 100, 'total_amount' => 600, 'paid_amount' => 600, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => '2026-11-26']);
        $this->sale($owner, ['kg_amount' => 9, 'total_amount' => 54, 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => '2026-11-19']);
        $this->sale($this->owner(), ['kg_amount' => 50, 'total_amount' => 300, 'payment_status' => Sale::STATUS_UNPAID]);
        // A payment on account yesterday is money received; so is a transfer today.
        $this->payment($owner, 50, '2026-11-26');
        $this->payment($owner, 8, '2026-11-27', bank: 'بنك فلسطين');

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Dashboard')
                ->where('period', 'day')
                ->where('rangeLabel', 'الجمعة 27/11/2026')
                ->where('bread', ['kg' => '10.00', 'regular' => '7.00', 'flour' => '3.00'])
                // Sales today 48, of which 22 paid (10 + 12); received 22 + 8 by transfer.
                ->where('takings', ['received' => '30.00', 'cash' => '22.00', 'transfer' => '8.00', 'unpaid' => '26.00', 'paidPercent' => 45])
                ->has('chart', 7)
                ->where('chart.6', ['label' => '27/11', 'kg' => 10, 'received' => 30])
                ->where('chart.5', ['label' => '26/11', 'kg' => 100, 'received' => 650])
                ->where('chart.0', ['label' => '21/11', 'kg' => 0, 'received' => 0])
                ->has('recent', 3)
                ->where('recent.0.type', Movement::TYPE_PAYMENT)
                ->where('recent.0.detail', 'دفعة، تحويل بنك فلسطين')
                ->where('pricePerKg', '6.00'));
    }

    public function test_each_period_sums_its_own_range_and_buckets(): void
    {
        $this->travelTo('2026-11-27 10:00:00');
        $owner = $this->owner();
        $this->sale($owner, ['kg_amount' => 4, 'total_amount' => 24, 'paid_amount' => 24, 'payment_status' => Sale::STATUS_PAID]);
        $this->sale($owner, ['kg_amount' => 6, 'total_amount' => 36, 'paid_amount' => 36, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => '2026-11-21']); // Saturday: this week
        $this->sale($owner, ['kg_amount' => 10, 'total_amount' => 60, 'paid_amount' => 60, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => '2026-11-20']); // last week
        $this->sale($owner, ['kg_amount' => 20, 'total_amount' => 120, 'paid_amount' => 120, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => '2026-10-15']);
        $this->sale($owner, ['kg_amount' => 40, 'total_amount' => 240, 'paid_amount' => 240, 'payment_status' => Sale::STATUS_PAID, 'sale_date' => '2025-06-01']);

        $get = fn (string $period) => $this->actingAs($owner)->get(route('panel.dashboard', ['period' => $period]));

        $get('week')->assertInertia(fn (Assert $page) => $page
            ->where('bread.kg', '10.00')
            ->where('takings.received', '60.00')
            ->where('takings.paidPercent', 100)
            ->where('chart.6', ['label' => '21/11', 'kg' => 10, 'received' => 60])
            ->where('chart.5', ['label' => '14/11', 'kg' => 10, 'received' => 60]));
        $get('month')->assertInertia(fn (Assert $page) => $page
            ->where('rangeLabel', 'نوفمبر 2026')
            ->where('bread.kg', '20.00')
            ->where('chart.6', ['label' => 'نوفمبر', 'kg' => 20, 'received' => 120])
            ->where('chart.5', ['label' => 'أكتوبر', 'kg' => 20, 'received' => 120]));
        $get('year')->assertInertia(fn (Assert $page) => $page
            ->where('bread.kg', '40.00')
            ->where('chart.6.label', '2026')
            ->where('chart.5', ['label' => '2025', 'kg' => 40, 'received' => 240]));
        $this->actingAs($owner)->get(route('panel.dashboard', ['period' => 'decade']))->assertSessionHasErrors('period');
    }

    public function test_another_bakerys_figures_never_reach_the_dashboard(): void
    {
        $owner = $this->owner();
        $other = $this->owner();
        $this->sale($other, ['kg_amount' => 50, 'total_amount' => 300, 'payment_status' => Sale::STATUS_UNPAID]);
        $this->payment($other, 80, local_today()->toDateString());

        foreach (['day', 'year'] as $period) {
            $this->actingAs($owner)
                ->get(route('panel.dashboard', ['period' => $period]))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('bread', ['kg' => '0.00', 'regular' => '0.00', 'flour' => '0.00'])
                    ->where('takings', ['received' => '0.00', 'cash' => '0.00', 'transfer' => '0.00', 'unpaid' => '0.00', 'paidPercent' => null])
                    ->where('chart.6.kg', 0)
                    ->where('chart.6.received', 0)
                    ->has('recent', 0)
                    ->has('customers', 0));
        }
    }

    public function test_a_sale_settled_from_earlier_credit_is_not_counted_as_takings(): void
    {
        $owner = $this->owner();
        $customer = $owner->bakery->customers()->create(['name' => 'زبون', 'mobile_number' => '0590000000']);
        app(RecordCustomerPayment::class)->handle($customer, ['amount' => '200', 'payment_date' => now()->subDay()->toDateString()], $owner);
        app(RecordSale::class)->handle($owner->bakery, $customer, Sale::TYPE_CASH, [
            'kg_amount' => '10', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => local_today()->toDateString(),
        ], $owner);

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('takings.received', '0.00')
                // Settled from the credit: fully paid, nothing left unpaid.
                ->where('takings.paidPercent', 100)
                ->where('takings.unpaid', '0.00')
                ->where('chart.5.received', 200)
                ->where('recent.0.type', Movement::TYPE_SALE)
                ->where('recent.0.who', 'زبون')
                ->where('recent.0.detail', 'بيع عادي، 10 كغ'));
    }

    public function test_the_deposit_dialog_gets_the_customers_only_with_the_permission(): void
    {
        $owner = $this->owner();
        $owner->bakery->customers()->create(['name' => 'زبون', 'mobile_number' => '0590000000']);
        $staff = User::factory()->create([
            'role' => User::ROLE_BAKERY_STAFF, 'bakery_id' => $owner->bakery_id, 'permissions' => ['view-dashboard'],
        ]);

        $this->actingAs($owner)->get(route('panel.dashboard'))->assertInertia(fn (Assert $page) => $page->has('customers', 1));
        $this->actingAs($staff)->get(route('panel.dashboard'))->assertInertia(fn (Assert $page) => $page->has('customers', 0));
        $staff->update(['permissions' => ['view-dashboard', 'record-deposit']]);
        $this->actingAs($staff)->get(route('panel.dashboard'))->assertInertia(fn (Assert $page) => $page->has('customers', 0));
        $staff->update(['permissions' => ['view-dashboard', 'record-deposit', 'view-customers']]);
        $this->actingAs($staff)->get(route('panel.dashboard'))->assertInertia(fn (Assert $page) => $page->has('customers', 1));
        $staff->update(['permissions' => ['record-deposit']]);
        $this->actingAs($staff)->get(route('panel.dashboard'))->assertForbidden();
    }

    public function test_the_date_follows_the_bakerys_day_in_palestine_not_utc(): void
    {
        $this->travelTo('2026-11-26 23:30:00'); // 01:30 on the 27th in Palestine
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('currentDate', '2026-11-27'));

        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'kg_amount' => '2',
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => '2026-11-27',
            'submission_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'kg_amount' => '2',
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => '2026-11-28',
            'submission_id' => (string) Str::uuid(),
        ])->assertSessionHasErrors('sale_date');
    }

    public function test_admin_cannot_open_the_owner_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('panel.dashboard'))->assertForbidden();
    }
}
