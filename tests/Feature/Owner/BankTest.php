<?php

namespace Tests\Feature\Owner;

use App\Models\Bakery;
use App\Models\Bank;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Banks and e-wallets (9-ب item 4-ب) and payments by transfer. */
class BankTest extends TestCase
{
    use RefreshDatabase;

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

    private function customer(User $owner): Customer
    {
        return $owner->bakery->customers()->create(['name' => 'أحمد', 'mobile_number' => '0591111111']);
    }

    private function pay(User $owner, Customer $customer, array $data)
    {
        return $this->actingAs($owner)->post(route('panel.customers.payments.store', $customer), [
            'amount' => '40', 'payment_date' => local_today()->toDateString(), 'submission_id' => fake()->uuid(), ...$data,
        ]);
    }

    public function test_index_lists_only_this_bakerys_banks(): void
    {
        $owner = $this->owner();
        $owner->bakery->banks()->create(['name' => 'جوال باي']);
        $owner->bakery->banks()->create(['name' => 'بنك فلسطين']);
        $this->owner()->bakery->banks()->create(['name' => 'بنك مخبز آخر']);

        $this->actingAs($owner)->get(route('panel.banks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Owner/Banks/Index')
                ->has('banks', 2)
                ->where('banks.0.name', 'بنك فلسطين')
                ->has('banks.0', 2) // id and name only
                ->where('canManage', true)
                ->where('canDelete', true));
    }

    public function test_owner_adds_renames_and_deletes_a_bank(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('panel.banks.store'), ['name' => '  بال باي '])
            ->assertRedirect()->assertSessionHas('success');
        $bank = Bank::sole();
        $this->assertSame(['bakery_id' => $owner->bakery_id, 'name' => 'بال باي'], $bank->only('bakery_id', 'name'));

        $this->actingAs($owner)->put(route('panel.banks.update', $bank), ['name' => 'بال باي - المحفظة'])->assertSessionHas('success');
        $this->assertSame('بال باي - المحفظة', $bank->fresh()->name);

        $this->actingAs($owner)->delete(route('panel.banks.destroy', $bank))->assertSessionHas('success');
        $this->assertDatabaseCount('banks', 0);
    }

    public function test_bank_validation_failure(): void
    {
        $owner = $this->owner();
        $owner->bakery->banks()->create(['name' => 'جوال باي']);
        // The same name in another bakery does not count.
        $this->owner()->bakery->banks()->create(['name' => 'بنك فلسطين']);

        $this->actingAs($owner)->post(route('panel.banks.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($owner)->post(route('panel.banks.store'), ['name' => str_repeat('ب', 101)])->assertSessionHasErrors('name');
        $this->actingAs($owner)->post(route('panel.banks.store'), ['name' => 'جوال باي'])
            ->assertSessionHasErrors(['name' => 'هذا الاسم موجود في القائمة.']);
        $this->actingAs($owner)->post(route('panel.banks.store'), ['name' => 'بنك فلسطين'])->assertSessionHasNoErrors();
        // Renaming to its own name is fine.
        $bank = $owner->bakery->banks()->where('name', 'جوال باي')->first();
        $this->actingAs($owner)->put(route('panel.banks.update', $bank), ['name' => 'جوال باي'])->assertSessionHasNoErrors();
    }

    public function test_owner_cannot_touch_another_bakerys_bank(): void
    {
        $owner = $this->owner();
        $other = $this->owner()->bakery->banks()->create(['name' => 'بنك آخر']);

        $this->actingAs($owner)->put(route('panel.banks.update', $other), ['name' => 'تغيير'])->assertNotFound();
        $this->actingAs($owner)->put(route('panel.banks.update', $other), ['name' => ''])->assertNotFound();
        $this->actingAs($owner)->delete(route('panel.banks.destroy', $other))->assertNotFound();
        $this->assertSame('بنك آخر', $other->fresh()->name);
    }

    public function test_admin_cannot_use_the_banks_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $bank = $this->owner()->bakery->banks()->create(['name' => 'بنك']);

        $this->actingAs($admin)->get(route('panel.banks.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('panel.banks.store'), ['name' => 'x'])->assertForbidden();
        $this->actingAs($admin)->delete(route('panel.banks.destroy', $bank))->assertForbidden();
        $this->assertDatabaseCount('banks', 1);
    }

    public function test_a_transfer_payment_keeps_the_bank_names_after_the_bank_is_deleted(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $from = $owner->bakery->banks()->create(['name' => 'جوال باي']);
        $to = $owner->bakery->banks()->create(['name' => 'بنك فلسطين']);

        $this->pay($owner, $customer, ['method' => 'transfer', 'from_bank_id' => $from->id, 'to_bank_id' => $to->id, 'transfer_reference' => 'TR-7781'])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            ['from_bank' => 'جوال باي', 'to_bank' => 'بنك فلسطين', 'transfer_reference' => 'TR-7781', 'amount' => '-40.00'],
            Movement::sole()->only('from_bank', 'to_bank', 'transfer_reference', 'amount'),
        );

        $this->actingAs($owner)->delete(route('panel.banks.destroy', $from));
        $this->actingAs($owner)->put(route('panel.banks.update', $to), ['name' => 'بنك فلسطين الجديد']);

        $this->actingAs($owner)->get(route('panel.customers.show', $customer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('moneyStatement.rows.0.transfer', 'جوال باي ← بنك فلسطين')
                ->where('moneyStatement.rows.0.transfer_reference', 'TR-7781')
                ->has('banks', 1));
    }

    public function test_a_cash_payment_shows_cash_and_ignores_bank_fields(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $bank = $owner->bakery->banks()->create(['name' => 'جوال باي']);

        $this->pay($owner, $customer, ['method' => 'cash', 'from_bank_id' => $bank->id, 'to_bank_id' => $bank->id, 'transfer_reference' => 'x'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['from_bank' => null, 'to_bank' => null, 'transfer_reference' => null], Movement::sole()->only('from_bank', 'to_bank', 'transfer_reference'));
        $this->assertSame('نقدًا', $customer->statement('money', null, null)['rows'][0]['transfer']);
    }

    public function test_transfer_validation_failure(): void
    {
        $owner = $this->owner();
        $customer = $this->customer($owner);
        $mine = $owner->bakery->banks()->create(['name' => 'جوال باي']);
        $theirs = $this->owner()->bakery->banks()->create(['name' => 'بنك آخر']);

        $this->pay($owner, $customer, ['method' => 'transfer'])->assertSessionHasErrors(['from_bank_id', 'to_bank_id']);
        $this->pay($owner, $customer, ['method' => 'transfer', 'from_bank_id' => $theirs->id, 'to_bank_id' => $mine->id])
            ->assertSessionHasErrors('from_bank_id');
        $this->pay($owner, $customer, ['method' => 'transfer', 'from_bank_id' => $mine->id, 'to_bank_id' => $mine->id, 'transfer_reference' => str_repeat('1', 101)])
            ->assertSessionHasErrors('transfer_reference');
        $this->pay($owner, $customer, ['method' => 'cheque'])->assertSessionHasErrors('method');

        $this->assertDatabaseCount('movements', 0);
    }
}
