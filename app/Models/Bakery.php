<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'phone', 'address', 'subscription_status', 'subscription_expires_at',
    'regular_price_per_kg', 'flour_exchange_fee_per_kg', 'created_by',
])]
class Bakery extends Model
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'subscription_expires_at' => 'date',
            'regular_price_per_kg' => 'decimal:2',
            'flour_exchange_fee_per_kg' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function owners(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function flourDeposits(): HasMany
    {
        return $this->hasMany(FlourDeposit::class);
    }

    public function isSubscriptionActive(): bool
    {
        if ($this->subscription_status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->subscription_expires_at === null) {
            return true;
        }

        return ! $this->subscription_expires_at->lt(Carbon::today());
    }

    protected function daysUntilExpiry(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->subscription_expires_at
                ? Carbon::today()->diffInDays($this->subscription_expires_at, false)
                : null,
        );
    }
}
