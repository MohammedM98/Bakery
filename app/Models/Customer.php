<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bakery_id', 'name', 'mobile_number', 'flour_balance_kg', 'notes'])]
class Customer extends Model
{
    /** Cached copy of the sum of the flour movements, kept in step by RecordMovements. Positive = عليه. */
    protected function casts(): array
    {
        return [
            'flour_balance_kg' => 'decimal:2',
        ];
    }

    public function bakery(): BelongsTo
    {
        return $this->belongsTo(Bakery::class);
    }

    public function flourDeposits(): HasMany
    {
        return $this->hasMany(FlourDeposit::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /** Sum of the ledger's movements in cents: positive = عليه, negative = له. */
    public function balanceCents(string $ledger): int
    {
        return Movement::cents($this->movements()->where('ledger', $ledger)->sum('amount'));
    }

    /**
     * The ledger's statement between two dates (either may be null: unbounded), as the page and
     * the future PDF / Excel export show it: the balance before the start, each movement with
     * the running balance after it, and the closing balance. Amounts are decimal strings.
     *
     * @return array{opening: string, closing: string, rows: array<int, array<string, mixed>>}
     */
    public function statement(string $ledger, ?string $from, ?string $to): array
    {
        $movements = $this->movements()->where('ledger', $ledger);
        $balance = $from ? Movement::cents((clone $movements)->whereDate('movement_date', '<', $from)->sum('amount')) : 0;
        $opening = $balance;

        $rows = $movements
            ->when($from, fn ($q) => $q->whereDate('movement_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('movement_date', '<=', $to))
            ->orderBy('movement_date')->orderBy('number')
            ->get()
            ->map(function (Movement $m) use (&$balance) {
                $amount = Movement::cents($m->amount);
                $balance += $amount;

                return [
                    'number' => $m->number,
                    'date' => $m->movement_date->toDateString(),
                    'description' => $m->description,
                    'note' => $m->note,
                    'transfer' => $m->type === Movement::TYPE_PAYMENT
                        ? ($m->from_bank ? $m->from_bank.' ← '.$m->to_bank : 'نقدًا')
                        : null,
                    'transfer_reference' => $m->transfer_reference,
                    'debit' => $amount > 0 ? Movement::decimal($amount) : null,
                    'credit' => $amount < 0 ? Movement::decimal(-$amount) : null,
                    'balance' => Movement::decimal($balance),
                    'recorded_by' => $m->recorded_by_name,
                ];
            });

        return ['opening' => Movement::decimal($opening), 'closing' => Movement::decimal($balance), 'rows' => $rows->all()];
    }

    /** Legacy: what the unpaid sales still owe. The money balance is balanceCents('money'). */
    public function outstandingBalance(): float
    {
        return round((float) $this->sales()
            ->where('payment_status', Sale::STATUS_UNPAID)
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as total')
            ->value('total'), 2);
    }
}
