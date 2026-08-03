<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bakery_id', 'customer_id', 'sale_type', 'kg_amount', 'price_per_kg',
    'total_amount', 'payment_status', 'paid_at', 'sale_date', 'notes', 'created_by',
])]
class Sale extends Model
{
    const TYPE_CASH = 'cash';

    const TYPE_FLOUR_EXCHANGE = 'flour_exchange';

    const STATUS_PAID = 'paid';

    const STATUS_UNPAID = 'unpaid';

    protected function casts(): array
    {
        return [
            'kg_amount' => 'decimal:2',
            'price_per_kg' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'sale_date' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFlourExchange(): bool
    {
        return $this->sale_type === self::TYPE_FLOUR_EXCHANGE;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::STATUS_PAID;
    }
}
