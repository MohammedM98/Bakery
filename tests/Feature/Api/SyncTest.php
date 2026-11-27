<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** The offline queue (section 13): push with per-operation results, the price-at-sale rule, and pull with a cursor. */
class SyncTest extends TestCase
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

    private function push(array $operations): array
    {
        return $this->api('POST', 'sync/push', ['operations' => $operations])->assertOk()->json('results');
    }

    private function pull(?string $cursor = null): TestResponse
    {
        return $this->api('GET', 'sync/pull'.($cursor === null ? '' : '?since='.$cursor))->assertOk();
    }

    public function test_a_batch_mixes_accepted_duplicate_and_rejected_operations(): void
    {
        $today = today()->toDateString();
        $deleted = $this->customerOf($this->owner, 'محذوف');
        $deposit = $this->op('deposit', ['customer_id' => $this->customer->id, 'kg_amount' => '30', 'deposit_date' => $today]);
        $this->as($this->owner);
        $this->push([$deposit]);
        $deleted->delete();

        $newCustomer = $this->op('customer.create', ['name' => 'زبون جديد', 'mobile_number' => '0593333333']);
        $results = $this->push([
            $deposit, // sent again: the response was lost
            $this->op('flour_sale', ['customer_id' => $this->customer->id, 'kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => $today, 'price_per_kg' => '1.50']),
            $this->op('deposit', ['customer_id' => $deleted->id, 'kg_amount' => '5', 'deposit_date' => $today]),
            $this->op('deposit', ['customer_id' => $this->customer->id, 'kg_amount' => 'كثير', 'deposit_date' => $today]),
            $this->op('settings.update', ['regular_price_per_kg' => '9', 'flour_exchange_fee_per_kg' => '9']),
            $newCustomer,
            $this->op('payment', ['customer_client_uuid' => $newCustomer['client_uuid'], 'amount' => '20', 'payment_date' => $today]),
            ['type' => 'sale'],
        ]);

        $this->assertSame(['duplicate', 'accepted', 'rejected', 'rejected', 'rejected', 'accepted', 'accepted', 'rejected'], array_column($results, 'status'));
        $this->assertSame($deposit['client_uuid'], $results[0]['client_uuid']);
        $this->assertSame(1, $results[0]['data']['movements'][0]['number']);
        $this->assertSame([2, 3], array_column($results[1]['data']['movements'], 'number'));
        $this->assertSame('له 20 كغ', $results[1]['data']['customer']['flour_balance_text']);
        $this->assertSame('customer_not_found', $results[2]['code']);
        $this->assertSame('validation_failed', $results[3]['code']);
        $this->assertArrayHasKey('kg_amount', $results[3]['errors']);
        $this->assertFalse($results[3]['retryable']);
        $this->assertSame('validation_failed', $results[4]['code'], 'settings are online only');
        $this->assertSame('-20.00', $results[6]['data']['customer']['money_balance']);
        $this->assertSame($results[5]['data']['customer']['id'], $results[6]['data']['customer']['id']);
        $this->assertSame('validation_failed', $results[7]['code']);
        $this->assertNull($results[7]['client_uuid']);

        $this->assertSame('5.00', $this->owner->bakery->refresh()->regular_price_per_kg);
        foreach (Customer::all() as $customer) {
            $this->assertBalancesMatchMovements($customer);
            $this->assertSame(
                Movement::cents($customer->movements()->where('ledger', 'money')->sum('amount')),
                Movement::cents($this->api('GET', "customers/{$customer->id}")->json('customer.money_balance')),
            );
        }
        $this->assertSame(range(1, 4), Movement::orderBy('number')->pluck('number')->all(), 'gap-free numbers');
    }

    public function test_revoked_permissions_and_an_expired_subscription_reject_each_operation(): void
    {
        $staff = $this->staffOf($this->owner, ['view-customers', 'record-deposit']);
        $op = fn () => $this->op('deposit', ['customer_id' => $this->customer->id, 'kg_amount' => '1', 'deposit_date' => today()->toDateString()]);
        $this->as($staff);

        $this->assertSame('accepted', $this->push([$op()])[0]['status']);

        $staff->update(['permissions' => ['view-customers']]);
        $this->assertSame('forbidden', $this->push([$op()])[0]['code']);

        $this->owner->bakery->update(['subscription_expires_at' => today()->subDay()]);
        $staff->update(['permissions' => ['view-customers', 'record-deposit']]);
        $staff->unsetRelation('bakery'); // each real request loads the user afresh
        $result = $this->push([$op()])[0];
        $this->assertSame(['rejected', 'subscription_expired'], [$result['status'], $result['code']]);
        $this->assertSame('انتهى اشتراك المخبز. تواصل مع إدارة المنصة لتجديده.', $result['message']);

        $this->assertSame(1, Movement::count());
    }

    public function test_an_offline_sale_keeps_a_price_the_bakery_had_at_that_time(): void
    {
        $this->travelTo(now()->subDays(3));
        $this->owner->bakery->update(['regular_price_per_kg' => '4']);
        $this->travelBack();
        $this->owner->bakery->update(['regular_price_per_kg' => '6']);
        $sale = fn (string $price, string $at) => $this->op('sale', ['kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => today()->toDateString(), 'price_per_kg' => $price], null, $at);
        $this->as($this->owner);

        $results = $this->push([
            $sale('4', now()->subDay()->toIso8601String()),      // the old price, while the device was offline
            $sale('6', now()->toIso8601String()),                // the current price
            $sale('6', now()->subDays(2)->toIso8601String()),    // the current price did not exist yet
            $sale('3', now()->toIso8601String()),                // never a price of this bakery
        ]);

        $this->assertSame(['accepted', 'accepted', 'rejected', 'rejected'], array_column($results, 'status'));
        $this->assertSame('price_mismatch', $results[2]['code']);
        $this->assertSame(['4.00', '6.00'], Sale::orderBy('id')->pluck('price_per_kg')->all());
        $this->assertSame('40.00', $results[0]['data']['sale']['total_amount']);

        // Online, the current setting, as on the website: a price in the body is ignored.
        $this->api('POST', 'sales', ['client_uuid' => $this->uuid(), 'kg_amount' => '1', 'payment_status' => 'paid', 'sale_date' => today()->toDateString(), 'price_per_kg' => '4'])
            ->assertCreated()->assertJsonPath('sale.price_per_kg', '6.00');
    }

    public function test_a_price_replaced_long_before_or_never_set_is_refused(): void
    {
        $sale = fn (string $price, string $at) => $this->op('sale', ['kg_amount' => '1', 'payment_status' => 'paid', 'sale_date' => today()->toDateString(), 'price_per_kg' => $price], null, $at);
        $unset = $this->bakeryOwner(['regular_price_per_kg' => 0, 'flour_exchange_fee_per_kg' => 0]);
        $this->as($unset);
        $this->assertSame('price_mismatch', $this->push([$sale('0', now()->toIso8601String())])[0]['code'], 'a new bakery\'s 0 is not a price');

        $this->travelTo(now()->subDays(20));
        $this->owner->bakery->update(['regular_price_per_kg' => '7']);
        $this->travel(4)->days();
        $this->owner->bakery->update(['regular_price_per_kg' => '8']);
        $this->travelBack();
        $this->as($this->owner);

        $this->assertSame(['rejected', 'accepted', 'accepted', 'rejected'], array_column($this->push([
            $sale('7', now()->toIso8601String()),                  // replaced 16 days ago: too stale
            $sale('8', now()->toIso8601String()),
            $sale('8', now()->subDays(13)->toIso8601String()),     // queued 13 days ago, the price then
            $sale('7', now()->subDays(17)->toIso8601String()),     // claims to be older than the window: not trusted
        ]), 'status'));
    }

    public function test_pull_sends_changes_since_the_cursor_with_tombstones(): void
    {
        $bank = $this->owner->bakery->banks()->create(['name' => 'جوال باي']);
        $other = $this->customerOf($this->bakeryOwner(), 'زبون مخبز آخر');
        $this->as($this->owner);
        $this->push([$this->op('deposit', ['customer_id' => $this->customer->id, 'kg_amount' => '7', 'deposit_date' => today()->toDateString()])]);

        $first = $this->pull()->assertJsonPath('full', true)->assertJsonPath('has_more', false)
            ->assertJsonPath('subscription.active', true)
            ->assertJsonPath('prices.regular_price_per_kg', '5.00')
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.flour_balance_text', 'له 7 كغ')
            ->assertJsonCount(1, 'movements')
            ->assertJsonPath('banks.0.name', 'جوال باي');
        $this->assertNotContains($other->id, array_column($first->json('customers'), 'id'));

        $cursor = $first->json('cursor');
        $this->pull($cursor)->assertJsonPath('full', false)->assertJsonCount(0, 'customers')->assertJsonCount(0, 'movements')->assertJsonCount(0, 'banks')
            ->assertJsonPath('cursor', $cursor);

        $this->customer->update(['name' => 'أحمد علي']);
        $second = $this->customerOf($this->owner, 'سعيد');
        $bank->delete();
        $second->delete();

        $next = $this->pull($cursor)
            ->assertJsonCount(1, 'customers')
            ->assertJsonPath('customers.0.name', 'أحمد علي')
            ->assertJsonPath('deleted.customers', [$second->id])
            ->assertJsonPath('deleted.banks', [$bank->id]);
        $this->assertNotSame($cursor, $next->json('cursor'));
    }

    public function test_pull_follows_the_users_permissions(): void
    {
        $staff = $this->staffOf($this->owner, ['view-customers', 'view-flour-statement']);
        $this->as($this->owner)->push([$this->op('flour_sale', ['customer_id' => $this->customer->id, 'kg_amount' => '2', 'payment_status' => 'unpaid', 'sale_date' => today()->toDateString()])]);

        $this->as($staff);
        $pull = $this->pull()->assertJsonCount(1, 'movements')->assertJsonPath('movements.0.ledger', 'flour')->assertJsonPath('banks', []);

        // New permissions: the old cursor starts a full pull, with what the user may now see.
        $staff->update(['permissions' => ['view-customers', 'view-flour-statement', 'view-money-statement']]);
        $this->pull($pull->json('cursor'))->assertJsonPath('full', true)->assertJsonCount(2, 'movements');

        $staff->update(['permissions' => []]);
        $this->pull()->assertJsonCount(0, 'customers')->assertJsonCount(0, 'movements')->assertJsonPath('permissions', []);
    }

    public function test_pull_pages_large_changes_without_splitting_an_operation(): void
    {
        $this->as($this->owner);
        foreach (array_chunk(range(1, 260), 100) as $chunk) {
            $this->push(array_map(fn () => $this->op('flour_sale', ['customer_id' => $this->customer->id, 'kg_amount' => '1', 'payment_status' => 'unpaid', 'sale_date' => today()->toDateString()]), $chunk));
        }

        $numbers = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $this->pull($cursor)->json();
            $numbers = [...$numbers, ...array_column($page['movements'], 'number')];
            $cursor = $page['cursor'];
            $pages++;
        } while ($page['has_more']);

        $this->assertSame(2, $pages);
        sort($numbers);
        $this->assertSame(range(1, 520), $numbers, 'every movement once, both of each sale on the same page');
    }

    public function test_pull_from_an_expired_bakery_only_reports_the_state(): void
    {
        $this->owner->bakery->update(['subscription_expires_at' => today()->subDay()]);

        $this->as($this->owner)->pull('5.abcdef12.20260101')
            ->assertJsonPath('subscription.active', false)
            ->assertJsonPath('cursor', '5.abcdef12.20260101')
            ->assertJsonCount(0, 'customers');
    }

    public function test_a_bad_cursor_is_refused(): void
    {
        $this->as($this->owner)->api('GET', 'sync/pull?since=yesterday')->assertStatus(422)->assertJsonPath('code', 'invalid_cursor');
    }

    public function test_push_refuses_an_oversized_or_malformed_batch(): void
    {
        $this->as($this->owner);
        $this->api('POST', 'sync/push', ['operations' => array_fill(0, 101, [])])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->api('POST', 'sync/push', [])->assertStatus(422);
    }

    public function test_the_migration_backfill_gives_existing_rows_a_version_the_first_pull_reads(): void
    {
        // Rows written before the sync columns existed: what the migration's backfill leaves behind.
        $this->as($this->owner)->push([$this->op('deposit', ['customer_id' => $this->customer->id, 'kg_amount' => '3', 'deposit_date' => today()->toDateString()])]);
        DB::table('movements')->update(['sync_version' => DB::raw('number')]);
        DB::table('customers')->update(['sync_version' => 1]);
        DB::table('bakeries')->update(['sync_version' => DB::raw('last_movement_number + 1')]);

        $this->pull()->assertJsonCount(1, 'customers')->assertJsonCount(1, 'movements');
    }

    public function test_the_demo_seed_gives_the_bakery_a_price_an_offline_sale_can_use(): void
    {
        $this->seed();
        $owner = User::where('email', 'owner@bakery.test')->firstOrFail();

        $this->as($owner)->api('POST', 'sync/push', ['operations' => [
            $this->op('sale', ['kg_amount' => '2', 'payment_status' => 'paid', 'sale_date' => today()->toDateString(), 'price_per_kg' => $owner->bakery->regular_price_per_kg]),
        ]])->assertOk()->assertJsonPath('results.0.status', 'accepted');
        $this->as($owner)->api('GET', 'sync/pull')->assertOk()->assertJsonCount($owner->bakery->customers()->count(), 'customers');
    }
}
