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
}
