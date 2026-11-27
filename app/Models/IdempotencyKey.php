<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A client operation the API already ran (the app's `client_uuid`), with the answer it gave.
 * Replaying the key returns that answer and writes nothing. `status` is null only inside the
 * transaction that is still running the operation.
 */
#[Fillable(['bakery_id', 'user_id', 'client_uuid', 'operation', 'request_hash', 'status', 'response'])]
class IdempotencyKey extends Model
{
    protected function casts(): array
    {
        return ['response' => 'array'];
    }
}
