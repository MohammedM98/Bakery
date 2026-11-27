<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name', 'phone', 'address', 'subscription_status', 'subscription_expires_at',
    'regular_price_per_kg', 'flour_exchange_fee_per_kg', 'created_by',
])]
class Bakery extends Model
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    /** Every price change is kept, so an offline sale's price can be checked against the prices the bakery had. */
    protected static function booted(): void
    {
        static::saved(function (Bakery $bakery) {
            if ($bakery->wasRecentlyCreated || $bakery->wasChanged(['regular_price_per_kg', 'flour_exchange_fee_per_kg'])) {
                // From the row: a bakery created without prices has the columns' defaults, not model attributes.
                $prices = DB::table('bakeries')->where('id', $bakery->id)->first(['regular_price_per_kg', 'flour_exchange_fee_per_kg']);
                DB::table('bakery_prices')->insert([...(array) $prices, 'bakery_id' => $bakery->id, 'effective_from' => now()]);
            }
        });
    }

    /**
     * The bakery's next change version for the mobile sync (see SyncsToDevices). Call it inside the
     * write's transaction: the row lock holds every other writer of this bakery until it commits.
     */
    public static function nextSyncVersion(int $bakeryId): int
    {
        $version = static::whereKey($bakeryId)->lockForUpdate()->value('sync_version') + 1;
        static::whereKey($bakeryId)->toBase()->update(['sync_version' => $version]);

        return $version;
    }

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

    /** Every account of the bakery: its owner and its staff. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owners(): HasMany
    {
        return $this->users()->where('role', User::ROLE_BAKERY_OWNER);
    }

    public function staff(): HasMany
    {
        return $this->users()->where('role', User::ROLE_BAKERY_STAFF);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(Bank::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
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

    /** Extends from the current expiry if it is still in the future, otherwise from today, and reactivates. */
    public function renew(int $months): void
    {
        $base = $this->subscription_expires_at && $this->subscription_expires_at->isFuture()
            ? $this->subscription_expires_at
            : now();

        $this->update([
            'subscription_status' => self::STATUS_ACTIVE,
            'subscription_expires_at' => $base->copy()->addMonths($months)->toDateString(),
        ]);
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
