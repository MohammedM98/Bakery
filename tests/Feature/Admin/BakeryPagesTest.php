<?php

namespace Tests\Feature\Admin;

use App\Actions\CreateBakeryWithOwner;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BakeryPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    private function bakery(array $attributes = []): Bakery
    {
        return Bakery::create([
            'name' => 'مخبز السنابل',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addDays(10)->toDateString(),
            ...$attributes,
        ]);
    }

    private function ownerOf(Bakery $bakery): User
    {
        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function validStorePayload(array $overrides = []): array
    {
        return [
            'name' => 'مخبز جديد',
            'phone' => '0599000000',
            'address' => 'غزة',
            'subscription_months' => 3,
            'owner_name' => 'صاحب المخبز',
            'owner_email' => 'owner@example.com',
            'owner_password' => 'password123',
            ...$overrides,
        ];
    }

    public function test_index_lists_bakeries_with_status_and_days_left(): void
    {
        $bakery = $this->bakery();
        $owner = $this->ownerOf($bakery);
        $this->bakery(['name' => 'مخبز موقوف', 'subscription_status' => Bakery::STATUS_INACTIVE]);

        $this->actingAs($this->admin())
            ->get(route('admin.bakeries.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bakeries/Index')
                ->has('bakeries.data', 2)
                ->where('bakeries.total', 2)
                ->has('bakeries.data.1', fn (Assert $row) => $row
                    ->where('id', $bakery->id)
                    ->where('name', 'مخبز السنابل')
                    ->where('phone', null)
                    ->where('owner', ['name' => $owner->name, 'email' => $owner->email])
                    ->where('subscription_status', 'active')
                    ->where('is_active', true)
                    ->where('expires_at', now()->addDays(10)->toDateString())
                    ->where('days_left', 10))
                ->where('bakeries.data.0.is_active', false));
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.bakeries.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Bakeries/Create'));
    }

    public function test_admin_creates_a_bakery_with_its_owner_account(): void
    {
        Carbon::setTestNow('2026-11-26');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.bakeries.store'), $this->validStorePayload())
            ->assertRedirect(route('admin.bakeries.index'))
            ->assertSessionHas('success');

        $bakery = Bakery::where('name', 'مخبز جديد')->firstOrFail();
        $this->assertSame('2027-02-26', $bakery->subscription_expires_at->toDateString());
        $this->assertTrue($bakery->isSubscriptionActive());
        $this->assertSame($admin->id, $bakery->created_by);
        $this->assertSame('0.00', $bakery->regular_price_per_kg);

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame($bakery->id, $owner->bakery_id);
        $this->assertSame(User::ROLE_BAKERY_OWNER, $owner->role);
        $this->assertTrue(password_verify('password123', $owner->password));
    }

    public function test_creating_a_bakery_validates_input_in_arabic(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($this->admin())
            ->from(route('admin.bakeries.create'))
            ->post(route('admin.bakeries.store'), $this->validStorePayload([
                'name' => '',
                'subscription_months' => 25,
                'owner_email' => 'taken@example.com',
                'owner_password' => 'short',
            ]))
            ->assertRedirect(route('admin.bakeries.create'))
            ->assertSessionHasErrors(['name', 'subscription_months', 'owner_email', 'owner_password']);

        $this->assertSame('حقل اسم المخبز مطلوب.', session('errors')->first('name'));
        $this->assertSame(0, Bakery::count());
    }

    public function test_a_failed_owner_insert_rolls_back_the_bakery(): void
    {
        // The email is unique at the database level too; if the user insert fails, no orphan bakery remains.
        $this->expectException(QueryException::class);

        try {
            app(CreateBakeryWithOwner::class)->handle([
                ...$this->validStorePayload(),
                'owner_email' => User::factory()->create()->email,
            ], $this->admin());
        } finally {
            $this->assertSame(0, Bakery::count());
        }
    }

    public function test_edit_page_sends_only_the_needed_fields(): void
    {
        $bakery = $this->bakery(['phone' => '0599111111', 'address' => 'خانيونس']);
        $owner = $this->ownerOf($bakery);

        $this->actingAs($this->admin())
            ->get(route('admin.bakeries.edit', $bakery))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Bakeries/Edit')
                ->where('bakery', [
                    'subscription_status' => 'active',
                    'is_active' => true,
                    'expires_at' => $bakery->subscription_expires_at->toDateString(),
                    'days_left' => 10,
                    'id' => $bakery->id,
                    'name' => 'مخبز السنابل',
                    'phone' => '0599111111',
                    'address' => 'خانيونس',
                ])
                ->where('owners', [['name' => $owner->name, 'email' => $owner->email]]));
    }

    public function test_admin_updates_bakery_details(): void
    {
        $bakery = $this->bakery();

        $this->actingAs($this->admin())
            ->from(route('admin.bakeries.edit', $bakery))
            ->put(route('admin.bakeries.update', $bakery), ['name' => 'اسم جديد', 'phone' => '', 'address' => 'رفح'])
            ->assertRedirect(route('admin.bakeries.edit', $bakery))
            ->assertSessionHas('success');

        $bakery->refresh();
        $this->assertSame('اسم جديد', $bakery->name);
        $this->assertNull($bakery->phone);
        $this->assertSame('رفح', $bakery->address);
    }

    public function test_updating_a_bakery_requires_a_name(): void
    {
        $bakery = $this->bakery();

        $this->actingAs($this->admin())
            ->put(route('admin.bakeries.update', $bakery), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertSame('مخبز السنابل', $bakery->fresh()->name);
    }

    public function test_renewal_extends_a_running_subscription_from_its_expiry_date(): void
    {
        Carbon::setTestNow('2026-11-26');
        $bakery = $this->bakery(['subscription_expires_at' => '2026-12-10']);

        $this->actingAs($this->admin())
            ->post(route('admin.bakeries.renew', $bakery), ['months' => 2])
            ->assertSessionHas('success');

        $this->assertSame('2027-02-10', $bakery->fresh()->subscription_expires_at->toDateString());
    }

    public function test_renewal_of_an_expired_or_stopped_subscription_starts_today_and_reactivates(): void
    {
        Carbon::setTestNow('2026-11-26');
        $bakery = $this->bakery([
            'subscription_status' => Bakery::STATUS_INACTIVE,
            'subscription_expires_at' => '2026-11-01',
        ]);

        $this->actingAs($this->admin())->post(route('admin.bakeries.renew', $bakery), ['months' => 1]);

        $bakery->refresh();
        $this->assertSame('2026-12-26', $bakery->subscription_expires_at->toDateString());
        $this->assertTrue($bakery->isSubscriptionActive());
    }

    public function test_renewal_months_must_be_between_1_and_24(): void
    {
        $bakery = $this->bakery();
        $before = $bakery->subscription_expires_at->toDateString();

        foreach ([0, 25, 'abc', null] as $months) {
            $this->actingAs($this->admin())
                ->post(route('admin.bakeries.renew', $bakery), ['months' => $months])
                ->assertSessionHasErrors('months');
        }

        $this->assertSame($before, $bakery->fresh()->subscription_expires_at->toDateString());
    }

    public function test_toggle_stops_and_restarts_a_subscription(): void
    {
        $bakery = $this->bakery();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.bakeries.toggle-status', $bakery))->assertSessionHas('success', 'تم إيقاف الاشتراك.');
        $this->assertFalse($bakery->fresh()->isSubscriptionActive());

        $this->actingAs($admin)->post(route('admin.bakeries.toggle-status', $bakery))->assertSessionHas('success', 'تم تفعيل الاشتراك.');
        $this->assertTrue($bakery->fresh()->isSubscriptionActive());
    }

    public function test_deleting_a_bakery_removes_its_owner_and_data(): void
    {
        $bakery = $this->bakery();
        $owner = $this->ownerOf($bakery);
        $customer = Customer::create(['bakery_id' => $bakery->id, 'name' => 'عميل', 'mobile_number' => '0590000000']);
        $other = $this->bakery(['name' => 'مخبز آخر']);
        $otherOwner = $this->ownerOf($other);

        $this->actingAs($this->admin())
            ->delete(route('admin.bakeries.destroy', $bakery))
            ->assertRedirect(route('admin.bakeries.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('bakeries', ['id' => $bakery->id]);
        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('bakeries', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $otherOwner->id]);
    }

    public function test_bakery_owner_cannot_reach_any_admin_route(): void
    {
        $bakery = $this->bakery();
        $owner = $this->ownerOf($bakery);

        $this->actingAs($owner);
        $this->get(route('admin.bakeries.index'))->assertForbidden();
        $this->get(route('admin.bakeries.create'))->assertForbidden();
        $this->get(route('admin.bakeries.edit', $bakery))->assertForbidden();
        $this->post(route('admin.bakeries.store'), $this->validStorePayload())->assertForbidden();
        $this->put(route('admin.bakeries.update', $bakery), ['name' => 'x'])->assertForbidden();
        $this->post(route('admin.bakeries.renew', $bakery), ['months' => 12])->assertForbidden();
        $this->post(route('admin.bakeries.toggle-status', $bakery))->assertForbidden();
        $this->delete(route('admin.bakeries.destroy', $bakery))->assertForbidden();

        $this->assertSame('مخبز السنابل', $bakery->fresh()->name);
        $this->assertSame(1, Bakery::count());
    }

    public function test_guests_are_sent_to_login(): void
    {
        $bakery = $this->bakery();

        $this->post(route('admin.bakeries.store'), $this->validStorePayload())->assertRedirect(route('login'));
        $this->delete(route('admin.bakeries.destroy', $bakery))->assertRedirect(route('login'));
        $this->assertSame(1, Bakery::count());
    }
}
