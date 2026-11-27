<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Two (here three) devices of one bakery pushing at the same moment, as separate processes on one database:
 * the server hands out gap-free movement numbers and the balances still equal the sum of the movements.
 */
class SyncConcurrencyTest extends TestCase
{
    use ApiHelpers;

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = sys_get_temp_dir().'/bakery-concurrency-'.uniqid().'.sqlite';
        touch($this->database);
        config(['database.connections.sqlite.database' => $this->database]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->database);

        parent::tearDown();
    }

    public function test_concurrent_pushes_from_several_devices_get_gap_free_numbers(): void
    {
        $owner = $this->bakeryOwner();
        $customer = $this->customerOf($owner);
        $devices = [$owner, $this->staffOf($owner, User::permissionKeys()), $this->staffOf($owner, User::permissionKeys())];
        $go = $this->database.'.go';
        $pushes = 10;

        $processes = array_map(function (User $user) use ($customer, $pushes, $go) {
            $process = proc_open(
                [PHP_BINARY, __DIR__.'/concurrent-push.php', $user->createToken('هاتف')->plainTextToken, (string) $customer->id, (string) $pushes, $go],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                [...getenv(), 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'],
            );

            return [$process, $pipes];
        }, $devices);

        touch($go);
        $results = array_map(function (array $p) {
            [$process, $pipes] = $p;
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            proc_close($process);

            return json_decode($out, true) ?? $this->fail("worker failed: {$err}{$out}");
        }, $processes);
        @unlink($go);

        $statuses = collect($results)->flatten(1)->flatMap(fn ($r) => $r['status'] === 200 ? array_column($r['body']['results'], 'status') : ['http '.$r['status']]);
        $this->assertSame(array_fill(0, count($devices) * $pushes * 3, 'accepted'), $statuses->all());

        // Per push: flour sale 2 movements, deposit 1, paid cash sale 2.
        $total = count($devices) * $pushes * 5;
        $this->assertSame(range(1, $total), Movement::orderBy('number')->pluck('number')->all());
        $this->assertSame($total, (int) $owner->bakery->fresh()->last_movement_number);

        $customer = Customer::find($customer->id);
        $this->assertBalancesMatchMovements($customer);
        $this->assertSame('عليه '.kg(count($devices) * $pushes).'', flour_balance($customer->flour_balance_kg));
    }
}
