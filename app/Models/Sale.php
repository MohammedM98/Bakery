<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bakery_id', 'customer_id', 'buyer_name', 'buyer_mobile', 'sale_type', 'kg_amount', 'price_per_kg',
    'total_amount', 'paid_amount', 'payment_status', 'paid_at', 'sale_date', 'notes', 'created_by',
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
            'paid_amount' => 'decimal:2',
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

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->paid_amount, 2));
    }

    public function isPartiallyPaid(): bool
    {
        return ! $this->isPaid() && (float) $this->paid_amount > 0;
    }

    public function buyerDisplayName(): string
    {
        return $this->customer->name ?? $this->buyer_name ?? 'عميل نقدي';
    }
}
