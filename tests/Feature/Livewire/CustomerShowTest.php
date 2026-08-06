<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomerShow;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerShowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwnerWithBakery(): array
    {
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 2,
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        return [$owner, $bakery];
    }

    public function test_owner_can_take_bread_against_the_customer_flour_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333333',
            'flour_balance_kg' => 20,
        ]);

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('bread_kg_amount', '6')
            ->set('bread_sale_date', now()->toDateString())
            ->set('bread_payment_status', Sale::STATUS_PAID)
            ->call('takeBreadForFlour')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDispatched('close-modal');

        $this->assertEquals(14, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('sales', [
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'kg_amount' => 6,
            'price_per_kg' => $bakery->flour_exchange_fee_per_kg,
            'total_amount' => 12,
            'payment_status' => Sale::STATUS_PAID,
        ]);
    }

    public function test_taking_bread_rejects_insufficient_flour_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333334',
            'flour_balance_kg' => 3,
        ]);

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('bread_kg_amount', '10')
            ->set('bread_sale_date', now()->toDateString())
            ->set('bread_payment_status', Sale::STATUS_PAID)
            ->call('takeBreadForFlour')
            ->assertHasErrors('bread_kg_amount');

        $this->assertEquals(3, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_taking_bread_can_be_recorded_as_unpaid(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333335',
            'flour_balance_kg' => 10,
        ]);

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('bread_kg_amount', '4')
            ->set('bread_sale_date', now()->toDateString())
            ->set('bread_payment_status', Sale::STATUS_UNPAID)
            ->call('takeBreadForFlour')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'customer_id' => $customer->id,
            'payment_status' => Sale::STATUS_UNPAID,
            'paid_at' => null,
        ]);
    }

    public function test_adding_a_flour_deposit_does_not_trigger_bread_form_validation(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333336',
            'flour_balance_kg' => 0,
        ]);

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('kg_amount', '5')
            ->set('deposit_date', now()->toDateString())
            ->call('addDeposit')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDispatched('close-modal');

        $this->assertEquals(5, $customer->fresh()->flour_balance_kg);
    }

    public function test_a_partial_payment_is_applied_to_the_oldest_unpaid_sale_first(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'محمد',
            'mobile_number' => '0533333337',
        ]);

        $older = Sale::create([
            'bakery_id' => $bakery->id, 'customer_id' => $customer->id, 'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 10, 'price_per_kg' => 10, 'total_amount' => 100, 'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->subDays(2)->toDateString(),
        ]);

        $newer = Sale::create([
            'bakery_id' => $bakery->id, 'customer_id' => $customer->id, 'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 20, 'price_per_kg' => 10, 'total_amount' => 200, 'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->toDateString(),
        ]);

        $this->assertEquals(300, $customer->outstandingBalance());

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('payment_amount', '120')
            ->set('payment_date', now()->toDateString())
            ->call('recordPayment')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertDispatched('close-modal');

        $older->refresh();
        $newer->refresh();

        $this->assertTrue($older->isPaid());
        $this->assertEquals(100, $older->paid_amount);

        $this->assertFalse($newer->isPaid());
        $this->assertTrue($newer->isPartiallyPaid());
        $this->assertEquals(20, $newer->paid_amount);
        $this->assertEquals(180, $newer->remainingAmount());

        $this->assertEquals(180, $customer->outstandingBalance());
        $this->assertDatabaseHas('payments', ['customer_id' => $customer->id, 'amount' => 120]);
    }

    public function test_a_payment_cannot_exceed_the_outstanding_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'محمد',
            'mobile_number' => '0533333338',
        ]);

        Sale::create([
            'bakery_id' => $bakery->id, 'customer_id' => $customer->id, 'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 10, 'price_per_kg' => 10, 'total_amount' => 100, 'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(CustomerShow::class, ['customer' => $customer])
            ->set('payment_amount', '150')
            ->set('payment_date', now()->toDateString())
            ->call('recordPayment')
            ->assertHasErrors('payment_amount');

        $this->assertEquals(100, $customer->outstandingBalance());
        $this->assertDatabaseCount('payments', 0);
    }
}
