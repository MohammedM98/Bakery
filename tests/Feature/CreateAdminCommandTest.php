<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        parent::tearDown();
    }

    public function test_it_creates_the_first_super_admin_once(): void
    {
        putenv('ADMIN_EMAIL=Boss@Example.test');
        putenv('ADMIN_PASSWORD=a-long-secret');

        $this->artisan('app:create-admin')->assertSuccessful();
        $admin = User::where('role', User::ROLE_SUPER_ADMIN)->sole();
        $this->assertSame('boss@example.test', $admin->email);
        $this->assertTrue(Hash::check('a-long-secret', $admin->password));

        putenv('ADMIN_EMAIL=other@example.test');
        $this->artisan('app:create-admin')->assertSuccessful();
        $this->assertSame(1, User::where('role', User::ROLE_SUPER_ADMIN)->count());
    }

    public function test_it_refuses_a_bad_email_or_a_short_password(): void
    {
        putenv('ADMIN_EMAIL=not-an-email');
        putenv('ADMIN_PASSWORD=short');

        $this->artisan('app:create-admin')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
