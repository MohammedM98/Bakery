<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SalesIndex;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwnerWithBakery(): array
    {
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        return [$owner, $bakery];
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

        Livewire::actingAs($owner)
            ->test(SalesIndex::class)
            ->call('markPaid', $sale->id)
            ->assertSet('message', "تم تأكيد استلام الدفع لهذه العملية بمبلغ {$sale->total_amount}.");

        $this->assertTrue($sale->fresh()->isPaid());
    }

    public function test_owner_can_mark_a_sale_as_unpaid(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $sale = Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => 5,
            'total_amount' => 25,
            'payment_status' => Sale::STATUS_PAID,
            'paid_at' => now(),
            'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(SalesIndex::class)
            ->call('markUnpaid', $sale->id);

        $this->assertFalse($sale->fresh()->isPaid());
    }

    public function test_deleting_a_flour_exchange_sale_restores_the_customer_balance(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        $customer = Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'عميل القمح',
            'mobile_number' => '0533333333',
            'flour_balance_kg' => 10,
        ]);

        $sale = Sale::create([
            'bakery_id' => $bakery->id,
            'customer_id' => $customer->id,
            'sale_type' => Sale::TYPE_FLOUR_EXCHANGE,
            'kg_amount' => 4,
            'price_per_kg' => 2,
            'total_amount' => 8,
            'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(SalesIndex::class)
            ->call('delete', $sale->id);

        $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
        $this->assertEquals(14, $customer->fresh()->flour_balance_kg);
    }

    public function test_owner_cannot_act_on_a_sale_from_another_bakery(): void
    {
        [$owner] = $this->makeOwnerWithBakery();
        [, $otherBakery] = $this->makeOwnerWithBakery();

        $foreignSale = Sale::create([
            'bakery_id' => $otherBakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 5,
            'price_per_kg' => 5,
            'total_amount' => 25,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($owner)
            ->test(SalesIndex::class)
            ->call('markPaid', $foreignSale->id);
    }

    public function test_status_filter_narrows_the_list(): void
    {
        [$owner, $bakery] = $this->makeOwnerWithBakery();

        Sale::create([
            'bakery_id' => $bakery->id, 'sale_type' => Sale::TYPE_CASH, 'kg_amount' => 1,
            'price_per_kg' => 7, 'total_amount' => 7, 'payment_status' => Sale::STATUS_PAID,
            'sale_date' => now()->toDateString(),
        ]);

        Sale::create([
            'bakery_id' => $bakery->id, 'sale_type' => Sale::TYPE_CASH, 'kg_amount' => 2,
            'price_per_kg' => 5, 'total_amount' => 10, 'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(SalesIndex::class)
            ->set('status', Sale::STATUS_UNPAID)
            ->assertSee('10.00')
            ->assertDontSee('7.00');
    }
}
