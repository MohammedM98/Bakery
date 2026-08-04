<?php

namespace Tests\Feature\Livewire;

use App\Livewire\DashboardUnpaidSales;
use App\Models\Bakery;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardUnpaidSalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_sale_paid_removes_it_from_the_widget(): void
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

        $sale = Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 3,
            'price_per_kg' => 6,
            'total_amount' => 18,
            'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(DashboardUnpaidSales::class)
            ->assertSee('18.00')
            ->call('markPaid', $sale->id)
            ->assertSee('لا توجد عمليات غير مدفوعة');

        $this->assertTrue($sale->fresh()->isPaid());
    }

    public function test_an_already_paid_sale_cannot_be_marked_paid_again(): void
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

        $sale = Sale::create([
            'bakery_id' => $bakery->id,
            'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 3,
            'price_per_kg' => 6,
            'total_amount' => 18,
            'payment_status' => Sale::STATUS_PAID,
            'paid_at' => now(),
            'sale_date' => now()->toDateString(),
        ]);

        Livewire::actingAs($owner)
            ->test(DashboardUnpaidSales::class)
            ->call('markPaid', $sale->id)
            ->assertStatus(409);
    }
}
