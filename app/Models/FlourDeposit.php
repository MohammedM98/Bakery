<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bakery_id', 'customer_id', 'kg_amount', 'deposit_date', 'notes', 'created_by'])]
class FlourDeposit extends Model
{
    protected function casts(): array
    {
        return [
            'kg_amount' => 'decimal:2',
            'deposit_date' => 'date',
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
}
