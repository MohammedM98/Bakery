<?php

namespace Tests\Feature\Owner;

use App\Actions\CreateCustomer;
use App\Actions\RecordFlourDeposit;
use App\Actions\RecordMovements;
use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Movement numbering, walk-in sales, "confirm payment", deleting a customer, and the data migration. */
class LedgerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $bakery = Bakery::create([
            'name' => 'مخبز',
            'subscription_status' => Bakery::STATUS_ACTIVE,
            'subscription_expires_at' => now()->addMonth(),
            'regular_price_per_kg' => 4,
            'flour_exchange_fee_per_kg' => 1,
        ]);

        return User::factory()->create(['role' => User::ROLE_BAKERY_OWNER, 'bakery_id' => $bakery->id]);
    }

    private function deposit(User $owner, Customer $customer, string $kg = '1'): void
    {
        app(RecordFlourDeposit::class)->handle($customer, ['kg_amount' => $kg, 'deposit_date' => '2026-11-20'], $owner);
    }

    public function test_numbers_start_at_one_per_bakery_and_follow_on(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $ca = app(CreateCustomer::class)->handle($a->bakery, ['name' => 'أ', 'mobile_number' => '1'], $a);
        $cb = app(CreateCustomer::class)->handle($b->bakery, ['name' => 'ب', 'mobile_number' => '2'], $b);

        $this->deposit($a, $ca);
        $this->deposit($b, $cb);
        $this->deposit($a, $ca);

        $this->assertSame([1, 2], Movement::where('bakery_id', $a->bakery_id)->orderBy('number')->pluck('number')->all());
        $this->assertSame([1], Movement::where('bakery_id', $b->bakery_id)->pluck('number')->all());
        $this->assertSame(2, $a->bakery->fresh()->last_movement_number);
    }

    public function test_a_number_can_never_be_used_twice_in_a_bakery(): void
    {
        $owner = $this->owner();
        $customer = app(CreateCustomer::class)->handle($owner->bakery, ['name' => 'أ', 'mobile_number' => '1'], $owner);
        $this->deposit($owner, $customer);

        $this->expectException(QueryException::class);
        Movement::create([...Movement::sole()->only('bakery_id', 'customer_id', 'ledger', 'type', 'amount', 'description', 'recorded_by_name', 'movement_date'), 'number' => 1]);
    }

    /** Real concurrent writers (separate PHP processes on one database file): no number repeats, none is skipped. */
    public function test_concurrent_writers_get_unique_gap_free_numbers(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ledger').'.sqlite';
        touch($file);
        $env = ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'DB_URL' => '', 'APP_ENV' => 'testing'];
        (new Process(['php', 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $env))->mustRun();

        $setup = 'DB::statement("PRAGMA busy_timeout = 10000");'
            .'$b = App\Models\Bakery::create(["name" => "x"]);'
            .'$u = App\Models\User::factory()->create(["bakery_id" => $b->id]);'
            .'App\Models\Customer::create(["bakery_id" => $b->id, "name" => "c", "mobile_number" => "1"]);';
        (new Process(['php', 'artisan', 'tinker', '--execute', $setup], base_path(), $env))->mustRun();

        $write = 'DB::statement("PRAGMA busy_timeout = 10000"); $b = App\Models\Bakery::first(); $u = App\Models\User::first(); $c = App\Models\Customer::first();'
            .'for ($i = 0, $done = 0; $done < 15 && $i < 200; $i++) { try {'
            .' Illuminate\Support\Facades\DB::transaction(fn () => app(App\Actions\RecordMovements::class)->handle($b, $u, ['
            .'["customer_id" => $c->id, "ledger" => "flour", "type" => "deposit", "amount_cents" => -100, "description" => "d", "movement_date" => "2026-11-20"],'
            .'["customer_id" => $c->id, "ledger" => "money", "type" => "payment", "amount_cents" => -100, "description" => "p", "movement_date" => "2026-11-20"]]));'
            .' $done++; } catch (Throwable $e) { usleep(random_int(1000, 20000)); } }';
        $workers = collect(range(1, 4))->map(fn () => tap(new Process(['php', 'artisan', 'tinker', '--execute', $write], base_path(), $env, null, 120))->start());
        $workers->each->wait();

        $pdo = new \PDO('sqlite:'.$file);
        $numbers = $pdo->query('select number from movements order by number')->fetchAll(\PDO::FETCH_COLUMN);
        $balance = $pdo->query('select flour_balance_kg from customers')->fetchColumn();
        $last = $pdo->query('select last_movement_number from bakeries')->fetchColumn();
        @unlink($file);

        $this->assertSame(range(1, 4 * 15 * 2), array_map('intval', $numbers));
        $this->assertSame(120, (int) $last);
        $this->assertEquals(-60, (float) $balance);
    }

    public function test_walk_in_cash_sales_enter_the_ledger_without_a_customer(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('panel.sales.store'), ['kg_amount' => '3', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => fake()->uuid()]);
        $this->actingAs($owner)->post(route('panel.sales.store'), ['kg_amount' => '2', 'payment_status' => 'unpaid', 'sale_date' => '2026-11-20', 'submission_id' => fake()->uuid()]);

        $this->assertSame(
            [[null, 'sale', '12.00', 'خبز 3 كغ × 4.00 ₪'], [null, 'payment', '-12.00', 'دفعة عن بيع 20/11/2026'], [null, 'sale', '8.00', 'خبز 2 كغ × 4.00 ₪']],
            Movement::orderBy('number')->get()->map(fn ($m) => [$m->customer_id, $m->type, $m->amount, $m->description])->all(),
        );
    }

    public function test_mark_paid_writes_one_payment_movement_for_what_was_left(): void
    {
        $owner = $this->owner();
        $customer = app(CreateCustomer::class)->handle($owner->bakery, ['name' => 'أ', 'mobile_number' => '1'], $owner);
        $this->actingAs($owner)->post(route('panel.customers.flour-sales.store', $customer), ['kg_amount' => '10', 'payment_status' => 'unpaid', 'sale_date' => '2026-11-20', 'submission_id' => fake()->uuid()]);
        $this->actingAs($owner)->post(route('panel.customers.payments.store', $customer), ['amount' => '4', 'payment_date' => '2026-11-21', 'submission_id' => fake()->uuid()]);
        $sale = Sale::sole();

        $this->actingAs($owner)->post(route('panel.sales.mark-paid', $sale))->assertSessionHas('success');
        $this->actingAs($owner)->post(route('panel.sales.mark-paid', $sale))->assertSessionHas('error', 'هذه العملية مدفوعة مسبقًا.');

        $this->assertTrue($sale->fresh()->isPaid());
        $this->assertSame(['-4.00', '-6.00'], Movement::where('type', 'payment')->orderBy('number')->pluck('amount')->all());
        $this->assertSame($sale->id, Movement::where('type', 'payment')->latest('number')->value('sale_id'));
        $this->assertSame(0, $customer->balanceCents('money'));
    }

    public function test_deleting_a_customer_deletes_their_movements_only(): void
    {
        $owner = $this->owner();
        $gone = app(CreateCustomer::class)->handle($owner->bakery, ['name' => 'أ', 'mobile_number' => '1', 'flour_balance_kg' => '5'], $owner);
        $kept = app(CreateCustomer::class)->handle($owner->bakery, ['name' => 'ب', 'mobile_number' => '2', 'flour_balance_kg' => '5'], $owner);
        $this->actingAs($owner)->post(route('panel.customers.flour-sales.store', $gone), ['kg_amount' => '1', 'payment_status' => 'paid', 'sale_date' => '2026-11-20', 'submission_id' => fake()->uuid()]);

        $this->actingAs($owner)->delete(route('panel.customers.destroy', $gone))->assertRedirect();

        $this->assertSame([$kept->id], Movement::pluck('customer_id')->unique()->values()->all());
        $this->assertSame(0, Sale::count());
        // Numbers are not reused after a deletion.
        $this->deposit($owner, $kept);
        $this->assertSame(6, Movement::max('number'));
    }

    /** Old rows → movements: every balance means the same thing before and after. */
    public function test_the_migration_keeps_every_balance(): void
    {
        $owner = $this->owner();
        $bakery = $owner->bakery;
        $walkInBakery = $this->owner()->bakery;
        DB::table('movements')->delete();
        $old = fn (array $c) => Customer::create(['bakery_id' => $bakery->id, 'mobile_number' => '1', ...$c]);

        // Old sign: positive flour_balance_kg = the customer HAS flour.
        $a = $old(['name' => 'أ', 'flour_balance_kg' => 12, 'created_at' => '2026-10-01 08:00:00']); // 30 deposited − 10 − 8 taken
        $b = $old(['name' => 'ب', 'flour_balance_kg' => 7]); // opening balance only
        $c = $old(['name' => 'ج', 'flour_balance_kg' => 0]);
        DB::table('flour_deposits')->insert(['bakery_id' => $bakery->id, 'customer_id' => $a->id, 'kg_amount' => 30, 'deposit_date' => '2026-10-02', 'created_by' => $owner->id]);
        $sale = fn (array $s) => Sale::forceCreate(['bakery_id' => $bakery->id, 'sale_type' => 'flour_exchange', 'price_per_kg' => 2, 'created_by' => $owner->id, ...$s]);
        // Paid when recorded (paid_at = created_at), then one paid partly by the payment row below.
        $sale(['customer_id' => $a->id, 'kg_amount' => 10, 'total_amount' => 20, 'paid_amount' => 20, 'payment_status' => 'paid', 'paid_at' => '2026-10-03 09:00:00', 'created_at' => '2026-10-03 09:00:00', 'sale_date' => '2026-10-03']);
        $sale(['customer_id' => $a->id, 'kg_amount' => 8, 'total_amount' => 16, 'paid_amount' => 6, 'payment_status' => 'unpaid', 'sale_date' => '2026-10-04']);
        DB::table('payments')->insert(['bakery_id' => $bakery->id, 'customer_id' => $a->id, 'amount' => 6, 'payment_date' => '2026-10-05', 'notes' => 'نقدًا', 'created_by' => $owner->id]);
        // Overpaid in the old data (a payment row with no sale left to cover): kept as an opening balance.
        DB::table('payments')->insert(['bakery_id' => $bakery->id, 'customer_id' => $c->id, 'amount' => 9, 'payment_date' => '2026-10-05']);
        Sale::create(['bakery_id' => $walkInBakery->id, 'sale_type' => 'cash', 'kg_amount' => 5, 'price_per_kg' => 6, 'total_amount' => 30, 'paid_amount' => 0, 'payment_status' => 'unpaid', 'sale_date' => '2026-10-06']);

        $before = Customer::all()->mapWithKeys(fn (Customer $x) => [$x->id => [-Movement::cents($x->flour_balance_kg), Movement::cents($x->outstandingBalance())]]);

        (require database_path('migrations/2026_11_26_000002_backfill_movements.php'))->up();

        foreach (Customer::all() as $x) {
            $this->assertSame($before[$x->id], [$x->balanceCents('flour'), $x->balanceCents('money')], "customer {$x->name}");
            $this->assertSame($x->balanceCents('flour'), Movement::cents($x->flour_balance_kg), 'cached flour = movements');
        }
        $this->assertSame(range(1, Movement::where('bakery_id', $bakery->id)->count()), Movement::where('bakery_id', $bakery->id)->orderBy('number')->pluck('number')->all());
        $this->assertSame(
            ['deposit', 'flour_withdrawal', 'exchange_fee', 'payment', 'flour_withdrawal', 'exchange_fee', 'payment'],
            Movement::where('customer_id', $a->id)->orderBy('number')->pluck('type')->all(),
        );
        $this->assertSame('دفعة عن بيع 03/10/2026', Movement::where('customer_id', $a->id)->where('type', 'payment')->orderBy('number')->value('description'));
        $this->assertSame(['opening_balance', '-7.00'], [Movement::where('customer_id', $b->id)->value('type'), Movement::where('customer_id', $b->id)->value('amount')]);
        $this->assertSame(['payment', 'opening_balance'], Movement::where('customer_id', $c->id)->orderBy('amount')->pluck('type')->all());
        $this->assertSame(['sale'], Movement::where('bakery_id', $walkInBakery->id)->pluck('type')->all());
        $this->assertSame(Movement::where('bakery_id', $bakery->id)->count(), $bakery->fresh()->last_movement_number);
    }

    public function test_record_movements_skips_zero_amounts(): void
    {
        $owner = $this->owner();

        $this->assertCount(0, app(RecordMovements::class)->handle($owner->bakery, $owner, [
            ['ledger' => 'money', 'type' => 'sale', 'amount_cents' => 0, 'description' => 'x', 'movement_date' => '2026-11-20'],
        ]));
        $this->assertSame(0, $owner->bakery->fresh()->last_movement_number);
    }
}
