<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The customer page and its actions: flour deposit, bread against flour, payment, adjustment. */
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

    /** A customer created through the page, so the opening flour balance is a movement. */
    private function customer(User $owner, float $flourCredit = 0): Customer
    {
        $this->actingAs($owner)->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(),
            'name' => 'أحمد', 'mobile_number' => '0591111111', 'notes' => 'زبون قديم', 'flour_balance_kg' => (string) $flourCredit,
        ]);

        return Customer::where('bakery_id', $owner->bakery_id)->latest('id')->first();
    }

    private function send(User $user, string $route, Customer $customer, array $data)
    {
        return $this->actingAs($user)->post(route($route, $customer), [...$data, 'submission_id' => fake()->uuid()]);
    }

    private function breadForFlour(User $owner, Customer $customer, string $kg, string $status = 'unpaid', string $date = '2026-11-20')
    {
        return $this->send($owner, 'panel.customers.flour-sales.store', $customer, ['kg_amount' => $kg, 'payment_status' => $status, 'sale_date' => $date]);
    }

    private function assertBalancesMatchMovements(Customer $customer): void
    {
        $customer->refresh();
        $flour = Movement::where('customer_id', $customer->id)->where('ledger', 'flour')->sum('amount');
        $this->assertSame(Movement::cents($flour), Movement::cents($customer->flour_balance_kg), 'cached flour balance = sum of flour movements');

        $unpaid = $customer->sales()->where('payment_status', 'unpaid')->get()->sum(fn (Sale $s) => Movement::cents($s->remainingAmount()));
        $this->assertLessThanOrEqual(max(0, $customer->balanceCents('money')), $unpaid, 'unpaid sales never claim more than the money balance');
    }

    public function test_show_renders_the_balances_and_both_statements(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 12.5);
        $this->breadForFlour($owner, $customer, '2', 'unpaid', local_today()->toDateString());

        $this->actingAs($owner)
            ->get(route('panel.customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Customers/Show')
                ->where('customer.flour_balance_kg', '-10.50')
                ->where('customer.money_balance', '3.00')
                ->where('range', ['from' => now()->startOfMonth()->toDateString(), 'to' => local_today()->toDateString()])
                ->where('flourStatement.opening', '0.00')
                ->where('flourStatement.rows.0.description', 'رصيد افتتاحي')
                ->where('flourStatement.rows.0.credit', '12.50')
                ->where('flourStatement.rows.1.description', 'خبز مقابل طحين 2 كغ')
                ->where('flourStatement.rows.1.debit', '2.00')
                ->where('flourStatement.rows.1.balance', '-10.50')
                ->where('flourStatement.closing', '-10.50')
                ->where('moneyStatement.rows.0.description', 'رسم تبديل طحين 2 كغ × 1.50 شيكل')
                ->where('moneyStatement.rows.0.number', 3)
                ->where('moneyStatement.rows.0.recorded_by', $owner->name)
                ->where('moneyStatement.rows.0.transfer', null)
                ->where('moneyStatement.closing', '3.00')
                ->where('canAdjust', true)
                ->missing('deposits')
                ->has('sales', 1));
    }

    public function test_the_statement_opens_with_the_balance_before_the_range(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '50', 'deposit_date' => '2026-09-10']);
        $this->breadForFlour($owner, $customer, '30', 'unpaid', '2026-10-05');
        $this->breadForFlour($owner, $customer, '5', 'unpaid', '2026-11-02');

        $this->actingAs($owner)
            ->get(route('panel.customers.show', [$customer, 'from' => '2026-10-01', 'to' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', ['from' => '2026-10-01', 'to' => '2026-10-31'])
                ->where('flourStatement.opening', '-50.00')
                ->has('flourStatement.rows', 1)
                ->where('flourStatement.rows.0.balance', '-20.00')
                ->where('flourStatement.closing', '-20.00'));

        // "All": no start date, no end date; the closing balance is the current balance.
        $this->actingAs($owner)
            ->get(route('panel.customers.show', [$customer, 'from' => '', 'to' => '']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', ['from' => null, 'to' => null])
                ->where('flourStatement.opening', '0.00')
                ->where('flourStatement.closing', '-15.00')
                ->where('customer.flour_balance_kg', '-15.00'));
    }

    /** The owner's worked example from 9-ب. */
    public function test_the_worked_example(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '50', 'deposit_date' => '2026-11-01'])
            ->assertSessionHas('success', 'تم تسجيل إيداع الطحين في حساب الزبون.');
        $this->breadForFlour($owner, $customer, '30', 'unpaid', '2026-11-02')
            ->assertSessionHas('success', 'تم تسجيل تسليم الخبز مقابل الطحين بنجاح.');
        $this->breadForFlour($owner, $customer, '40', 'unpaid', '2026-11-03')->assertSessionHasNoErrors();
        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '100', 'payment_date' => '2026-11-04'])
            ->assertSessionHas('success', 'تم تسجيل الدفعة بمبلغ '.money(100).'.');
        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '60', 'deposit_date' => '2026-11-05']);

        $flour = $customer->statement('flour', null, null)['rows'];
        $money = $customer->statement('money', null, null)['rows'];

        $this->assertSame(['-50.00', '-20.00', '20.00', '-40.00'], array_column($flour, 'balance'));
        // Fees 30 × 1.5 = 45 and 40 × 1.5 = 60, then 100 paid: 45 → 105 → 5.
        $this->assertSame(['45.00', '105.00', '5.00'], array_column($money, 'balance'));
        $this->assertSame('نقدًا', $money[2]['transfer']);
        $this->assertSame('دفعة', $money[2]['description']);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], Movement::orderBy('number')->pluck('number')->all());
        // The payment settled the older sale and part of the newer one.
        $this->assertSame(['paid', 'unpaid'], $customer->sales()->oldest('id')->pluck('payment_status')->all());
        $this->assertSame('5.00', number_format($customer->sales()->latest('id')->first()->remainingAmount(), 2));
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_bread_against_flour_paid_now_writes_three_movements(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 20);

        $this->breadForFlour($owner, $customer, '8', 'paid');

        $this->assertSame('-12.00', $customer->fresh()->flour_balance_kg);
        $this->assertSame(0, $customer->balanceCents('money'));
        $sale = Sale::sole();
        $this->assertSame(['paid', '12.00', '12.00'], [$sale->payment_status, $sale->total_amount, $sale->paid_amount]);
        $this->assertSame(
            [['flour', 'flour_withdrawal', '8.00'], ['money', 'exchange_fee', '12.00'], ['money', 'payment', '-12.00']],
            Movement::where('sale_id', $sale->id)->orderBy('number')->get()->map(fn ($m) => [$m->ledger, $m->type, $m->amount])->all(),
        );
        $this->assertSame('دفعة عن بيع 20/11/2026', Movement::where('type', 'payment')->value('description'));
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_bread_against_flour_is_not_refused_when_the_balance_is_short(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, 3);

        $this->breadForFlour($owner, $customer, '10')->assertSessionHasNoErrors();

        $this->assertSame('7.00', $customer->fresh()->flour_balance_kg);
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_a_deposit_nets_against_flour_owed(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->breadForFlour($owner, $customer, '20');

        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '25', 'deposit_date' => '2026-11-21', 'notes' => 'كيس']);

        $this->assertSame('-5.00', $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('movements', ['type' => 'deposit', 'amount' => -25, 'note' => 'كيس', 'recorded_by' => $owner->id, 'recorded_by_name' => $owner->name]);
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_deposit_validation_does_not_write(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $before = Movement::count();

        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '', 'deposit_date' => 'not a date'])
            ->assertSessionHasErrors(['kg_amount', 'deposit_date']);

        $this->assertSame($before, Movement::count());
    }

    public function test_double_submits_write_one_movement_set(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $forms = [
            ['panel.customers.deposits.store', ['kg_amount' => '5', 'deposit_date' => '2026-11-20']],
            ['panel.customers.flour-sales.store', ['kg_amount' => '2', 'payment_status' => 'paid', 'sale_date' => '2026-11-20']],
            ['panel.customers.payments.store', ['amount' => '4', 'payment_date' => '2026-11-20']],
            ['panel.customers.adjustments.store', ['ledger' => 'money', 'direction' => 'debit', 'amount' => '1', 'reason' => 'خطأ', 'movement_date' => '2026-11-20']],
        ];

        foreach ($forms as [$route, $data]) {
            $data['submission_id'] = fake()->uuid();
            $this->actingAs($owner)->post(route($route, $customer), $data);
            $this->actingAs($owner)->post(route($route, $customer), $data);
        }

        $this->assertSame(1 + 3 + 1 + 1, Movement::count());
        $this->assertSame(1, Sale::count());
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_a_payment_may_exceed_what_is_owed(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->breadForFlour($owner, $customer, '20'); // 30.00 owed

        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '50', 'payment_date' => '2026-11-21'])
            ->assertSessionHasNoErrors();

        $this->assertSame(-2000, $customer->balanceCents('money'));
        $this->assertTrue(Sale::sole()->isPaid());

        // The credit pays the next unpaid sale as soon as it is recorded.
        $this->breadForFlour($owner, $customer, '10'); // 15.00
        $this->assertTrue(Sale::latest('id')->first()->isPaid());
        $this->assertSame(-500, $customer->balanceCents('money'));
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_a_payment_for_a_named_sale_pays_that_sale_first(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->breadForFlour($owner, $customer, '20', 'unpaid', '2026-11-01');
        $this->breadForFlour($owner, $customer, '10', 'unpaid', '2026-11-10');
        $newer = Sale::latest('id')->first();

        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '15', 'sale_id' => $newer->id, 'payment_date' => '2026-11-21']);

        $this->assertTrue($newer->fresh()->isPaid());
        $this->assertFalse(Sale::oldest('id')->first()->isPaid());
        $this->assertDatabaseHas('movements', ['type' => 'payment', 'sale_id' => $newer->id, 'description' => 'دفعة عن بيع 10/11/2026']);
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_a_payment_cannot_name_another_customers_sale(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $other = $this->customer($owner);
        $this->breadForFlour($owner, $other, '10');
        $before = Movement::count();

        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '5', 'sale_id' => Sale::sole()->id, 'payment_date' => '2026-11-21'])
            ->assertSessionHasErrors(['sale_id' => 'هذا البيع غير موجود في مبيعات هذا الزبون غير المدفوعة.']);

        $this->assertSame($before, Movement::count());
    }

    public function test_entries_cannot_be_dated_in_the_future(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $before = Movement::count();
        $tomorrow = now()->addDay()->toDateString();

        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '5', 'payment_date' => $tomorrow])->assertSessionHasErrors('payment_date');
        $this->send($owner, 'panel.customers.deposits.store', $customer, ['kg_amount' => '5', 'deposit_date' => $tomorrow])->assertSessionHasErrors('deposit_date');
        $this->breadForFlour($owner, $customer, '5', 'paid', $tomorrow)->assertSessionHasErrors('sale_date');

        $this->assertSame($before, Movement::count());
    }

    public function test_an_invalid_range_in_the_url_falls_back_to_this_month(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->actingAs($owner)
            ->get(route('panel.customers.show', [$customer, 'from' => '2026-02-31', 'to' => 'x']))
            ->assertInertia(fn (Assert $page) => $page->where('range', ['from' => now()->startOfMonth()->toDateString(), 'to' => local_today()->toDateString()]));
    }

    public function test_payment_validation_failure(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->send($owner, 'panel.customers.payments.store', $customer, ['amount' => '-5', 'payment_date' => ''])
            ->assertSessionHasErrors(['amount', 'payment_date']);
    }

    public function test_an_adjustment_corrects_a_balance_with_its_reason(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $this->breadForFlour($owner, $customer, '10');
        $withdrawal = Movement::where('type', 'flour_withdrawal')->sole();

        $this->send($owner, 'panel.customers.adjustments.store', $customer, [
            'ledger' => 'flour', 'direction' => 'credit', 'amount' => '2', 'reason' => 'وزن خاطئ', 'movement_date' => '2026-11-21', 'corrects_number' => $withdrawal->number,
        ])->assertSessionHas('success', 'تم تسجيل قيد التسوية.');

        $this->assertDatabaseHas('movements', [
            'type' => 'adjustment', 'ledger' => 'flour', 'amount' => -2, 'corrects_movement_id' => $withdrawal->id,
            'description' => 'تسوية: وزن خاطئ (تصحيح الحركة رقم '.$withdrawal->number.')',
        ]);
        $this->assertSame('8.00', $customer->fresh()->flour_balance_kg);

        // A money "له" adjustment lowers what the unpaid sales may claim.
        $this->send($owner, 'panel.customers.adjustments.store', $customer, [
            'ledger' => 'money', 'direction' => 'credit', 'amount' => '15', 'reason' => 'خصم', 'movement_date' => '2026-11-21',
        ]);
        $this->assertSame(0, $customer->balanceCents('money'));
        $this->assertTrue(Sale::sole()->isPaid());
        $this->assertBalancesMatchMovements($customer);
    }

    public function test_adjustment_validation_failure(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $other = $this->customer($owner);
        $this->breadForFlour($owner, $other, '1');
        $before = Movement::count();

        $this->send($owner, 'panel.customers.adjustments.store', $customer, ['ledger' => 'bank', 'direction' => 'up', 'amount' => '0', 'reason' => '', 'movement_date' => ''])
            ->assertSessionHasErrors(['ledger', 'direction', 'amount', 'reason', 'movement_date']);
        $this->send($owner, 'panel.customers.adjustments.store', $customer, [
            'ledger' => 'money', 'direction' => 'debit', 'amount' => '1', 'reason' => 'x', 'movement_date' => '2026-11-21',
            'corrects_number' => Movement::where('customer_id', $other->id)->value('number'),
        ])->assertSessionHasErrors(['corrects_number' => 'لا توجد حركة بهذا الرقم في حساب هذا الزبون.']);

        $this->assertSame($before, Movement::count());
    }

    public function test_owner_cannot_reach_another_bakerys_customer(): void
    {
        $owner = $this->owner();
        $foreignOwner = $this->owner();
        $foreign = $this->customer($foreignOwner, 10);
        $before = Movement::count();

        $this->actingAs($owner)->get(route('panel.customers.show', $foreign))->assertNotFound();
        $this->send($owner, 'panel.customers.deposits.store', $foreign, ['kg_amount' => '5', 'deposit_date' => '2026-11-20'])->assertNotFound();
        $this->breadForFlour($owner, $foreign, '5')->assertNotFound();
        $this->send($owner, 'panel.customers.payments.store', $foreign, ['amount' => '5', 'payment_date' => '2026-11-20'])->assertNotFound();
        $this->send($owner, 'panel.customers.adjustments.store', $foreign, ['ledger' => 'money', 'direction' => 'debit', 'amount' => '1', 'reason' => 'x', 'movement_date' => '2026-11-20'])
            ->assertNotFound();

        $this->assertSame('-10.00', $foreign->fresh()->flour_balance_kg);
        $this->assertSame($before, Movement::count());
        $this->assertSame(0, Sale::count());
    }

    public function test_admin_cannot_use_the_customer_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = $this->customer($this->owner());
        $before = Movement::count();

        $this->actingAs($admin)->get(route('panel.customers.show', $customer))->assertForbidden();
        $this->send($admin, 'panel.customers.deposits.store', $customer, ['kg_amount' => '5', 'deposit_date' => '2026-11-20'])->assertForbidden();
        $this->send($admin, 'panel.customers.adjustments.store', $customer, ['ledger' => 'money', 'direction' => 'debit', 'amount' => '1', 'reason' => 'x', 'movement_date' => '2026-11-20'])
            ->assertForbidden();
        $this->assertSame($before, Movement::count());
    }
}
