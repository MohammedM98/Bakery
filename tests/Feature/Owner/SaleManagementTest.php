<?php

namespace Tests\Feature\Owner;

use App\Livewire\SaleCreate;
use App\Models\Bakery;
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

    public function test_sale_price_always_comes_from_bakery_settings(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        // The sale form has no price field at all — price_per_kg is always
        // derived server-side from the bakery's settings, never user input.
        Livewire::actingAs($owner)
            ->test(SaleCreate::class)
            ->set('kg_amount', '4')
            ->set('payment_status', Sale::STATUS_PAID)
            ->set('sale_date', now()->toDateString())
            ->call('save')
            ->assertRedirect(route('panel.sales.index'));

        $this->assertDatabaseHas('sales', [
            'sale_type' => Sale::TYPE_CASH,
            'price_per_kg' => $bakery->regular_price_per_kg,
            'total_amount' => 4 * $bakery->regular_price_per_kg,
        ]);
    }

    public function test_sale_records_the_buyer_name_and_mobile(): void
    {
        [$owner] = $this->makeOwnerWithBakery();

        Livewire::actingAs($owner)
            ->test(SaleCreate::class)
            ->set('buyer_name', 'خالد أحمد')
            ->set('buyer_mobile', '0544444444')
            ->set('kg_amount', '3')
            ->set('payment_status', Sale::STATUS_PAID)
            ->set('sale_date', now()->toDateString())
            ->call('save')
            ->assertRedirect(route('panel.sales.index'));

        $this->assertDatabaseHas('sales', [
            'buyer_name' => 'خالد أحمد',
            'buyer_mobile' => '0544444444',
            'customer_id' => null,
        ]);
    }

    public function test_the_general_sale_form_has_no_flour_exchange_option(): void
    {
        // Flour-exchange sales are only ever created from the customer's own
        // page (against their tracked flour balance) — the general "sell
        // bread" form has no sale_type or customer selection at all.
        $this->assertFalse(property_exists(SaleCreate::class, 'sale_type'));
        $this->assertFalse(property_exists(SaleCreate::class, 'customer_id'));
    }
}
