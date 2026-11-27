<?php

namespace Tests\Feature\Api;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/** Shared set-up for the mobile API tests: bakeries, accounts, and calls as a signed-in device. */
trait ApiHelpers
{
    protected function bakeryOwner(array $bakery = []): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز النور',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
            ...$bakery,
        ]);

        return User::factory()->create(['name' => 'صاحب المخبز', 'role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    /** @param  list<string>  $permissions */
    protected function staffOf(User $owner, array $permissions = []): User
    {
        return User::factory()->create([
            'name' => 'سامي الموظف', 'role' => User::ROLE_BAKERY_STAFF, 'bakery_id' => $owner->bakery_id, 'permissions' => $permissions,
        ]);
    }

    protected function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    protected function customerOf(User $owner, string $name = 'أحمد'): Customer
    {
        return $owner->bakery->customers()->create(['name' => $name, 'mobile_number' => '0591111111']);
    }

    protected function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }

    protected function api(string $method, string $uri, array $data = []): TestResponse
    {
        return $this->json($method, '/api/v1/'.ltrim($uri, '/'), $data);
    }

    protected function assertBalancesMatchMovements(Customer $customer): void
    {
        $customer->refresh();
        $flour = Movement::cents($customer->movements()->where('ledger', Movement::LEDGER_FLOUR)->sum('amount'));
        $this->assertSame($flour, Movement::cents($customer->flour_balance_kg), 'cached flour balance = sum of flour movements');
    }

    /** @param  array<string, mixed>  $payload */
    protected function op(string $type, array $payload, ?string $uuid = null, ?string $at = null): array
    {
        return ['client_uuid' => $uuid ?? $this->uuid(), 'type' => $type, 'payload' => $payload, 'client_created_at' => $at ?? now()->toIso8601String()];
    }
}
