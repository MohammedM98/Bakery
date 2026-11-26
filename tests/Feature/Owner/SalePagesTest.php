<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalePagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 2,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function sale(User $owner, array $attributes = []): Sale
    {
        return Sale::create([
            'bakery_id' => $owner->bakery_id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => 5,
            'total_amount' => 25,
            'paid_amount' => 0,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
            ...$attributes,
        ]);
    }

    private function validSale(array $overrides = []): array
    {
        return [
            'kg_amount' => '4',
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
            'submission_id' => fake()->uuid(),
            ...$overrides,
        ];
    }

    public function test_index_shows_this_bakerys_cash_sales_with_only_the_needed_fields(): void
    {
        $owner = $this->owner();
        $sale = $this->sale($owner, ['buyer_name' => 'خالد', 'buyer_mobile' => '0544444444', 'paid_amount' => 10]);
        $this->sale($owner, ['sale_type' => Sale::TYPE_FLOUR_EXCHANGE]);
        $this->sale($this->owner());

        $this->actingAs($owner)
            ->get(route('panel.sales.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Sales/Index')
                ->where('filters', ['type' => 'cash', 'status' => '', 'date' => ''])
                ->where('pricePerKg', '5.00')
                ->where('sales.total', 1)
                ->where('sales.data.0', [
                    'id' => $sale->id,
                    'sale_date' => now()->toDateString(),
                    'sale_type' => 'cash',
                    'customer_id' => null,
                    'buyer' => 'خالد',
                    'buyer_mobile' => '0544444444',
                    'kg_amount' => '5.00',
                    'total_amount' => '25.00',
                    'remaining_amount' => '15.00',
                    'payment_status' => 'unpaid',
                    'is_partially_paid' => true,
                ]));
    }

    public function test_cash_and_flour_exchange_sales_are_on_separate_tabs(): void
    {
        $owner = $this->owner();
        $customer = Customer::create(['bakery_id' => $owner->bakery_id, 'name' => 'أحمد', 'mobile_number' => '0591111111']);
        $this->sale($owner);
        $flour = $this->sale($owner, ['sale_type' => Sale::TYPE_FLOUR_EXCHANGE, 'customer_id' => $customer->id]);

        $this->actingAs($owner)
            ->get(route('panel.sales.index', ['type' => 'flour_exchange']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.type', 'flour_exchange')
                ->has('sales.data', 1)
                ->where('sales.data.0.id', $flour->id)
                ->where('sales.data.0.buyer', 'أحمد'));
    }

    public function test_status_and_date_filters_narrow_the_list_and_bad_values_are_ignored(): void
    {
        $owner = $this->owner();
        $this->sale($owner, ['payment_status' => Sale::STATUS_PAID, 'paid_amount' => 25]);
        $unpaid = $this->sale($owner);
        $this->sale($owner, ['sale_date' => now()->subDays(3)->toDateString()]);

        $this->actingAs($owner)
            ->get(route('panel.sales.index', ['status' => 'unpaid', 'date' => now()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('sales.data', 1)->where('sales.data.0.id', $unpaid->id));

        $this->actingAs($owner)
            ->get(route('panel.sales.index', ['status' => 'bogus', 'type' => 'bogus', 'date' => 'x']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters', ['type' => 'cash', 'status' => '', 'date' => ''])
                ->where('sales.total', 3));
    }

    public function test_create_page_renders_with_the_price(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.sales.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Owner/Sales/Create')->where('pricePerKg', '5.00'));
    }

    public function test_store_prices_the_sale_from_the_bakery_settings_and_keeps_the_walk_in_buyer(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->from(route('panel.sales.index'))
            ->post(route('panel.sales.store'), $this->validSale([
                'buyer_name' => 'خالد أحمد',
                'buyer_mobile' => '0544444444',
                'price_per_kg' => 1, // ignored: never accepted from the browser
                'bakery_id' => 999,
            ]))
            ->assertRedirect(route('panel.sales.index'))
            ->assertSessionHas('success', 'تم تسجيل عملية البيع بنجاح.');

        $this->assertDatabaseHas('sales', [
            'bakery_id' => $owner->bakery_id,
            'customer_id' => null,
            'buyer_name' => 'خالد أحمد',
            'buyer_mobile' => '0544444444',
            'sale_type' => Sale::TYPE_CASH,
            'price_per_kg' => 5,
            'total_amount' => 20,
            'paid_amount' => 20,
            'payment_status' => Sale::STATUS_PAID,
            'created_by' => $owner->id,
        ]);
    }

    public function test_an_unpaid_sale_has_nothing_paid(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('panel.sales.store'), $this->validSale(['payment_status' => 'unpaid']));

        $sale = Sale::sole();
        $this->assertSame('0.00', $sale->paid_amount);
        $this->assertNull($sale->paid_at);
        $this->assertSame('20.00', $sale->total_amount);
    }

    public function test_store_rejects_invalid_input_with_arabic_messages(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.sales.store'), $this->validSale(['kg_amount' => '0', 'payment_status' => 'maybe', 'submission_id' => '']))
            ->assertSessionHasErrors(['kg_amount', 'payment_status', 'submission_id']);

        $this->assertDatabaseCount('sales', 0);
        $this->assertStringContainsString('الكمية', session('errors')->first('kg_amount'));
    }

    public function test_a_quantity_too_large_for_the_amount_columns_is_a_validation_error(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.sales.store'), $this->validSale(['kg_amount' => '50000000']))
            ->assertSessionHasErrors('kg_amount');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_a_quantity_with_more_than_two_decimals_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->post(route('panel.sales.store'), $this->validSale(['kg_amount' => '1.555']))
            ->assertSessionHasErrors(['kg_amount' => 'يجب ألا تتجاوز منازل الكمية العشرية منزلتين.']);
    }

    public function test_a_double_submitted_sale_is_saved_once(): void
    {
        $owner = $this->owner();
        $data = $this->validSale();

        $this->actingAs($owner)->post(route('panel.sales.store'), $data)->assertSessionHas('success');
        $this->actingAs($owner)->post(route('panel.sales.store'), $data)->assertRedirect();

        $this->assertDatabaseCount('sales', 1);
    }

    public function test_mark_paid_confirms_the_rest_once(): void
    {
        $owner = $this->owner();
        $sale = $this->sale($owner, ['paid_amount' => 10]);

        $this->actingAs($owner)
            ->post(route('panel.sales.mark-paid', $sale))
            ->assertSessionHas('success', 'تم تأكيد استلام المتبقي وقدره '.money(15).'. لا يمكن التراجع عن هذا الإجراء.');

        $sale->refresh();
        $this->assertTrue($sale->isPaid());
        $this->assertSame('25.00', $sale->paid_amount);
        $this->assertNotNull($sale->paid_at);

        $paidAt = $sale->paid_at;
        $this->travel(1)->hours();
        $this->actingAs($owner)
            ->post(route('panel.sales.mark-paid', $sale))
            ->assertSessionHas('error', 'هذه العملية مدفوعة مسبقًا.');
        $this->assertEquals($paidAt, $sale->fresh()->paid_at);
    }

    public function test_owner_cannot_see_or_touch_another_bakerys_sale(): void
    {
        $owner = $this->owner();
        $foreign = $this->sale($this->owner());

        $this->actingAs($owner)->post(route('panel.sales.mark-paid', $foreign))->assertNotFound();
        $this->assertFalse($foreign->fresh()->isPaid());
    }

    public function test_admin_cannot_use_the_sales_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $sale = $this->sale($this->owner());

        $this->actingAs($admin)->get(route('panel.sales.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('panel.sales.store'), $this->validSale())->assertForbidden();
        $this->actingAs($admin)->post(route('panel.sales.mark-paid', $sale))->assertForbidden();
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_there_is_no_route_to_delete_or_edit_a_sale(): void
    {
        $owner = $this->owner();
        $sale = $this->sale($owner);

        $this->actingAs($owner)->delete('/panel/sales/'.$sale->id)->assertClientError();
        $this->actingAs($owner)->put('/panel/sales/'.$sale->id)->assertClientError();
        $this->assertModelExists($sale);
    }
}
