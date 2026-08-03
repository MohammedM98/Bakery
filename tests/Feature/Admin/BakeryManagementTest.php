<?php

namespace Tests\Feature\Admin;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BakeryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_super_admin_can_create_a_bakery_with_an_owner_account(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.bakeries.store'), [
            'name' => 'مخبز جديد',
            'owner_name' => 'مالك جديد',
            'owner_email' => 'newowner@example.com',
            'owner_password' => 'password123',
            'subscription_months' => 1,
        ])->assertRedirect(route('admin.bakeries.index'));

        $bakery = Bakery::where('name', 'مخبز جديد')->first();
        $this->assertNotNull($bakery);
        $this->assertTrue($bakery->isSubscriptionActive());

        $owner = User::where('email', 'newowner@example.com')->first();
        $this->assertSame($bakery->id, $owner->bakery_id);
        $this->assertSame(User::ROLE_BAKERY_OWNER, $owner->role);
    }

    public function test_super_admin_can_renew_a_subscription(): void
    {
        $admin = $this->makeSuperAdmin();

        $bakery = Bakery::create([
            'name' => 'مخبز منتهي',
            'subscription_status' => Bakery::STATUS_INACTIVE,
            'subscription_expires_at' => now()->subDays(5),
        ]);

        $this->actingAs($admin)->post(route('admin.bakeries.renew', $bakery), [
            'subscription_months' => 2,
        ])->assertRedirect();

        $bakery->refresh();
        $this->assertTrue($bakery->isSubscriptionActive());
        $this->assertTrue($bakery->subscription_expires_at->isFuture());
    }

    public function test_deleting_a_bakery_removes_its_owner_and_data(): void
    {
        $admin = $this->makeSuperAdmin();

        $bakery = Bakery::create([
            'name' => 'مخبز للحذف',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.bakeries.destroy', $bakery))
            ->assertRedirect(route('admin.bakeries.index'));

        $this->assertDatabaseMissing('bakeries', ['id' => $bakery->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
    }

    public function test_bakery_owner_cannot_access_admin_panel(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER]);

        $this->actingAs($owner)
            ->get(route('admin.bakeries.index'))
            ->assertForbidden();
    }
}
