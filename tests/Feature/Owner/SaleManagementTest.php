<?php

namespace Tests\Feature\Owner;

use App\Livewire\SaleCreate;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SaleManagementTest extends TestCase
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

    public function test_flour_exchange_sale_deducts_customer_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333333',
            'flour_balance_kg' => 20,
        ]);

        Livewire::actingAs($owner)
            ->test(SaleCreate::class)
            ->set('sale_type', Sale::TYPE_FLOUR_EXCHANGE)
            ->set('customer_id', $customer->id)
            ->set('kg_amount', '6')
            ->set('payment_status', Sale::STATUS_PAID)
            ->set('sale_date', now()->toDateString())
            ->call('save')
            ->assertRedirect(route('panel.sales.index'));

        $this->assertEquals(14, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('sales', ['customer_id' => $customer->id, 'total_amount' => 12]);
    }

    public function test_sale_price_always_comes_from_bakery_settings(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        // The sale form has no price field at all — price_per_kg is always
        // derived server-side from the bakery's settings, never user input.
        Livewire::actingAs($owner)
            ->test(SaleCreate::class)
            ->set('sale_type', Sale::TYPE_CASH)
            ->set('kg_amount', '4')
            ->set('payment_status', Sale::STATUS_PAID)
            ->set('sale_date', now()->toDateString())
            ->call('save')
            ->assertRedirect(route('panel.sales.index'));

        $this->assertDatabaseHas('sales', [
            'price_per_kg' => $bakery->regular_price_per_kg,
            'total_amount' => 4 * $bakery->regular_price_per_kg,
        ]);
    }

    public function test_flour_exchange_sale_rejects_insufficient_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333334',
            'flour_balance_kg' => 3,
        ]);

        Livewire::actingAs($owner)
            ->test(SaleCreate::class)
            ->set('sale_type', Sale::TYPE_FLOUR_EXCHANGE)
            ->set('customer_id', $customer->id)
            ->set('kg_amount', '10')
            ->set('payment_status', Sale::STATUS_PAID)
            ->set('sale_date', now()->toDateString())
            ->call('save')
            ->assertHasErrors('kg_amount');

        $this->assertEquals(3, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('sales', 0);
    }
}
