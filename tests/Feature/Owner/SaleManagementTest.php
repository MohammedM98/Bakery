<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'customer_id' => $customer->id,
            'kg_amount' => 6,
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
        ])->assertRedirect(route('panel.sales.index'));

        $this->assertEquals(14, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseHas('sales', ['customer_id' => $customer->id, 'total_amount' => 12]);
    }

    public function test_sale_price_always_comes_from_bakery_settings_not_the_request(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        // Even if a malicious or stale request tries to submit its own price,
        // the server must ignore it and use the bakery's configured price.
        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 4,
            'price_per_kg' => 999,
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
        ])->assertRedirect(route('panel.sales.index'));

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

        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'customer_id' => $customer->id,
            'kg_amount' => 10,
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
        ])->assertSessionHasErrors('kg_amount');

        $this->assertEquals(3, $customer->fresh()->flour_balance_kg);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_owner_can_mark_a_sale_as_paid(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $sale = Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => 5,
            'total_amount' => 25,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);

        $this->actingAs($owner)
            ->post(route('panel.sales.mark-paid', $sale))
            ->assertSessionHas('status');

        $this->assertTrue($sale->fresh()->isPaid());
    }
}
