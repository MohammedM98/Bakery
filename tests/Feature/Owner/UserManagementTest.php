<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The owner's "المستخدمون" page: adding, editing, disabling staff (9-ب item 7). */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function staff(User $owner, array $attributes = []): User
    {
        return User::factory()->create(['role' => User::ROLE_BAKERY_STAFF, 'bakery_id' => $owner->bakery_id, 'permissions' => [], ...$attributes]);
    }

    public function test_index_lists_only_this_bakerys_staff(): void
    {
        $owner = $this->owner();
        $this->staff($owner, ['name' => 'سامي', 'permissions' => ['record-sale', 'view-customers']]);
        $this->staff($this->owner(), ['name' => 'موظف مخبز آخر']);

        $this->actingAs($owner)->get(route('panel.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Users/Index')
                ->has('users', 1) // not the owner, not the other bakery's staff
                ->where('users.0.name', 'سامي')
                ->where('users.0.permissions_count', 2)
                ->where('users.0.permissions', ['record-sale', 'view-customers'])
                ->has('users.0', 6)
                ->where('permissionsTotal', count(User::permissionKeys()))
                // For the add / edit dialogs.
                ->has('groups', count(User::PERMISSIONS)));
    }

    public function test_the_list_sends_the_permission_groups_for_the_dialogs(): void
    {
        $this->actingAs($this->owner())->get(route('panel.users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('groups', count(User::PERMISSIONS))
                ->where('groups.0.label', 'اللوحة')
                ->where('groups.0.permissions.0', ['key' => 'view-dashboard', 'label' => 'عرض اللوحة الرئيسية']));
    }

    public function test_owner_adds_staff_in_their_own_bakery(): void
    {
        $owner = $this->owner();
        $otherBakery = $this->owner()->bakery_id;

        $this->actingAs($owner)->post(route('panel.users.store'), [
            'name' => 'سامي',
            'email' => ' Sami@Example.test ',
            'password' => 'secret123',
            'permissions' => ['record-sale', 'view-customers'],
            // Never taken from the browser.
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $otherBakery,
            'is_active' => false,
        ])->assertRedirect(route('panel.users.index'))->assertSessionHas('success');

        $staff = User::where('email', 'sami@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_BAKERY_STAFF, $staff->role);
        $this->assertSame($owner->bakery_id, $staff->bakery_id);
        $this->assertTrue($staff->is_active);
        $this->assertSame(['record-sale', 'view-customers'], $staff->permissions);
        $this->assertTrue(Hash::check('secret123', $staff->password));
    }

    public function test_new_staff_start_with_no_permission(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('panel.users.store'), ['name' => 'سامي', 'email' => 'sami@example.test', 'password' => 'secret123', 'permissions' => []]);

        $staff = User::where('email', 'sami@example.test')->firstOrFail();
        $this->assertSame([], $staff->grantedPermissions());
    }

    public function test_store_validation(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('panel.users.store'), [
            'name' => '',
            'email' => $owner->email,
            'password' => 'short',
            'permissions' => ['delete-customers', 'not-a-permission'],
        ])->assertSessionHasErrors(['name', 'email', 'password', 'permissions.0', 'permissions.1']);

        $this->actingAs($owner)->post(route('panel.users.store'), ['name' => 'x', 'email' => 'x@example.test', 'password' => 'secret123'])
            ->assertSessionHasErrors('permissions');

        $this->assertSame(0, $owner->bakery->staff()->count());
    }

    public function test_owner_edits_staff_and_their_permissions_apply_on_the_next_request(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner, ['permissions' => ['view-customers']]);
        $hash = $staff->password;

        $this->actingAs($staff)->get(route('panel.sales.index'))->assertForbidden();

        $this->actingAs($owner)->get(route('panel.users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.0.permissions', ['view-customers'])->missing('users.0.password'));

        // An empty password keeps the current one.
        $this->actingAs($owner)->put(route('panel.users.update', $staff), [
            'name' => 'سامي المعدّل', 'email' => $staff->email, 'password' => '', 'permissions' => ['view-sales'],
        ])->assertRedirect(route('panel.users.index'));

        $staff->refresh();
        $this->assertSame('سامي المعدّل', $staff->name);
        $this->assertSame($hash, $staff->password);
        $this->actingAs($staff)->get(route('panel.sales.index'))->assertOk();
        $this->actingAs($staff)->get(route('panel.customers.index'))->assertForbidden();

        $this->actingAs($owner)->put(route('panel.users.update', $staff), [
            'name' => 'سامي', 'email' => $staff->email, 'password' => 'newpass123', 'permissions' => [],
        ]);
        $this->assertTrue(Hash::check('newpass123', $staff->fresh()->password));
    }

    public function test_owner_cannot_manage_other_bakeries_users_or_their_own_account_here(): void
    {
        $owner = $this->owner();
        $otherOwner = $this->owner();
        $theirStaff = $this->staff($otherOwner);

        $this->actingAs($owner);
        $this->put(route('panel.users.update', $theirStaff), ['name' => 'x', 'email' => 'x@example.test', 'permissions' => []])->assertNotFound();
        $this->post(route('panel.users.status', $theirStaff), ['active' => false])->assertNotFound();
        $this->put(route('panel.users.update', $otherOwner), ['name' => 'x', 'email' => 'x@example.test', 'permissions' => []])->assertNotFound();
        $this->post(route('panel.users.status', $owner), ['active' => false])->assertNotFound();

        $this->assertTrue($theirStaff->fresh()->is_active);
        $this->assertTrue($owner->fresh()->is_active);
        $this->assertNotSame('x', $otherOwner->fresh()->name);
    }

    public function test_disabling_logs_the_staff_member_out_on_their_next_request(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner, ['permissions' => ['view-customers']]);

        $this->actingAs($staff)->get(route('panel.customers.index'))->assertOk();

        $this->actingAs($owner)->post(route('panel.users.status', $staff), ['active' => false])->assertSessionHas('success');
        // Sending the wanted state twice (a double click) does not re-enable.
        $this->actingAs($owner)->post(route('panel.users.status', $staff), ['active' => false]);
        $this->assertFalse($staff->fresh()->is_active);

        $this->actingAs($staff->fresh())->get(route('panel.customers.index'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($owner)->post(route('panel.users.status', $staff), ['active' => true]);
        $this->assertTrue($staff->fresh()->is_active);
        $this->actingAs($staff->fresh())->get(route('panel.customers.index'))->assertOk();
    }

    public function test_status_validation(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner);

        $this->actingAs($owner)->post(route('panel.users.status', $staff), ['active' => 'maybe'])->assertSessionHasErrors('active');
        $this->assertTrue($staff->fresh()->is_active);
    }

    public function test_a_disabled_staff_member_cannot_log_in(): void
    {
        $staff = $this->staff($this->owner(), ['is_active' => false]);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]); // the same message as a wrong password
        $this->assertGuest();
    }

    public function test_login_throttling_applies_to_staff(): void
    {
        $staff = $this->staff($this->owner());

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $staff->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_staff_cannot_raise_their_own_role_or_permissions_through_the_profile(): void
    {
        $owner = $this->owner();
        $staff = $this->staff($owner, ['permissions' => ['view-customers']]);

        $this->actingAs($staff)->patch(route('profile.update'), [
            'name' => 'سامي',
            'email' => $staff->email,
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $this->owner()->bakery_id,
            'permissions' => User::permissionKeys(),
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('سامي', $staff->name);
        $this->assertSame(User::ROLE_BAKERY_STAFF, $staff->role);
        $this->assertSame($owner->bakery_id, $staff->bakery_id);
        $this->assertSame(['view-customers'], $staff->permissions);
    }

    public function test_staff_cannot_delete_their_own_account(): void
    {
        $staff = $this->staff($this->owner());

        $this->actingAs($staff)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->assertModelExists($staff);
    }

    public function test_deleting_a_bakery_removes_its_staff_and_the_admin_sees_only_owners(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $owner = $this->owner();
        $staff = $this->staff($owner);

        $this->actingAs($admin)->get(route('admin.bakeries.edit', $owner->bakery))
            ->assertInertia(fn (Assert $page) => $page->has('owners', 1)->where('owners.0.email', $owner->email));

        $this->actingAs($admin)->delete(route('admin.bakeries.destroy', $owner->bakery));
        $this->assertModelMissing($staff);
    }
}
