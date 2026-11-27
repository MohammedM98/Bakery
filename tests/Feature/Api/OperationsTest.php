<?php

namespace Tests\Feature\Api;

use App\Actions\RecordSale;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The online writes: the website's Form Requests, gates and Actions, plus durable idempotency keys. */
class OperationsTest extends TestCase
{
    use ApiHelpers, RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->bakeryOwner();
        $this->customer = $this->customerOf($this->owner);
    }

    private function unpaidSale(User $owner, ?Customer $customer = null): Sale
    {
        return app(RecordSale::class)->handle($owner->bakery, $customer, Sale::TYPE_CASH, [
            'kg_amount' => '2', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => local_today()->toDateString(),
        ], $owner);
    }

    /** One valid call per operation, on the records of $owner's bakery. */
    private function attempt(string $case, User $owner): TestResponse
    {
        $bakery = $owner->bakery;
        $customer = $bakery->customers()->first() ?? $this->customerOf($owner);
        $today = local_today()->toDateString();
        $key = ['client_uuid' => $this->uuid()];
        $bank = fn () => $bakery->banks()->firstOrCreate(['name' => 'بنك فلسطين']);
        $staff = fn () => $bakery->staff()->first() ?? $this->staffOf($owner);

        return match ($case) {
            'sale' => $this->api('POST', 'sales', [...$key, 'kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => $today, 'buyer_name' => 'مشترٍ']),
            'flour sale' => $this->api('POST', "customers/{$customer->id}/flour-sales", [...$key, 'kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => $today]),
            'deposit' => $this->api('POST', "customers/{$customer->id}/deposits", [...$key, 'kg_amount' => '25', 'deposit_date' => $today]),
            'payment' => $this->api('POST', "customers/{$customer->id}/payments", [...$key, 'amount' => '12.50', 'payment_date' => $today]),
            'mark paid' => $this->api('POST', "sales/{$this->unpaidSale($owner, $customer)->id}/mark-paid", $key),
            'adjustment' => $this->api('POST', "customers/{$customer->id}/adjustments", [...$key, 'ledger' => 'flour', 'direction' => 'credit', 'amount' => '2', 'reason' => 'تصحيح', 'movement_date' => $today]),
            'add customer' => $this->api('POST', 'customers', [...$key, 'name' => 'جديد', 'mobile_number' => '0592222222']),
            'edit customer' => $this->api('PUT', "customers/{$customer->id}", ['name' => 'معدّل', 'mobile_number' => '0591111111']),
            'delete customer' => $this->api('DELETE', "customers/{$customer->id}"),
            'add bank' => $this->api('POST', 'banks', [...$key, 'name' => 'بنك '.fake()->unique()->numberBetween(1, 999)]),
            'rename bank' => $this->api('PUT', "banks/{$bank()->id}", ['name' => 'بنك '.fake()->unique()->numberBetween(1, 999)]),
            'delete bank' => $this->api('DELETE', "banks/{$bank()->id}"),
            'settings' => $this->api('PUT', 'settings', ['regular_price_per_kg' => '6', 'flour_exchange_fee_per_kg' => '2']),
            'add staff' => $this->api('POST', 'users', [...$key, 'name' => 'موظف', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123', 'permissions' => ['view-customers']]),
            'edit staff' => $this->api('PUT', "users/{$staff()->id}", ['name' => 'موظف', 'email' => $staff()->email, 'permissions' => []]),
            'staff status' => $this->api('POST', "users/{$staff()->id}/status", ['active' => false]),
        };
    }

    /** @return array<string, array{0: string, 1: string, 2: int}> */
    public static function operations(): array
    {
        return [
            'sale' => ['sale', 'record-sale', 201],
            'flour sale' => ['flour sale', 'record-flour-sale', 201],
            'deposit' => ['deposit', 'record-deposit', 201],
            'payment' => ['payment', 'record-payment', 201],
            'mark paid' => ['mark paid', 'record-payment', 200],
            'adjustment' => ['adjustment', 'record-adjustment', 201],
            'add customer' => ['add customer', 'create-customers', 201],
            'edit customer' => ['edit customer', 'edit-customers', 200],
            'delete customer' => ['delete customer', 'delete-customers', 200],
            'add bank' => ['add bank', 'manage-banks', 201],
            'rename bank' => ['rename bank', 'manage-banks', 200],
            'delete bank' => ['delete bank', 'delete-banks', 200],
            'settings' => ['settings', 'manage-settings', 200],
            'add staff' => ['add staff', 'manage-users', 201],
            'edit staff' => ['edit staff', 'manage-users', 200],
            'staff status' => ['staff status', 'manage-users', 200],
        ];
    }

    #[DataProvider('operations')]
    public function test_the_owner_can_and_staff_need_the_permission(string $case, string $permission, int $status): void
    {
        $this->as($this->owner);
        $this->attempt($case, $this->owner)->assertStatus($status);

        $without = $this->staffOf($this->owner, array_values(array_diff(User::permissionKeys(), [$permission])));
        $this->as($without);
        $this->attempt($case, $this->owner)->assertStatus(403)->assertJsonPath('code', 'forbidden');

        $with = $this->staffOf($this->owner, [$permission, ...($permission === 'create-customers' ? ['record-deposit'] : [])]);
        $this->as($with);
        $this->attempt($case, $this->owner)->assertStatus(in_array($permission, User::OWNER_ONLY, true) ? 403 : $status);
    }

    /** @return array<string, array{0: string}> */
    public static function recordOperations(): array
    {
        return collect(self::operations())
            ->only(['flour sale', 'deposit', 'payment', 'mark paid', 'adjustment', 'edit customer', 'delete customer', 'rename bank', 'delete bank', 'edit staff', 'staff status'])
            ->map(fn ($row) => [$row[0]])->all();
    }

    #[DataProvider('recordOperations')]
    public function test_another_bakerys_records_are_not_found(string $case): void
    {
        $other = $this->bakeryOwner();

        $this->as($this->owner);
        $response = $this->attempt($case, $other)->assertStatus(404);

        $this->assertStringEndsWith('_not_found', $response->json('code'));
        $this->assertSame(0, Movement::where('recorded_by', $this->owner->id)->count(), 'nothing written');
        $this->assertTrue($other->bakery->customers()->exists());
        $this->assertSame(0, $this->owner->bakery->customers()->where('name', 'معدّل')->count());
    }

    public function test_a_sale_answers_with_the_server_numbers_and_the_balances_after(): void
    {
        $this->as($this->owner)->api('POST', "customers/{$this->customer->id}/flour-sales", [
            'client_uuid' => $this->uuid(), 'kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => local_today()->toDateString(),
        ])->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'false')
            ->assertJsonPath('movements.0.number', 1)
            ->assertJsonPath('movements.0.type', 'flour_withdrawal')
            ->assertJsonPath('movements.0.amount_text', '10 كغ')
            ->assertJsonPath('movements.1.number', 2)
            ->assertJsonPath('movements.1.amount_text', '15.00 شيكل')
            ->assertJsonPath('customer.flour_balance_text', 'عليه 10 كغ')
            ->assertJsonPath('customer.money_balance', '15.00')
            ->assertJsonPath('sale.payment_status', 'unpaid')
            ->assertJsonPath('sale.price_per_kg', '1.50');

        $this->assertBalancesMatchMovements($this->customer);
    }

    public function test_validation_errors_are_arabic_and_write_nothing(): void
    {
        $this->as($this->owner)->api('POST', "customers/{$this->customer->id}/deposits", [
            'client_uuid' => $this->uuid(), 'kg_amount' => '-1', 'deposit_date' => today()->addDay()->toDateString(),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.kg_amount.0', 'يجب ألا تقل قيمة حقل الكمية عن 0.01.')
            ->assertJsonStructure(['errors' => ['deposit_date']]);

        $this->api('POST', 'sales', ['kg_amount' => 1, 'payment_status' => 'paid', 'sale_date' => local_today()->toDateString()])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['client_uuid']]);

        $this->assertSame(0, Movement::count());
    }

    public function test_replaying_a_client_uuid_returns_the_first_answer_and_writes_once(): void
    {
        $body = ['client_uuid' => $this->uuid(), 'amount' => '40', 'payment_date' => local_today()->toDateString()];

        $first = $this->as($this->owner)->api('POST', "customers/{$this->customer->id}/payments", $body)->assertCreated();
        $again = $this->api('POST', "customers/{$this->customer->id}/payments", $body)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json(), $again->json());
        $this->assertSame(1, Movement::count());

        $this->api('POST', "customers/{$this->customer->id}/payments", [...$body, 'amount' => '41'])
            ->assertStatus(409)->assertJsonPath('code', 'idempotency_conflict');
        $this->api('POST', "customers/{$this->customer->id}/deposits", [...$body, 'kg_amount' => '1', 'deposit_date' => local_today()->toDateString()])
            ->assertStatus(409);

        // Another user's key with the same value is not theirs to replay.
        $staff = $this->staffOf($this->owner, ['record-payment']);
        $this->as($staff)->api('POST', "customers/{$this->customer->id}/payments", $body)->assertCreated()->assertHeader('Idempotent-Replayed', 'false');
        $this->assertSame(2, Movement::count());
    }

    public function test_a_failed_operation_does_not_keep_its_key(): void
    {
        $body = ['client_uuid' => $this->uuid(), 'kg_amount' => '5', 'payment_status' => 'paid', 'sale_date' => today()->addDays(2)->toDateString()];

        $this->as($this->owner)->api('POST', 'sales', $body)->assertStatus(422);
        // The refused attempt left no key: the corrected operation can use the same one.
        $this->api('POST', 'sales', [...$body, 'sale_date' => local_today()->toDateString()])->assertCreated()->assertHeader('Idempotent-Replayed', 'false');

        $this->assertSame(1, Sale::count());
    }

    public function test_marking_a_paid_sale_again_is_refused(): void
    {
        $sale = $this->unpaidSale($this->owner, $this->customer);

        $this->as($this->owner)->api('POST', "sales/{$sale->id}/mark-paid", ['client_uuid' => $this->uuid()])->assertOk()
            ->assertJsonPath('sale.payment_status', 'paid')
            ->assertJsonPath('movements.0.type', 'payment');
        $this->api('POST', "sales/{$sale->id}/mark-paid", ['client_uuid' => $this->uuid()])->assertStatus(409)->assertJsonPath('code', 'already_paid');
    }

    public function test_deleting_a_customer_and_a_bank_and_changing_prices(): void
    {
        $bank = $this->owner->bakery->banks()->create(['name' => 'جوال باي']);

        $this->as($this->owner)->api('DELETE', "customers/{$this->customer->id}")->assertOk();
        $this->api('DELETE', "banks/{$bank->id}")->assertOk();
        $this->api('PUT', 'settings', ['regular_price_per_kg' => '6.5', 'flour_exchange_fee_per_kg' => '2'])->assertOk()
            ->assertJsonPath('prices.regular_price_text', '6.50 شيكل');

        $this->assertModelMissing($this->customer);
        $this->assertModelMissing($bank);
        $this->assertSame(['5.00', '6.50'], $this->owner->bakery->refresh()->newQuery()->getConnection()
            ->table('bakery_prices')->where('bakery_id', $this->owner->bakery_id)->orderBy('id')->pluck('regular_price_per_kg')
            ->map(fn ($p) => number_format((float) $p, 2))->all());
    }

    public function test_staff_accounts_are_managed_by_the_owner_only_with_known_permissions(): void
    {
        $this->as($this->owner)->api('POST', 'users', [
            'client_uuid' => $this->uuid(), 'name' => 'موظف', 'email' => 'Staff@Example.com', 'password' => 'password123', 'permissions' => ['view-customers', 'delete-customers'],
        ])->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonFragment(['permissions.1' => ['صلاحية غير معروفة.']]);

        $created = $this->api('POST', 'users', [
            'client_uuid' => $this->uuid(), 'name' => 'موظف', 'email' => 'Staff@Example.com', 'password' => 'password123', 'permissions' => ['view-customers'],
        ])->assertCreated()->assertJsonPath('user.email', 'staff@example.com')->json('user');

        $staff = User::find($created['id']);
        $this->assertTrue($staff->isBakeryStaff());
        $this->assertSame($this->owner->bakery_id, $staff->bakery_id);

        // The owner's own account is not a staff member.
        $this->api('POST', "users/{$this->owner->id}/status", ['active' => false])->assertStatus(404)->assertJsonPath('code', 'user_not_found');
    }

    public function test_admin_creates_edits_renews_and_stops_bakeries(): void
    {
        $this->as($this->admin());

        $id = $this->api('POST', 'admin/bakeries', [
            'client_uuid' => $this->uuid(), 'name' => 'مخبز جديد', 'subscription_months' => 1,
            'owner_name' => 'مالك', 'owner_email' => 'new@example.com', 'owner_password' => 'password123',
        ])->assertCreated()->assertJsonPath('bakery.owner.email', 'new@example.com')->json('bakery.id');

        $this->api('PUT', "admin/bakeries/{$id}", ['name' => 'اسم آخر'])->assertOk()->assertJsonPath('bakery.name', 'اسم آخر');

        $renew = ['client_uuid' => $this->uuid(), 'months' => 2];
        $expiry = $this->api('POST', "admin/bakeries/{$id}/renew", $renew)->assertOk()->json('bakery.subscription.expires_at');
        $this->api('POST', "admin/bakeries/{$id}/renew", $renew)->assertOk()->assertJsonPath('bakery.subscription.expires_at', $expiry);
        $this->assertSame($expiry, Bakery::find($id)->subscription_expires_at->toDateString(), 'a replayed renewal does not add months twice');

        $this->api('POST', "admin/bakeries/{$id}/status", ['status' => 'inactive'])->assertOk()->assertJsonPath('bakery.subscription.active', false);
        $this->api('POST', "admin/bakeries/{$id}/status", ['status' => 'paused'])->assertStatus(422);
    }
}
