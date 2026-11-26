<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeOwner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز الاختبار',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        return User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);
    }

    public function test_owner_cannot_view_a_customer_from_another_bakery(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();

        $foreignCustomer = Customer::create([
            'bakery_id' => $otherOwner->bakery_id,
            'name' => 'عميل آخر',
            'mobile_number' => '0511111111',
        ]);

        $this->actingAs($owner)
            ->get(route('panel.customers.show', $foreignCustomer))
            ->assertNotFound();
    }
}
