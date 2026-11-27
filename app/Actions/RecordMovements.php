<?php

namespace App\Actions;

use App\Models\Bakery;
use App\Models\Customer;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place movements are written. Hands out the bakery's next movement numbers under a
 * row lock and keeps the customer's cached flour balance equal to the sum of its flour movements.
 * Call it inside the transaction that writes the related rows.
 */
class RecordMovements
{
    /**
     * @param  array<int, array{ledger: string, type: string, amount_cents: int, description: string, customer_id?: ?int, sale_id?: ?int, note?: ?string, corrects_movement_id?: ?int, movement_date: string}>  $movements
     * @return Collection<int, Movement>
     */
    public function handle(Bakery $bakery, User $user, array $movements): Collection
    {
        $movements = array_values(array_filter($movements, fn ($m) => $m['amount_cents'] !== 0));

        if ($movements === []) {
            return collect();
        }

        return DB::transaction(function () use ($bakery, $user, $movements) {
            // Serialises numbering per bakery: a second writer waits here until this one commits.
            $last = Bakery::whereKey($bakery->id)->lockForUpdate()->value('last_movement_number');
            // One mobile-sync version for the batch; the customers it touches get it too (their balances changed).
            $version = Bakery::nextSyncVersion($bakery->id);

            $created = collect($movements)->map(function (array $m, int $i) use ($bakery, $user, $last, $version) {
                $amount = $m['amount_cents'];
                unset($m['amount_cents']);

                return Movement::create([
                    ...$m,
                    'bakery_id' => $bakery->id,
                    'number' => $last + $i + 1,
                    'amount' => Movement::decimal($amount),
                    'recorded_by' => $user->id,
                    'recorded_by_name' => $user->name,
                    'sync_version' => $version,
                ]);
            });

            Bakery::whereKey($bakery->id)->update(['last_movement_number' => $last + count($movements)]);

            $created->where('ledger', Movement::LEDGER_FLOUR)->groupBy('customer_id')
                ->each(fn ($rows, $customerId) => Customer::whereKey($customerId)
                    ->increment('flour_balance_kg', Movement::decimal($rows->sum(fn ($m) => Movement::cents($m->amount)))));
            Customer::whereKey($created->pluck('customer_id')->filter()->unique()->all())->toBase()->update(['sync_version' => $version]);

            return $created;
        });
    }
}
