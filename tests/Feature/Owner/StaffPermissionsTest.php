<?php

namespace Tests\Feature\Owner;

use App\Actions\RecordSale;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Bakery staff and their checkbox permissions (9-ب item 7): every permission is enforced in the server. */
class StaffPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->owner();
        $this->customer = $this->owner->bakery->customers()->create(['name' => 'أحمد', 'mobile_number' => '0591111111']);
    }

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 5,
            'flour_exchange_fee_per_kg' => 1.5,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    /** @param  list<string>  $permissions */
    private function staff(array $permissions = [], ?User $owner = null): User
    {
        return User::factory()->create([
            'name' => 'سامي الموظف',
            'role' => User::ROLE_BAKERY_STAFF,
            'bakery_id' => ($owner ?? $this->owner)->bakery_id,
            'permissions' => $permissions,
        ]);
    }

    private function unpaidSale(): Sale
    {
        return app(RecordSale::class)->handle($this->owner->bakery, null, Sale::TYPE_CASH, [
            'kg_amount' => '2', 'payment_status' => Sale::STATUS_UNPAID, 'sale_date' => now()->toDateString(),
        ], $this->owner);
    }

    /** Each permission → one request it guards (a page or an action), with valid data. */
    private function attempt(User $user, string $case): TestResponse
    {
        $c = $this->customer;
        $today = now()->toDateString();
        $id = fn () => ['submission_id' => fake()->uuid()];
        $as = $this->actingAs($user);

        return match ($case) {
            'dashboard' => $as->get(route('panel.dashboard')),
            'customers list' => $as->get(route('panel.customers.index')),
            'customer page' => $as->get(route('panel.customers.show', $c)),
            'add customer page' => $as->get(route('panel.customers.create')),
            'add customer' => $as->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(), 'name' => 'جديد', 'mobile_number' => '0592222222']),
            'edit customer page' => $as->get(route('panel.customers.edit', $c)),
            'edit customer' => $as->put(route('panel.customers.update', $c), ['name' => 'معدّل', 'mobile_number' => '0591111111']),
            'sales list' => $as->get(route('panel.sales.index')),
            'sale page' => $as->get(route('panel.sales.create')),
            'cash sale' => $as->post(route('panel.sales.store'), ['kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => $today, ...$id()]),
            'flour sale' => $as->post(route('panel.customers.flour-sales.store', $c), ['kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => $today, ...$id()]),
            'payment' => $as->post(route('panel.customers.payments.store', $c), ['amount' => '10', 'payment_date' => $today, ...$id()]),
            'confirm payment' => $as->post(route('panel.sales.mark-paid', $this->unpaidSale())),
            'deposit' => $as->post(route('panel.customers.deposits.store', $c), ['kg_amount' => '5', 'deposit_date' => $today, ...$id()]),
            'export money' => $as->get(route('panel.customers.statement.export', [$c, 'money', 'xlsx'])),
            'export flour' => $as->get(route('panel.customers.statement.export', [$c, 'flour', 'pdf'])),
            'sales log' => $as->get(route('panel.sales-log')),
            'adjustment' => $as->post(route('panel.customers.adjustments.store', $c), ['ledger' => 'money', 'direction' => 'debit', 'amount' => '5', 'reason' => 'تصحيح', 'movement_date' => $today, ...$id()]),
            'banks' => $as->get(route('panel.banks.index')),
            'add bank' => $as->post(route('panel.banks.store'), ['name' => 'جوال باي']),
            'rename bank' => $as->put(route('panel.banks.update', $this->owner->bakery->banks()->create(['name' => fake()->unique()->word()])), ['name' => 'جديد']),
        };
    }

    /** @return array<string, array{0: string, 1: list<string>, 2: list<string>}> case, the permission it needs, others it also needs */
    public static function guarded(): array
    {
        return [
            'dashboard' => ['dashboard', ['view-dashboard'], []],
            'customers list' => ['customers list', ['view-customers'], []],
            'customer page' => ['customer page', ['view-customers'], []],
            'add customer page' => ['add customer page', ['create-customers'], []],
            'add customer' => ['add customer', ['create-customers'], []],
            'edit customer page' => ['edit customer page', ['edit-customers'], []],
            'edit customer' => ['edit customer', ['edit-customers'], []],
            'sales list' => ['sales list', ['view-sales'], []],
            'sale page' => ['sale page', ['record-sale'], []],
            'cash sale' => ['cash sale', ['record-sale'], []],
            'flour sale' => ['flour sale', ['record-flour-sale'], []],
            'payment' => ['payment', ['record-payment'], []],
            'confirm payment' => ['confirm payment', ['record-payment'], []],
            'deposit' => ['deposit', ['record-deposit'], []],
            'export money needs export' => ['export money', ['export-statements'], ['view-money-statement']],
            'export money needs the money statement' => ['export money', ['view-money-statement'], ['export-statements']],
            'export flour needs the flour statement' => ['export flour', ['view-flour-statement'], ['export-statements']],
            'sales log' => ['sales log', ['view-sales-log'], []],
            'adjustment' => ['adjustment', ['record-adjustment'], []],
            'banks' => ['banks', ['view-banks'], []],
            'add bank' => ['add bank', ['manage-banks'], []],
            'rename bank' => ['rename bank', ['manage-banks'], []],
        ];
    }

    /** @param  list<string>  $needed  @param  list<string>  $also */
    #[DataProvider('guarded')]
    public function test_staff_without_the_permission_are_refused_and_with_it_succeed(string $case, array $needed, array $also): void
    {
        // Every other permission, so only the one under test is missing.
        $others = array_values(array_diff(User::permissionKeys(), $needed));
        $this->attempt($this->staff($others), $case)->assertForbidden();

        $response = $this->attempt($this->staff([...$needed, ...$also]), $case);
        $this->assertTrue($response->isSuccessful() || $response->isRedirection(), "{$case}: HTTP {$response->getStatusCode()}");
        $response->assertSessionHasNoErrors();
    }

    public function test_the_owner_holds_every_permission(): void
    {
        foreach (array_unique(array_column(self::guarded(), 0)) as $case) {
            $response = $this->attempt($this->owner, $case);
            $this->assertTrue($response->isSuccessful() || $response->isRedirection(), "{$case}: HTTP {$response->getStatusCode()}");
        }
    }

    public function test_statements_and_sales_on_the_customer_page_follow_their_permissions(): void
    {
        $this->actingAs($this->staff(['view-customers']))->get(route('panel.customers.show', $this->customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('moneyStatement', null)
                ->where('flourStatement', null)
                ->where('sales', null)
                ->where('unpaidSales', [])
                ->where('banks', [])
                ->where('canAdjust', false)
                ->where('canExport', false));

        $this->owner->bakery->banks()->create(['name' => 'بنك فلسطين']);
        $this->actingAs($this->staff(['view-customers', 'view-flour-statement', 'record-payment']))->get(route('panel.customers.show', $this->customer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('moneyStatement', null)
                ->has('flourStatement.rows')
                ->where('sales', null)
                // The payment dialog gets the bank names without "عرض البنوك".
                ->has('banks', 1));
    }

    /** Owner-only actions stay refused even for staff whose stored permissions somehow include them. */
    public function test_owner_only_actions_are_refused_to_staff_with_every_permission(): void
    {
        $staff = $this->staff([...User::permissionKeys(), ...User::OWNER_ONLY]);
        $bank = $this->owner->bakery->banks()->create(['name' => 'بنك']);
        $other = $this->staff();

        $this->actingAs($staff);
        $this->delete(route('panel.customers.destroy', $this->customer))->assertForbidden();
        $this->delete(route('panel.banks.destroy', $bank))->assertForbidden();
        $this->get(route('panel.settings.edit'))->assertForbidden();
        $this->put(route('panel.settings.update'), ['regular_price_per_kg' => 1, 'flour_exchange_fee_per_kg' => 1])->assertForbidden();
        $this->get(route('panel.users.index'))->assertForbidden();
        $this->get(route('panel.users.create'))->assertForbidden();
        $this->post(route('panel.users.store'), ['name' => 'x', 'email' => 'x@example.test', 'password' => 'password1', 'permissions' => []])->assertForbidden();
        $this->get(route('panel.users.edit', $other))->assertForbidden();
        $this->put(route('panel.users.update', $staff), ['name' => 'x', 'email' => $staff->email, 'permissions' => User::permissionKeys()])->assertForbidden();
        $this->post(route('panel.users.status', $other), ['active' => false])->assertForbidden();

        $this->assertModelExists($this->customer);
        $this->assertModelExists($bank);
        $this->assertTrue($other->fresh()->is_active);
        $this->assertFalse($staff->fresh()->hasPermission('delete-customers'));
    }

    public function test_staff_cannot_reach_another_bakerys_records(): void
    {
        $staff = $this->staff(User::permissionKeys());
        $otherOwner = $this->owner();
        $theirs = $otherOwner->bakery->customers()->create(['name' => 'زبون آخر', 'mobile_number' => '0593333333']);
        $theirBank = $otherOwner->bakery->banks()->create(['name' => 'بنكهم']);
        $theirSale = app(RecordSale::class)->handle($otherOwner->bakery, null, Sale::TYPE_CASH, ['kg_amount' => '1', 'payment_status' => 'unpaid', 'sale_date' => now()->toDateString()], $otherOwner);

        $this->actingAs($staff);
        $this->get(route('panel.customers.show', $theirs))->assertNotFound();
        $this->get(route('panel.customers.edit', $theirs))->assertNotFound();
        $this->put(route('panel.customers.update', $theirs), ['name' => 'x', 'mobile_number' => '1'])->assertNotFound();
        $this->post(route('panel.customers.payments.store', $theirs), ['amount' => '1', 'payment_date' => now()->toDateString(), 'submission_id' => fake()->uuid()])->assertNotFound();
        $this->get(route('panel.customers.statement.export', [$theirs, 'money', 'pdf']))->assertNotFound();
        $this->post(route('panel.sales.mark-paid', $theirSale))->assertNotFound();
        $this->put(route('panel.banks.update', $theirBank), ['name' => 'x'])->assertNotFound();

        $this->get(route('panel.customers.index'))->assertInertia(fn (Assert $page) => $page->has('customers.data', 1));
        $this->assertSame(0, $theirs->movements()->count());
        $this->assertSame('بنكهم', $theirBank->fresh()->name);
    }

    public function test_a_staff_movement_carries_the_staff_members_name(): void
    {
        $staff = $this->staff(['record-sale', 'record-flour-sale', 'view-customers', 'view-money-statement', 'view-sales-log']);

        $this->attempt($staff, 'cash sale')->assertSessionHasNoErrors();
        $this->attempt($staff, 'flour sale')->assertSessionHasNoErrors();

        $movements = $this->owner->bakery->movements()->get();
        $this->assertCount(5, $movements); // cash: sale + payment; flour: withdrawal + fee + payment
        $this->assertSame([$staff->id], $movements->pluck('recorded_by')->unique()->values()->all());
        $this->assertSame(['سامي الموظف'], $movements->pluck('recorded_by_name')->unique()->values()->all());

        $this->actingAs($staff)->get(route('panel.customers.show', $this->customer))
            ->assertInertia(fn (Assert $page) => $page->where('moneyStatement.rows.0.recorded_by', 'سامي الموظف'));
        $this->actingAs($staff)->get(route('panel.sales-log'))
            ->assertInertia(fn (Assert $page) => $page->where('options.recordedBy', ['سامي الموظف']));

        // The name stays after the account is disabled.
        $staff->update(['is_active' => false]);
        $this->assertSame('سامي الموظف', Movement::first()->recorded_by_name);
    }

    public function test_staff_land_on_the_first_page_they_may_open(): void
    {
        $this->actingAs($this->staff(['view-dashboard', 'view-customers']))->get(route('dashboard'))->assertRedirect(route('panel.dashboard'));
        $this->actingAs($this->staff(['view-customers']))->get(route('dashboard'))->assertRedirect(route('panel.customers.index'));
        $this->actingAs($this->staff(['record-sale']))->get(route('dashboard'))->assertRedirect(route('panel.sales.create'));

        $none = $this->staff(['record-payment']);
        $this->actingAs($none)->get(route('dashboard'))->assertRedirect(route('panel.no-permissions'));
        $this->actingAs($none)->get(route('panel.no-permissions'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Owner/NoPermissions'));
    }

    public function test_login_sends_staff_to_their_first_page(): void
    {
        $staff = $this->staff(['view-sales']);

        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertRedirect(route('panel.sales.index'));
    }

    public function test_staff_of_an_expired_bakery_are_sent_to_the_expired_page(): void
    {
        $staff = $this->staff(User::permissionKeys());
        $this->owner->bakery->update(['subscription_expires_at' => now()->subDay()]);

        $this->actingAs($staff)->get(route('panel.customers.index'))->assertRedirect(route('panel.subscription.expired'));
    }

    public function test_react_gets_only_the_permissions_the_user_holds(): void
    {
        $this->actingAs($this->staff(['record-sale', 'view-customers']))->get(route('panel.customers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', ['view-customers', 'record-sale']));

        $this->actingAs($this->owner)->get(route('panel.customers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.permissions', [...User::permissionKeys(), ...User::OWNER_ONLY]));
    }

    public function test_super_admin_and_staff_stay_out_of_each_others_panels(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin)->get(route('panel.customers.index'))->assertForbidden();

        $this->actingAs($this->staff(User::permissionKeys()))->get(route('admin.bakeries.index'))->assertForbidden();
    }

    public function test_an_opening_flour_balance_needs_the_deposit_permission(): void
    {
        $data = ['submission_id' => fake()->uuid(), 'name' => 'جديد', 'mobile_number' => '0592222222', 'flour_balance_kg' => '50000'];

        $this->actingAs($this->staff(['create-customers']))->post(route('panel.customers.store'), $data)
            ->assertSessionHasErrors('flour_balance_kg');
        $this->assertSame(0, $this->owner->bakery->movements()->count());

        // The hidden field arrives empty: allowed.
        $this->actingAs($this->staff(['create-customers']))->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(), ...$data, 'flour_balance_kg' => ''])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->staff(['create-customers', 'record-deposit']))->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(), ...$data, 'name' => 'ثالث'])
            ->assertSessionHasNoErrors();
        $this->assertSame('-50000.00', $this->owner->bakery->customers()->where('name', 'ثالث')->first()->flour_balance_kg);
    }

    public function test_staff_without_the_customer_list_stay_on_the_form_after_saving(): void
    {
        $this->actingAs($this->staff(['create-customers']))
            ->post(route('panel.customers.store'), ['submission_id' => fake()->uuid(), 'name' => 'جديد', 'mobile_number' => '0592222222'])
            ->assertRedirect(route('panel.customers.create'))->assertSessionHas('success');

        $this->actingAs($this->staff(['edit-customers']))
            ->from(route('panel.customers.edit', $this->customer))
            ->put(route('panel.customers.update', $this->customer), ['name' => 'معدّل', 'mobile_number' => '0591111111'])
            ->assertRedirect(route('panel.customers.edit', $this->customer))->assertSessionHas('success');

        $this->actingAs($this->staff(['create-customers']))->get(route('dashboard'))->assertRedirect(route('panel.customers.create'));
    }
}
