<?php

namespace App\Models;

use App\Models\Concerns\SyncsToDevices;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bank or e-wallet in the bakery's list. Payments copy its name as text, so deleting
 * or renaming it never changes a recorded movement.
 */
#[Fillable(['bakery_id', 'name'])]
class Bank extends Model
{
    use SyncsToDevices;

    public function bakery(): BelongsTo
    {
        return $this->belongsTo(Bakery::class);
    }
}
