<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The bakery sales log (9-ب item 6). */
class SalesLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-26 10:00'); // a Thursday
    }

    private function owner(string $name = 'صاحب المخبز'): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id, 'name' => $name]);
    }

    private function record(User $user, string $route, array $data, ?Customer $customer = null): void
    {
        $this->actingAs($user)->post(route($route, $customer), [...$data, 'submission_id' => fake()->uuid()])->assertSessionHasNoErrors();
    }

    /**
     * Today: a paid walk-in cash sale of 4 kg (20 شيكل), 10 kg of bread for flour (15 شيكل fee, unpaid),
     * a 30 kg flour deposit and a 15 شيكل transfer payment. Yesterday: a 2 kg paid walk-in sale.
     */
    private function activity(User $owner): Customer
    {
        $customer = $owner->bakery->customers()->create(['name' => 'أحمد', 'mobile_number' => '0591111111']);
        $bank = $owner->bakery->banks()->create(['name' => 'جوال باي']);
        $today = local_today()->toDateString();

        $this->record($owner, 'panel.sales.store', ['kg_amount' => '4', 'payment_status' => 'paid', 'sale_date' => $today, 'buyer_name' => 'سامي']);
        $this->record($owner, 'panel.sales.store', ['kg_amount' => '2', 'payment_status' => 'paid', 'sale_date' => '2026-11-25']);
        $this->record($owner, 'panel.customers.flour-sales.store', ['kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => $today], $customer);
        $this->record($owner, 'panel.customers.deposits.store', ['kg_amount' => '30', 'deposit_date' => $today], $customer);
        $this->record($owner, 'panel.customers.payments.store', [
            'amount' => '15', 'payment_date' => $today, 'method' => 'transfer', 'from_bank_id' => $bank->id, 'to_bank_id' => $bank->id,
        ], $customer);

        return $customer;
    }

    public function test_today_by_default_with_the_summary_and_movements(): void
    {
        $owner = $this->owner();
        $this->activity($owner);
        $this->activity($this->owner()); // another bakery: never counted

        $this->actingAs($owner)->get(route('panel.sales-log'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/SalesLog')
                ->where('filters.period', 'day')
                ->where('range.from', '2026-11-26')
                ->where('range.to', '2026-11-26')
                ->where('range.label', 'الخميس 26/11/2026')
                ->where('previousDate', '2026-11-25')
                ->where('nextDate', null)
                ->where('summary', [
                    'bread_kg' => '14.00',
                    'bread_regular_kg' => '4.00',
                    'bread_flour_kg' => '10.00',
                    'money_received' => '35.00',
                    'money_cash' => '20.00',
                    'money_transfer' => '15.00',
                    'flour_spent_kg' => '10.00',
                    'flour_received_kg' => '30.00',
                ])
                // sale + payment (walk-in), withdrawal + fee, deposit, payment.
                ->where('movements.total', 6)
                ->where('movements.data.0.type_label', 'دفعة')
                ->where('movements.data.0.customer', 'أحمد')
                ->where('movements.data.5.customer', 'سامي')
                ->where('movements.data.5.customer_id', null)
                ->where('options.customers.0.name', 'أحمد')
                ->has('options.customers', 1)
                ->where('options.recordedBy', ['صاحب المخبز']));
    }

    public function test_week_month_and_year_periods_and_navigation(): void
    {
        $owner = $this->owner();
        $this->activity($owner);
        $get = fn (array $q) => $this->actingAs($owner)->get(route('panel.sales-log', $q))->assertOk();

        $get(['period' => 'week'])->assertInertia(fn (Assert $page) => $page
            ->where('range.from', '2026-11-21')->where('range.to', '2026-11-27') // Saturday to Friday
            ->where('previousDate', '2026-11-14')->where('nextDate', null)
            ->where('summary.bread_kg', '16.00')->where('summary.money_received', '45.00'));

        $get(['period' => 'month', 'date' => '2026-10-31'])->assertInertia(fn (Assert $page) => $page
            ->where('range.label', 'أكتوبر 2026')
            ->where('previousDate', '2026-09-01')->where('nextDate', '2026-11-01')
            ->where('summary.bread_kg', '0.00')->where('movements.total', 0));

        $get(['period' => 'year'])->assertInertia(fn (Assert $page) => $page
            ->where('range.from', '2026-01-01')->where('range.to', '2026-12-31')->where('range.label', '2026')
            ->where('movements.total', 8));

        $get(['period' => 'day', 'date' => '2026-11-25'])->assertInertia(fn (Assert $page) => $page
            ->where('nextDate', '2026-11-26')->where('summary.money_cash', '10.00')->where('movements.total', 2));
    }

    public function test_the_table_filters(): void
    {
        $owner = $this->owner();
        $customer = $this->activity($owner);
        $count = fn (array $q) => $this->actingAs($owner)->get(route('panel.sales-log', ['period' => 'year', ...$q]))
            ->viewData('page')['props']['movements']['total'];

        $this->assertSame(2, $count(['type' => 'sale']));
        $this->assertSame(2, $count(['type' => 'flour_sale'])); // the withdrawal and its fee
        $this->assertSame(3, $count(['type' => 'payment']));
        $this->assertSame(1, $count(['type' => 'deposit']));
        $this->assertSame(0, $count(['type' => 'adjustment']));
        $this->assertSame(4, $count(['customer' => $customer->id]));
        $this->assertSame(4, $count(['customer' => 'walk_in']));
        $this->assertSame(2, $count(['customer' => $customer->id, 'type' => 'payment']) + $count(['customer' => $customer->id, 'type' => 'deposit']));
        $this->assertSame(8, $count(['recorded_by' => 'صاحب المخبز']));
        $this->assertSame(0, $count(['recorded_by' => 'غيره']));
    }

    public function test_the_log_totals_equal_the_customer_statements(): void
    {
        $owner = $this->owner();
        $first = $this->activity($owner);
        $second = $owner->bakery->customers()->create(['name' => 'بسام', 'mobile_number' => '0592222222']);
        $this->record($owner, 'panel.customers.deposits.store', ['kg_amount' => '7.5', 'deposit_date' => '2026-11-24'], $second);
        $this->record($owner, 'panel.customers.flour-sales.store', ['kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => '2026-11-22'], $second);
        $this->record($owner, 'panel.customers.payments.store', ['amount' => '12.25', 'payment_date' => '2026-11-23'], $second);

        $summary = $this->actingAs($owner)->get(route('panel.sales-log', ['period' => 'week']))->viewData('page')['props']['summary'];

        $sum = function (string $ledger, string $column) use ($first, $second) {
            return collect([$first, $second])->sum(fn (Customer $c) => collect($c->statement($ledger, '2026-11-21', '2026-11-27')['rows'])
                ->sum(fn ($r) => (float) $r[$column]));
        };
        // Customers' flour: every debit is bread taken against flour, every credit a deposit.
        $this->assertEquals($sum('flour', 'debit'), (float) $summary['flour_spent_kg']);
        $this->assertEquals($sum('flour', 'credit'), (float) $summary['flour_received_kg']);
        // Customers' money credits are all payments; the walk-in cash sales (20 + 10 شيكل) are on top.
        $this->assertEquals($sum('money', 'credit') + 30, (float) $summary['money_received']);
        $this->assertSame('61.75', $summary['money_received']); // 30 walk-in + 15 transfer + 4.50 paid fee + 12.25
    }

    public function test_validation_failure(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('panel.sales-log', ['period' => 'decade']))->assertSessionHasErrors('period');
        $this->actingAs($owner)->get(route('panel.sales-log', ['date' => '26-11-2026']))->assertSessionHasErrors('date');
        $this->actingAs($owner)->get(route('panel.sales-log', ['type' => 'sales']))->assertSessionHasErrors('type');
        $this->actingAs($owner)->get(route('panel.sales-log', ['customer' => 'all; drop']))->assertSessionHasErrors('customer');
    }

    public function test_another_bakerys_customer_shows_nothing(): void
    {
        $owner = $this->owner();
        $this->activity($owner);
        $theirs = $this->activity($this->owner());

        $this->actingAs($owner)->get(route('panel.sales-log', ['period' => 'year', 'customer' => $theirs->id]))
            ->assertInertia(fn (Assert $page) => $page->where('movements.total', 0)->where('summary.bread_kg', '16.00'));
    }

    public function test_admin_cannot_see_the_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get(route('panel.sales-log'))->assertForbidden();
    }
}
