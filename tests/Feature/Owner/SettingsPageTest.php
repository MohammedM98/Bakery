<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(array $bakery = []): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 4.5,
            'flour_exchange_fee_per_kg' => 1,
            ...$bakery,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    public function test_settings_page_shows_the_bakery_prices(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Settings/Edit')
                ->where('settings', ['regular_price_per_kg' => '4.50', 'flour_exchange_fee_per_kg' => '1.00']));
    }

    public function test_owner_updates_only_their_own_bakery_prices(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->actingAs($owner)
            ->from(route('panel.settings.edit'))
            ->put(route('panel.settings.update'), [
                'regular_price_per_kg' => '5.25',
                'flour_exchange_fee_per_kg' => '1.5',
                'bakery_id' => $other->bakery_id,
                'name' => 'محاولة تغيير الاسم',
            ])
            ->assertRedirect(route('panel.settings.edit'))
            ->assertSessionHas('success');

        $bakery = $owner->bakery->fresh();
        $this->assertSame('5.25', $bakery->regular_price_per_kg);
        $this->assertSame('1.50', $bakery->flour_exchange_fee_per_kg);
        $this->assertSame('مخبز', $bakery->name);
        $this->assertSame('4.50', $other->bakery->fresh()->regular_price_per_kg);
    }

    public function test_prices_must_be_non_negative_numbers(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put(route('panel.settings.update'), ['regular_price_per_kg' => '-1', 'flour_exchange_fee_per_kg' => 'abc'])
            ->assertSessionHasErrors(['regular_price_per_kg', 'flour_exchange_fee_per_kg']);

        $this->assertSame('4.50', $owner->bakery->fresh()->regular_price_per_kg);
    }

    public function test_super_admin_and_expired_bakery_are_blocked(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->get(route('panel.settings.edit'))->assertForbidden();
        $this->actingAs($admin)->put(route('panel.settings.update'), ['regular_price_per_kg' => 1, 'flour_exchange_fee_per_kg' => 1])->assertForbidden();

        $expired = $this->owner(['subscription_expires_at' => now()->subDay()]);
        $this->actingAs($expired)->get(route('panel.settings.edit'))->assertRedirect(route('panel.subscription.expired'));
        $this->actingAs($expired)
            ->put(route('panel.settings.update'), ['regular_price_per_kg' => 9, 'flour_exchange_fee_per_kg' => 9])
            ->assertRedirect(route('panel.subscription.expired'));
        $this->assertSame('4.50', $expired->bakery->fresh()->regular_price_per_kg);
    }
}
