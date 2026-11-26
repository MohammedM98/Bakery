<?php

namespace Tests\Feature\Auth;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('auth.user', null));
    }

    public function test_there_is_no_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'x@y.test'])->assertNotFound();
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_inertia_login_is_a_plain_redirect_without_a_409(): void
    {
        $user = User::factory()->create();

        $this->withHeaders(['X-Inertia' => 'true'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertHeaderMissing('X-Inertia-Location');

        $this->assertAuthenticatedAs($user);
    }

    public function test_session_is_regenerated_on_login(): void
    {
        $user = User::factory()->create();
        $this->startSession();
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'password', '_token' => csrf_token()]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_super_admin_lands_on_bakeries_and_owner_on_dashboard(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.bakeries.index'));

        $bakery = Bakery::create(['name' => 'مخبز', 'subscription_status' => Bakery::STATUS_ACTIVE, 'subscription_expires_at' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
        $this->actingAs($owner)->get('/dashboard')->assertRedirect(route('panel.dashboard'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unknown_email_and_wrong_password_get_the_same_message(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    public function test_login_is_locked_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_shared_props_expose_only_the_layout_fields(): void
    {
        $bakery = Bakery::create(['name' => 'مخبز التجربة', 'subscription_status' => Bakery::STATUS_ACTIVE, 'subscription_expires_at' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);

        $this->actingAs($owner)->get('/profile')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                    'role' => User::ROLE_BAKERY_OWNER,
                ])
                ->where('auth.bakery', ['name' => 'مخبز التجربة'])
                ->has('flash')
                ->etc());
    }
}
