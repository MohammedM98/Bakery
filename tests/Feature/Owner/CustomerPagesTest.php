<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\FlourDeposit;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
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

    private function customer(User $owner, array $attributes = []): Customer
    {
        return Customer::create([
            'bakery_id' => $owner->bakery_id,
            'name' => 'أحمد',
            'mobile_number' => '0591111111',
            ...$attributes,
        ]);
    }

    public function test_index_lists_only_this_bakerys_customers_with_needed_fields(): void
    {
        $owner = $this->owner();
        $mine = $this->customer($owner, ['flour_balance_kg' => 12.5, 'notes' => 'سرّي']);
        $this->customer($this->owner(), ['name' => 'عميل مخبز آخر']);

        $this->actingAs($owner)
            ->get(route('panel.customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Customers/Index')
                ->where('search', '')
                ->where('customers.total', 1)
                ->where('customers.data.0', [
                    'id' => $mine->id,
                    'name' => 'أحمد',
                    'mobile_number' => '0591111111',
                    'flour_balance_kg' => '12.50',
                ]));
    }

    public function test_search_matches_name_or_mobile_within_the_bakery(): void
    {
        $owner = $this->owner();
        $this->customer($owner, ['name' => 'أحمد المالكي', 'mobile_number' => '0501111111']);
        $this->customer($owner, ['name' => 'سالم القحطاني', 'mobile_number' => '0502222222']);
        $this->customer($this->owner(), ['name' => 'أحمد الغريب']);

        $this->actingAs($owner)
            ->get(route('panel.customers.index', ['search' => 'أحمد']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('search', 'أحمد')
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'أحمد المالكي'));

        $this->actingAs($owner)
            ->get(route('panel.customers.index', ['search' => '2222']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('customers.data', 1)
                ->where('customers.data.0.name', 'سالم القحطاني'));
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->owner())
            ->get(route('panel.customers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Owner/Customers/Create'));
    }

    public function test_owner_creates_a_customer_in_their_own_bakery(): void
    {
        $owner = $this->owner();
        $other = $this->owner();

        $this->actingAs($owner)
            ->post(route('panel.customers.store'), [
                'name' => 'عميل تجريبي',
                'mobile_number' => '0500000000',
                'flour_balance_kg' => '10',
                'notes' => '',
                'bakery_id' => $other->bakery_id,
            ])
            ->assertRedirect(route('panel.customers.index'))
            ->assertSessionHas('success', 'تم حفظ بيانات "عميل تجريبي" بنجاح.');

        $customer = Customer::sole();
        $this->assertSame($owner->bakery_id, $customer->bakery_id);
        $this->assertSame('-10.00', $customer->flour_balance_kg);
        $this->assertDatabaseHas('movements', ['customer_id' => $customer->id, 'type' => 'opening_balance', 'ledger' => 'flour', 'amount' => -10]);
        $this->assertNull($customer->notes);
    }

    public function test_opening_flour_balance_defaults_to_zero(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('panel.customers.store'), ['name' => 'عميل', 'mobile_number' => '0500000000']);

        $this->assertSame('0.00', Customer::sole()->flour_balance_kg);
    }

    public function test_creating_a_customer_validates_input(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('panel.customers.store'), ['name' => '', 'mobile_number' => '', 'flour_balance_kg' => '-3'])
            ->assertSessionHasErrors(['name', 'mobile_number', 'flour_balance_kg']);

        $this->assertSame('حقل اسم العميل مطلوب.', session('errors')->first('name'));
        $this->assertSame(0, Customer::count());
    }

    public function test_edit_page_sends_only_the_form_fields(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, ['notes' => 'ملاحظة']);

        $this->actingAs($owner)
            ->get(route('panel.customers.edit', $customer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Customers/Edit')
                ->where('customer', ['id' => $customer->id, 'name' => 'أحمد', 'mobile_number' => '0591111111', 'notes' => 'ملاحظة']));
    }

    public function test_owner_updates_a_customer_but_not_their_flour_balance(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner, ['flour_balance_kg' => 5]);

        $this->actingAs($owner)
            ->put(route('panel.customers.update', $customer), [
                'name' => 'أحمد علي',
                'mobile_number' => '0593333333',
                'notes' => 'جديد',
                'flour_balance_kg' => 999,
            ])
            ->assertRedirect(route('panel.customers.index'))
            ->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('أحمد علي', $customer->name);
        $this->assertSame('0593333333', $customer->mobile_number);
        $this->assertSame('جديد', $customer->notes);
        $this->assertSame('5.00', $customer->flour_balance_kg);
    }

    public function test_updating_a_customer_validates_input(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);

        $this->actingAs($owner)
            ->put(route('panel.customers.update', $customer), ['name' => '', 'mobile_number' => str_repeat('1', 51)])
            ->assertSessionHasErrors(['name', 'mobile_number']);

        $this->assertSame('أحمد', $customer->fresh()->name);
    }

    public function test_deleting_a_customer_removes_all_their_records(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $keep = $this->customer($owner, ['name' => 'باقٍ']);

        $sale = fn (Customer $c) => Sale::create([
            'bakery_id' => $owner->bakery_id, 'customer_id' => $c->id, 'sale_type' => Sale::TYPE_CASH,
            'kg_amount' => 2, 'price_per_kg' => 5, 'total_amount' => 10, 'payment_status' => Sale::STATUS_UNPAID,
            'sale_date' => now()->toDateString(),
        ]);
        $sale($customer);
        $keptSale = $sale($keep);
        FlourDeposit::create(['bakery_id' => $owner->bakery_id, 'customer_id' => $customer->id, 'kg_amount' => 10, 'deposit_date' => now()->toDateString()]);
        Payment::create(['bakery_id' => $owner->bakery_id, 'customer_id' => $customer->id, 'amount' => 5, 'payment_date' => now()->toDateString()]);

        $this->actingAs($owner)
            ->delete(route('panel.customers.destroy', $customer))
            ->assertRedirect(route('panel.customers.index'))
            ->assertSessionHas('success', 'تم حذف العميل وجميع سجلاته.');

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
        $this->assertSame(0, Sale::where('customer_id', $customer->id)->count());
        $this->assertSame(1, Sale::count());
        $this->assertDatabaseHas('sales', ['id' => $keptSale->id]);
        $this->assertSame(0, FlourDeposit::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_owner_cannot_edit_update_or_delete_another_bakerys_customer(): void
    {
        $owner = $this->owner();
        $foreign = $this->customer($this->owner(), ['name' => 'عميل آخر']);

        $this->actingAs($owner);
        $this->get(route('panel.customers.edit', $foreign))->assertNotFound();
        $this->put(route('panel.customers.update', $foreign), ['name' => 'مخترق', 'mobile_number' => '1'])->assertNotFound();
        $this->delete(route('panel.customers.destroy', $foreign))->assertNotFound();

        $this->assertSame('عميل آخر', $foreign->fresh()->name);
    }

    public function test_super_admin_cannot_reach_customer_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = $this->customer($this->owner());

        $this->actingAs($admin);
        $this->get(route('panel.customers.index'))->assertForbidden();
        $this->post(route('panel.customers.store'), ['name' => 'x', 'mobile_number' => '1'])->assertForbidden();
        $this->put(route('panel.customers.update', $customer), ['name' => 'x', 'mobile_number' => '1'])->assertForbidden();
        $this->delete(route('panel.customers.destroy', $customer))->assertForbidden();
        $this->assertSame(1, Customer::count());
    }

    public function test_expired_bakery_cannot_write_customers(): void
    {
        $owner = $this->owner();
        $owner->bakery->update(['subscription_expires_at' => now()->subDay()]);

        $this->actingAs($owner)
            ->post(route('panel.customers.store'), ['name' => 'x', 'mobile_number' => '1'])
            ->assertRedirect(route('panel.subscription.expired'));
        $this->assertSame(0, Customer::count());
    }
}
