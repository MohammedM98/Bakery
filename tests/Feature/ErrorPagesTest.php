<?php

namespace Tests\Feature;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** HTTP errors render the Arabic React page instead of Laravel's English defaults. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function assertErrorPage($response, int $status): void
    {
        $response->assertStatus($status)
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', $status)->has('appName'));
    }

    public function test_an_unknown_url_renders_the_arabic_404_page(): void
    {
        $this->assertErrorPage($this->get('/no-such-page'), 404);
    }

    public function test_another_bakerys_customer_renders_the_404_page(): void
    {
        $other = $this->owner();
        $customer = Customer::create(['bakery_id' => $other->bakery_id, 'name' => 'زبون', 'mobile_number' => '0500000000']);

        $this->assertErrorPage($this->actingAs($this->owner())->get(route('panel.customers.show', $customer)), 404);
    }

    public function test_a_missing_permission_renders_the_403_page(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_BAKERY_STAFF,
            'bakery_id' => $this->owner()->bakery_id,
            'permissions' => [],
        ]);

        $this->assertErrorPage($this->actingAs($staff)->get(route('panel.dashboard')), 403);
    }

    public function test_the_wrong_role_renders_the_403_page(): void
    {
        $this->assertErrorPage($this->actingAs($this->owner())->get(route('admin.bakeries.index')), 403);
    }

    public function test_api_urls_keep_json_errors(): void
    {
        $this->get('/api/no-such-endpoint')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_a_server_error_renders_the_500_page_when_debug_is_off(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/boom', fn () => throw new \RuntimeException('boom'))->middleware('web');

        $this->assertErrorPage($this->get('/_test/boom'), 500);
    }

    public function test_a_server_error_keeps_the_debug_page_when_debug_is_on(): void
    {
        config(['app.debug' => true]);
        Route::get('/_test/boom', fn () => throw new \RuntimeException('boom'))->middleware('web');

        $this->get('/_test/boom')->assertStatus(500)->assertSee('RuntimeException')->assertDontSee('&quot;component&quot;:&quot;Error&quot;', false);
    }

    public function test_maintenance_mode_renders_the_503_page(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/down', fn () => abort(503))->middleware('web');

        $this->assertErrorPage($this->get('/_test/down'), 503);
    }

    public function test_an_expired_page_renders_the_419_page_and_an_inertia_form_goes_back_with_a_toast(): void
    {
        Route::post('/_test/expired', fn () => abort(419))->middleware('web');

        $this->assertErrorPage($this->post('/_test/expired'), 419);

        $this->from('/profile')
            ->post('/_test/expired', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/profile')
            ->assertSessionHas('error', 'انتهت صلاحية الصفحة. حاول مرة أخرى.');
    }
}
