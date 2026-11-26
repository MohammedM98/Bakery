<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_general_sale_form_ignores_a_flour_exchange_type_or_customer(): void
    {
        // Flour-exchange sales are only ever created from the customer's own page
        // (against their flour balance); the general sale route takes no sale type,
        // customer or price from the browser.
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 2,
        ]);
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
        $customer = Customer::create(['bakery_id' => $bakery->id, 'name' => 'زبون', 'mobile_number' => '0500000000']);

        $this->actingAs($owner)->post(route('panel.sales.store'), [
            'kg_amount' => '3',
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'customer_id' => $customer->id,
            'price_per_kg' => '1',
            'submission_id' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('sales', [
            'sale_type' => Sale::TYPE_CASH,
            'customer_id' => null,
            'price_per_kg' => '5.00',
            'total_amount' => '15.00',
        ]);
    }
}
