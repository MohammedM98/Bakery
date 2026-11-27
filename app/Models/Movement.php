<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A money or flour movement on a customer's account (or a walk-in buyer's sale and payment,
 * with no customer). Never updated or deleted by the app: mistakes are corrected with an
 * adjustment. The only deletion is "delete the customer with all their records".
 *
 * `amount` is signed: positive = عليه (the customer took / owes), negative = له.
 */
#[Fillable([
    'bakery_id', 'number', 'customer_id', 'ledger', 'type', 'amount', 'description', 'note',
    'sale_id', 'corrects_movement_id', 'from_bank', 'to_bank', 'transfer_reference',
    'recorded_by', 'recorded_by_name', 'movement_date', 'sync_version',
])]
class Movement extends Model
{
    const LEDGER_MONEY = 'money';

    const LEDGER_FLOUR = 'flour';

    const TYPE_SALE = 'sale';

    const TYPE_EXCHANGE_FEE = 'exchange_fee';

    const TYPE_FLOUR_WITHDRAWAL = 'flour_withdrawal';

    const TYPE_DEPOSIT = 'deposit';

    const TYPE_PAYMENT = 'payment';

    const TYPE_ADJUSTMENT = 'adjustment';

    const TYPE_OPENING_BALANCE = 'opening_balance';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'movement_date' => 'date',
        ];
    }

    public function bakery(): BelongsTo
    {
        return $this->belongsTo(Bakery::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** Whole agorot / hundredths of a kilo, so sums and comparisons are exact. */
    public static function cents(float|int|string|null $value): int
    {
        return (int) round((float) $value * 100);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
