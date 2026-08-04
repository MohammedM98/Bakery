<?php

namespace Tests\Feature\Livewire;

use App\Livewire\CustomersIndex;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_narrows_the_customer_list_live(): void
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

        Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'أحمد المالكي',
            'mobile_number' => '0501111111',
        ]);

        Customer::create([
            'bakery_id' => $bakery->id,
            'name' => 'سالم القحطاني',
            'mobile_number' => '0502222222',
        ]);

        Livewire::actingAs($owner)
            ->test(CustomersIndex::class)
            ->assertSee('أحمد المالكي')
            ->assertSee('سالم القحطاني')
            ->set('search', 'أحمد')
            ->assertSee('أحمد المالكي')
            ->assertDontSee('سالم القحطاني');
    }

    public function test_customers_from_other_bakeries_never_appear(): void
    {
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $otherBakery = Bakery::create([
            'name' => 'مخبز آخر',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        Customer::create([
            'bakery_id' => $otherBakery->id,
            'name' => 'عميل مخبز آخر',
            'mobile_number' => '0509999999',
        ]);

        Livewire::actingAs($owner)
            ->test(CustomersIndex::class)
            ->assertDontSee('عميل مخبز آخر');
    }
}
