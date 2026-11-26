<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login_from_both_panels(): void
    {
        $this->get('/admin/bakeries')->assertRedirect('/login');
        $this->get('/panel/dashboard')->assertRedirect('/login');
    }

    public function test_super_admin_cannot_open_the_bakery_panel(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)->get('/panel/dashboard')->assertForbidden();
        $this->actingAs($admin)->get('/panel/subscription-expired')->assertForbidden();
    }

    public function test_profile_update_only_touches_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'id' => $other->id,
            'name' => 'تعديل',
            'email' => 'changed@example.test',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        $this->assertSame('تعديل', $user->fresh()->name);
        $this->assertNotSame(User::ROLE_SUPER_ADMIN, $user->fresh()->role);
        $this->assertNotSame('تعديل', $other->fresh()->name);
    }
}
