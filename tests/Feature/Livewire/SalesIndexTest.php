<?php

namespace Tests\Feature\Livewire;

use App\Livewire\SalesIndex;
use App\Models\Bakery;
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
            ->assertDispatched('toast', message: 'تم تأكيد استلام الدفع لهذه العملية بمبلغ '.money($sale->total_amount).'. لا يمكن التراجع عن هذا الإجراء.');

        $this->assertTrue($sale->fresh()->isPaid());
    }

    public function test_a_paid_sale_cannot_be_marked_paid_again(): void
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
            ->call('markPaid', $sale->id)
            ->assertStatus(409);

        // There is deliberately no "mark unpaid" action anywhere in this
        // component: once a sale is confirmed paid, it cannot be reverted.
        $this->assertFalse(method_exists(SalesIndex::class, 'markUnpaid'));
    }

    public function test_there_is_no_way_to_delete_a_sale(): void
    {
        // Sales are permanent records: once created, they can only be
        // marked paid, never deleted or reverted.
        $this->assertFalse(method_exists(SalesIndex::class, 'delete'));
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
