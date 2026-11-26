<?php

namespace Tests\Feature\Admin;

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

    public function test_bakery_owner_cannot_access_admin_panel(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_BAKERY_OWNER]);

        $this->actingAs($owner)
            ->get(route('admin.bakeries.index'))
            ->assertForbidden();
    }
}
