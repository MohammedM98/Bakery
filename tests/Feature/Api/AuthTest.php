<?php

namespace Tests\Feature\Api;

use App\Models\Bakery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Token sign-in (section 13): the website's rules, the role and subscription gates, and revocation. */
class AuthTest extends TestCase
{
    use ApiHelpers, RefreshDatabase;

    private function login(string $email, string $password = 'password'): TestResponse
    {
        return $this->api('POST', 'login', ['email' => $email, 'password' => $password, 'device_name' => 'هاتف سامي']);
    }

    public function test_login_returns_a_token_and_the_session_data(): void
    {
        $owner = $this->bakeryOwner();

        $response = $this->login($owner->email)->assertOk()
            ->assertJsonPath('user.role', User::ROLE_BAKERY_OWNER)
            ->assertJsonPath('bakery.name', 'مخبز النور')
            ->assertJsonPath('subscription.active', true)
            ->assertJsonPath('prices.regular_price_per_kg', '5.00')
            ->assertJsonPath('prices.regular_price_text', '5.00 شيكل')
            ->assertJsonPath('timezone', 'UTC')
            ->assertJsonPath('local_timezone', 'Asia/Hebron');

        $this->assertContains('manage-users', $response->json('permissions'));
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $this->assertSame('هاتف سامي', $owner->tokens()->first()->name);

        $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.id', $owner->id);
    }

    public function test_login_needs_the_device_name_and_answers_in_arabic(): void
    {
        $this->api('POST', 'login', ['email' => 'x@y.z', 'password' => 'p'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.device_name.0', 'حقل اسم الجهاز مطلوب.');
    }

    public function test_a_wrong_password_and_an_unknown_email_give_the_same_error(): void
    {
        $owner = $this->bakeryOwner();

        $wrong = $this->login($owner->email, 'wrong')->assertStatus(422)->json('errors.email.0');
        $unknown = $this->login('nobody@example.com')->assertStatus(422)->json('errors.email.0');

        $this->assertSame($wrong, $unknown);
        $this->assertSame(0, $owner->tokens()->count());
    }

    public function test_login_locks_after_five_failed_attempts(): void
    {
        $owner = $this->bakeryOwner();

        foreach (range(1, 5) as $i) {
            $this->login($owner->email, 'wrong')->assertStatus(422);
        }

        $this->login($owner->email)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->assertStringContainsString('ثانية', $this->login($owner->email)->json('errors.email.0'));
        $this->assertSame(0, $owner->tokens()->count());
    }

    public function test_a_disabled_account_cannot_log_in_and_its_tokens_stop_working(): void
    {
        $staff = $this->staffOf($this->bakeryOwner(), ['view-customers']);
        $token = $staff->createToken('هاتف')->plainTextToken;

        $staff->update(['is_active' => false]);

        $this->login($staff->email)->assertStatus(422);
        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'account_disabled');
        $this->assertSame(0, $staff->tokens()->count(), 'the token is revoked');
    }

    public function test_a_new_password_signs_out_every_phone(): void
    {
        $owner = $this->bakeryOwner();
        $staff = $this->staffOf($owner, ['view-customers']);
        $staff->createToken('هاتف');

        $this->as($owner)->api('PUT', "users/{$staff->id}", ['name' => $staff->name, 'email' => $staff->email, 'password' => 'new-password-1', 'permissions' => []])->assertOk();

        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_the_profile_changes_name_and_email_for_every_role(): void
    {
        $owner = $this->bakeryOwner();
        $this->as($owner)->api('PUT', 'profile', ['name' => 'أبو سامي', 'email' => 'NEW@bakery.test'])
            ->assertJsonValidationErrors(['email']);
        $this->as($owner)->api('PUT', 'profile', ['name' => 'أبو سامي', 'email' => 'new@bakery.test'])->assertOk()
            ->assertJsonPath('user.name', 'أبو سامي')
            ->assertJsonPath('user.email', 'new@bakery.test');

        $other = $this->admin();
        $this->as($other)->api('PUT', 'profile', ['name' => 'المالك', 'email' => 'new@bakery.test'])
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->as($other)->api('PUT', 'profile', ['name' => 'المالك', 'email' => $other->email])->assertOk();
    }

    public function test_a_password_change_keeps_this_phone_signed_in_and_signs_out_the_others(): void
    {
        $owner = $this->bakeryOwner();
        $phone = $owner->createToken('هاتف')->plainTextToken;
        $owner->createToken('جهاز لوحي');

        $this->withToken($phone)->putJson('/api/v1/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1', 'device_name' => 'هاتف'])
            ->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $fresh = $this->withToken($phone)->putJson('/api/v1/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1', 'device_name' => 'هاتف'])
            ->assertOk()->json('token');

        $this->assertSame(['هاتف'], $owner->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($fresh)->getJson('/api/v1/me')->assertOk();
    }

    public function test_logout_revokes_only_this_devices_token(): void
    {
        $owner = $this->bakeryOwner();
        $phone = $owner->createToken('هاتف')->plainTextToken;
        $tablet = $owner->createToken('جهاز لوحي')->plainTextToken;

        $this->withToken($phone)->postJson('/api/v1/logout')->assertOk();

        $this->assertSame(['جهاز لوحي'], $owner->tokens()->pluck('name')->all());
        $this->app['auth']->forgetGuards();
        $this->withToken($tablet)->getJson('/api/v1/me')->assertOk();
    }

    public function test_requests_without_a_token_are_refused(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/api/v1/customers')->assertStatus(401);
    }

    public function test_an_expired_bakery_can_sign_in_but_reads_and_writes_are_refused(): void
    {
        $owner = $this->bakeryOwner(['subscription_expires_at' => now()->subDay()]);

        $this->login($owner->email)->assertOk()->assertJsonPath('subscription.active', false);

        $this->as($owner)->api('GET', 'customers')->assertStatus(403)->assertJsonPath('code', 'subscription_expired');
        $this->api('POST', 'sales', ['client_uuid' => $this->uuid(), 'kg_amount' => 1, 'payment_status' => 'paid', 'sale_date' => local_today()->toDateString()])
            ->assertStatus(403)->assertJsonPath('code', 'subscription_expired');

        $owner->bakery->update(['subscription_status' => Bakery::STATUS_INACTIVE, 'subscription_expires_at' => now()->addMonth()]);
        $this->api('GET', 'dashboard')->assertStatus(403)->assertJsonPath('code', 'subscription_expired');
    }

    public function test_each_role_is_kept_to_its_own_endpoints(): void
    {
        $owner = $this->bakeryOwner();

        $this->as($owner)->api('GET', 'admin/bakeries')->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $this->as($this->staffOf($owner, User::permissionKeys()))->api('GET', 'admin/bakeries')->assertStatus(403);

        $this->as($this->admin())->api('GET', 'customers')->assertStatus(403)->assertJsonPath('code', 'forbidden');
        $this->api('GET', 'sync/pull')->assertStatus(403);
        $this->api('GET', 'admin/bakeries')->assertOk();
    }
}
