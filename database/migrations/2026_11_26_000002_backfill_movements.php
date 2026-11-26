<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Builds the movements of every bakery from its sales, flour deposits and payments, numbered
 * per bakery in date order, and flips the flour balance sign to the new convention
 * (positive = عليه). Any difference between what the rows add up to and the balance a
 * customer had before is kept as an opening-balance movement, so no balance changes meaning.
 *
 * Self-contained on purpose (no app models): later changes to the models must not change it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $users = DB::table('users')->pluck('name', 'id');

            foreach (DB::table('bakeries')->orderBy('id')->pluck('id') as $bakeryId) {
                $this->backfillBakery($bakeryId, $users);
            }

            DB::table('customers')->update(['flour_balance_kg' => DB::raw('0 - flour_balance_kg')]);
        });
    }

    public function down(): void
    {
        DB::table('customers')->update(['flour_balance_kg' => DB::raw('0 - flour_balance_kg')]);
        DB::table('movements')->delete();
        DB::table('bakeries')->update(['last_movement_number' => 0]);
    }

    private function backfillBakery(int $bakeryId, $users): void
    {
        $events = [];
        $add = function (array $e) use (&$events, $users) {
            $e['recorded_by_name'] = $e['recorded_by'] ? ($users[$e['recorded_by']] ?? 'غير معروف') : 'ترحيل البيانات';
            $events[] = $e;
        };
        $cents = fn ($v) => (int) round((float) $v * 100);
        $flourCents = [];
        $moneyCents = [];

        $sales = DB::table('sales')->where('bakery_id', $bakeryId)->orderBy('sale_date')->orderBy('id')->get();
        $payments = DB::table('payments')->where('bakery_id', $bakeryId)->orderBy('payment_date')->orderBy('id')->get();
        $deposits = DB::table('flour_deposits')->where('bakery_id', $bakeryId)->get();

        // Payment rows paid the customer's sales oldest first; whatever part of a sale's
        // paid_amount they do not explain was paid at the sale or by "confirm payment".
        $pool = $payments->groupBy('customer_id')->map(fn ($rows) => $rows->sum(fn ($p) => $cents($p->amount)));

        foreach ($sales as $sale) {
            $base = [
                'customer_id' => $sale->customer_id, 'sale_id' => $sale->id, 'recorded_by' => $sale->created_by,
                'movement_date' => substr($sale->sale_date, 0, 10), 'created_at' => $sale->created_at, 'order' => 1,
                'corrects_movement_id' => null, 'note' => $sale->notes,
            ];
            $kgText = $this->kg($sale->kg_amount);
            $total = $cents($sale->total_amount);

            if ($sale->sale_type === 'flour_exchange') {
                $add([...$base, 'ledger' => 'flour', 'type' => 'flour_withdrawal', 'amount' => $cents($sale->kg_amount),
                    'description' => 'خبز مقابل طحين '.$kgText]);
                $flourCents[$sale->customer_id] = ($flourCents[$sale->customer_id] ?? 0) + $cents($sale->kg_amount);
            }

            if ($total > 0) {
                $add([...$base, 'ledger' => 'money', 'type' => $sale->sale_type === 'flour_exchange' ? 'exchange_fee' : 'sale', 'amount' => $total,
                    'description' => ($sale->sale_type === 'flour_exchange' ? 'رسم تبديل طحين ' : 'خبز ').$kgText.' × '.number_format((float) $sale->price_per_kg, 2).' ₪']);
            }

            $paid = $cents($sale->paid_amount);
            // A sale paid when it was recorded (paid_at within seconds of created_at) was never paid by a payment row.
            $paidAtSale = $sale->paid_at && $sale->created_at && abs(strtotime($sale->paid_at) - strtotime($sale->created_at)) <= 5;
            if ($sale->customer_id !== null && ! $paidAtSale) {
                $covered = min($paid, $pool[$sale->customer_id] ?? 0);
                $pool[$sale->customer_id] = ($pool[$sale->customer_id] ?? 0) - $covered;
                $paid -= $covered;
            }
            if ($paid > 0) {
                $add([...$base, 'ledger' => 'money', 'type' => 'payment', 'amount' => -$paid, 'order' => 2, 'note' => null,
                    'movement_date' => substr($sale->paid_at ?? $sale->sale_date, 0, 10),
                    'description' => 'دفعة عن بيع '.substr($sale->sale_date, 0, 10)]);
            }
            if ($sale->customer_id !== null) {
                $moneyCents[$sale->customer_id] = ($moneyCents[$sale->customer_id] ?? 0) + $total - $paid;
            }
        }

        foreach ($payments as $p) {
            $add(['customer_id' => $p->customer_id, 'sale_id' => null, 'recorded_by' => $p->created_by, 'corrects_movement_id' => null,
                'movement_date' => substr($p->payment_date, 0, 10), 'created_at' => $p->created_at, 'order' => 2,
                'ledger' => 'money', 'type' => 'payment', 'amount' => -$cents($p->amount), 'description' => 'دفعة', 'note' => $p->notes]);
            $moneyCents[$p->customer_id] = ($moneyCents[$p->customer_id] ?? 0) - $cents($p->amount);
        }

        foreach ($deposits as $d) {
            $add(['customer_id' => $d->customer_id, 'sale_id' => null, 'recorded_by' => $d->created_by, 'corrects_movement_id' => null,
                'movement_date' => substr($d->deposit_date, 0, 10), 'created_at' => $d->created_at, 'order' => 0,
                'ledger' => 'flour', 'type' => 'deposit', 'amount' => -$cents($d->kg_amount), 'description' => 'إيداع طحين '.$this->kg($d->kg_amount), 'note' => $d->notes]);
            $flourCents[$d->customer_id] = ($flourCents[$d->customer_id] ?? 0) - $cents($d->kg_amount);
        }

        // Opening balances: the difference between the old balance and what the rows add up to,
        // dated no later than the customer's first movement so they open the statement.
        $firstDate = [];
        foreach ($events as $e) {
            if ($e['customer_id'] !== null) {
                $firstDate[$e['customer_id']] = min($firstDate[$e['customer_id']] ?? $e['movement_date'], $e['movement_date']);
            }
        }

        foreach (DB::table('customers')->where('bakery_id', $bakeryId)->get() as $customer) {
            $outstanding = DB::table('sales')->where('customer_id', $customer->id)->where('payment_status', 'unpaid')->get()
                ->sum(fn ($s) => $cents($s->total_amount) - $cents($s->paid_amount));
            $differences = [
                'flour' => -$cents($customer->flour_balance_kg) - ($flourCents[$customer->id] ?? 0),
                'money' => $outstanding - ($moneyCents[$customer->id] ?? 0),
            ];
            foreach ($differences as $ledger => $difference) {
                if ($difference !== 0) {
                    $add(['customer_id' => $customer->id, 'sale_id' => null, 'recorded_by' => null, 'corrects_movement_id' => null,
                        'movement_date' => min(substr($customer->created_at ?? now(), 0, 10), $firstDate[$customer->id] ?? '9999-12-31'), 'created_at' => $customer->created_at, 'order' => -1,
                        'ledger' => $ledger, 'type' => 'opening_balance', 'amount' => $difference, 'description' => 'رصيد افتتاحي', 'note' => null]);
                }
            }
        }

        usort($events, fn ($a, $b) => [$a['movement_date'], $a['order'], (string) $a['created_at']] <=> [$b['movement_date'], $b['order'], (string) $b['created_at']]);

        $number = 0;
        foreach (array_chunk($events, 500) as $chunk) {
            DB::table('movements')->insert(array_map(function ($e) use ($bakeryId, &$number) {
                unset($e['order']);

                return [...$e, 'bakery_id' => $bakeryId, 'number' => ++$number,
                    'amount' => number_format($e['amount'] / 100, 2, '.', ''),
                    'created_at' => $e['created_at'] ?? now(), 'updated_at' => $e['created_at'] ?? now()];
            }, $chunk));
        }

        DB::table('bakeries')->where('id', $bakeryId)->update(['last_movement_number' => $number]);
    }

    private function kg($kg): string
    {
        return rtrim(rtrim(number_format((float) $kg, 2), '0'), '.').' كغ';
    }
};
