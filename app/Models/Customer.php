<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bakery_id', 'name', 'mobile_number', 'flour_balance_kg', 'notes'])]
class Customer extends Model
{
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
}
