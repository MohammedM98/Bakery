<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The customer page and its actions: flour deposit, bread against flour, payment. */
class CustomerAccountTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function customer(User $owner, float $flour = 0): Customer
    {
        return Customer::create([
            'bakery_id' => $owner->bakery_id,
            'name' => 'أحمد',
            'mobile_number' => '0591111111',
            'notes' => 'زبون قديم',
            'flour_balance_kg' => $flour,
        ]);
    }

    private function unpaidSale(Customer $customer, float $total, string $date, float $paid = 0): Sale
    {
        return Sale::create([
            'bakery_id' => $customer->bakery_id,
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 1,
            'price_per_kg' => $total,
            'total_amount' => $total,
            'paid_amount' => $paid,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => $date,
        ]);
    }

    private function id(): string
    {
        return fake()->uuid();
    }

    public function test_show_renders_the_customer_with_balances_and_history(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 12.5);
        $sale = $this->unpaidSale($customer, 40, now()->toDateString(), 10);
        $deposit = FlourDeposit::create(['bakery_id' => $owner->bakery_id, 'customer_id' => $customer->id, 'kg_amount' => 12.5, 'deposit_date' => '2026-11-01']);
        $payment = Payment::create(['bakery_id' => $owner->bakery_id, 'customer_id' => $customer->id, 'amount' => 10, 'payment_date' => '2026-11-02', 'notes' => 'نقدًا']);

        $this->actingAs($owner)
            ->get(route('panel.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Customers/Show')
                ->where('customer', [
                    'id' => $customer->id,
                    'name' => 'أحمد',
                    'mobile_number' => '0591111111',
                    'notes' => 'زبون قديم',
                    'flour_balance_kg' => '12.50',
                ])
                ->where('outstandingBalance', '30.00')
                ->where('flourExchangeFee', '1.50')
                ->where('sales.0.id', $sale->id)
                ->where('sales.0.remaining_amount', '30.00')
                ->where('deposits.0', ['id' => $deposit->id, 'kg_amount' => '12.50', 'notes' => null, 'deposit_date' => '2026-11-01'])
                ->where('payments.0', ['id' => $payment->id, 'amount' => '10.00', 'notes' => 'نقدًا', 'payment_date' => '2026-11-02']));
    }

    public function test_deposit_adds_to_the_flour_balance(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 2);

        $this->actingAs($owner)
            ->post(route('panel.customers.deposits.store', $customer), ['kg_amount' => '10.5', 'deposit_date' => '2026-11-20', 'notes' => 'كيس', 'submission_id' => $this->id()])
            ->assertSessionHas('success', 'تم تسجيل استلام القمح وإضافته لرصيد العميل.');

        $this->assertSame('12.50', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('flour_deposits', ['customer_id' => $customer->id, 'kg_amount' => 10.5, 'notes' => 'كيس', 'created_by' => $owner->id]);
    }

    public function test_deposit_validation_does_not_touch_the_balance(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 2);

        $this->actingAs($owner)
            ->post(route('panel.customers.deposits.store', $customer), ['kg_amount' => '', 'deposit_date' => 'not a date', 'submission_id' => $this->id()])
            ->assertSessionHasErrors(['kg_amount', 'deposit_date']);

        $this->assertSame('2.00', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('flour_deposits', 0);
    }

    public function test_a_double_submitted_deposit_is_counted_once(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $data = ['kg_amount' => '5', 'deposit_date' => '2026-11-20', 'submission_id' => $this->id()];

        $this->actingAs($owner)->post(route('panel.customers.deposits.store', $customer), $data);
        $this->actingAs($owner)->post(route('panel.customers.deposits.store', $customer), $data);

        $this->assertSame('5.00', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('flour_deposits', 1);
    }

    public function test_bread_against_flour_deducts_the_balance_and_charges_the_fee(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 20);

        $this->actingAs($owner)
            ->post(route('panel.customers.flour-sales.store', $customer), [
                'kg_amount' => '8', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => $this->id(),
            ])
            ->assertSessionHas('success', 'تم تسجيل تسليم الخبز مقابل رصيد القمح بنجاح.');

        $this->assertSame('12.00', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('sales', [
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'kg_amount' => 8,
            'price_per_kg' => 1.5,
            'total_amount' => 12,
            'paid_amount' => 12,
            'payment_status' => 'paid',
        ]);
    }

    public function test_bread_against_flour_can_be_unpaid(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 20);

        $this->actingAs($owner)->post(route('panel.customers.flour-sales.store', $customer), [
            'kg_amount' => '4', 'payment_status' => 'unpaid', 'sale_date' => '2026-11-20', 'submission_id' => $this->id(),
        ]);

        $this->assertSame(6.0, $customer->fresh()->outstandingBalance());
    }

    public function test_bread_against_flour_is_refused_beyond_the_balance_and_can_be_retried(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 3);
        $id = $this->id();

        $this->actingAs($owner)
            ->post(route('panel.customers.flour-sales.store', $customer), ['kg_amount' => '3.01', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => $id])
            ->assertSessionHasErrors(['kg_amount' => 'رصيد القمح لدى العميل غير كافٍ لهذه الكمية.']);

        $this->assertSame('3.00', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('sales', 0);

        // The same form, corrected, goes through: a refused attempt does not use up its submission id.
        $this->actingAs($owner)
            ->post(route('panel.customers.flour-sales.store', $customer), ['kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => $id])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', $customer->fresh()->flour_balance_kg);
    }

    public function test_a_partial_payment_is_applied_to_the_oldest_unpaid_sale_first(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $older = $this->unpaidSale($customer, 30, '2026-11-01');
        $newer = $this->unpaidSale($customer, 50, '2026-11-10');

        $this->actingAs($owner)
            ->post(route('panel.customers.payments.store', $customer), ['amount' => '40', 'payment_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertSessionHas('success', 'تم تسجيل الدفعة بمبلغ '.money(40).'.');

        $this->assertTrue($older->fresh()->isPaid());
        $this->assertSame('30.00', $older->fresh()->paid_amount);
        $this->assertFalse($newer->fresh()->isPaid());
        $this->assertSame('10.00', $newer->fresh()->paid_amount);
        $this->assertSame(40.0, $customer->outstandingBalance());
        $this->assertDatabaseHas('payments', ['customer_id' => $customer->id, 'amount' => 40, 'created_by' => $owner->id]);
    }

    public function test_paying_the_exact_outstanding_with_cents_settles_every_sale(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->unpaidSale($customer, 0.1, '2026-11-01');
        $this->unpaidSale($customer, 0.2, '2026-11-02');

        $this->actingAs($owner)
            ->post(route('panel.customers.payments.store', $customer), ['amount' => '0.3', 'payment_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $customer->sales()->where('payment_status', 'unpaid')->count());
    }

    public function test_a_payment_cannot_exceed_the_outstanding_balance(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->unpaidSale($customer, 30, '2026-11-01');

        $this->actingAs($owner)
            ->post(route('panel.customers.payments.store', $customer), ['amount' => '30.01', 'payment_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertSessionHasErrors(['amount' => 'المبلغ أكبر من المبلغ المستحق على العميل ('.money(30).').']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(30.0, $customer->outstandingBalance());
    }

    public function test_payment_validation_failure(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->actingAs($owner)
            ->post(route('panel.customers.payments.store', $customer), ['amount' => '-5', 'payment_date' => '', 'submission_id' => $this->id()])
            ->assertSessionHasErrors(['amount', 'payment_date']);
    }

    public function test_owner_cannot_reach_another_bakerys_customer(): void
    {
        $owner = $this->owner();
        $foreign = $this->customer($this->owner(), 10);
        $this->unpaidSale($foreign, 30, '2026-11-01');

        $this->actingAs($owner)->get(route('panel.customers.show', $foreign))->assertNotFound();
        $this->actingAs($owner)
            ->post(route('panel.customers.deposits.store', $foreign), ['kg_amount' => '5', 'deposit_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('panel.customers.flour-sales.store', $foreign), ['kg_amount' => '5', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertNotFound();
        $this->actingAs($owner)
            ->post(route('panel.customers.payments.store', $foreign), ['amount' => '5', 'payment_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertNotFound();

        $this->assertSame('10.00', $foreign->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('flour_deposits', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_admin_cannot_use_the_customer_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = $this->customer($this->owner());

        $this->actingAs($admin)->get(route('panel.customers.show', $customer))->assertForbidden();
        $this->actingAs($admin)
            ->post(route('panel.customers.deposits.store', $customer), ['kg_amount' => '5', 'deposit_date' => '2026-11-20', 'submission_id' => $this->id()])
            ->assertForbidden();
        $this->assertDatabaseCount('flour_deposits', 0);
    }
}
