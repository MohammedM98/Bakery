<?php

namespace Tests\Feature;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_with_inactive_subscription_is_redirected_to_expired_page(): void
    {
        $bakery = Bakery::create([
            'name' => 'مخبز متوقف',
            'subscription_status' => Bakery::STATUS_INACTIVE,
            'subscription_expires_at' => now()->subDay(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertRedirect(route('panel.subscription.expired'));
    }

    public function test_owner_with_active_subscription_can_reach_dashboard(): void
    {
        $bakery = Bakery::create([
            'name' => 'مخبز نشط',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $owner = User::factory()->create([
            'role' => User::ROLE_BAKERY_OWNER,
            'bakery_id' => $bakery->id,
        ]);

        $this->actingAs($owner)
            ->get(route('panel.dashboard'))
            ->assertOk();
    }
}
