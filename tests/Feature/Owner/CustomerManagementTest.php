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

    public function test_owner_can_create_a_customer(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('panel.customers.store'), [
            'name' => 'عميل تجريبي',
            'mobile_number' => '0500000000',
            'flour_balance_kg' => 10,
        ]);

        $customer = Customer::first();
        $response->assertRedirect(route('panel.customers.show', $customer));
        $this->assertSame($owner->bakery_id, $customer->bakery_id);
        $this->assertSame('10.00', $customer->flour_balance_kg);
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
            ->assertForbidden();
    }

    public function test_deleting_a_customer_removes_their_records(): void
    {
        $owner = $this->makeOwner();

        $customer = Customer::create([
            'bakery_id' => $owner->bakery_id,
            'name' => 'عميل للحذف',
            'mobile_number' => '0522222222',
        ]);

        $this->actingAs($owner)
            ->delete(route('panel.customers.destroy', $customer))
            ->assertRedirect(route('panel.customers.index'));

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
