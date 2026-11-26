<?php

namespace Tests\Feature;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_page_is_the_react_landing(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_expired_subscription_page_is_react_and_only_for_owners(): void
    {
        $bakery = Bakery::create(['name' => 'مخبز', 'subscription_status' => Bakery::STATUS_INACTIVE]);
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($owner)->get(route('panel.sales.index'))->assertRedirect(route('panel.subscription.expired'));
        $this->actingAs($owner)->get(route('panel.subscription.expired'))
            ->assertInertia(fn (Assert $page) => $page->component('Owner/SubscriptionExpired'));
        $this->actingAs($admin)->get(route('panel.subscription.expired'))->assertForbidden();
    }
}
