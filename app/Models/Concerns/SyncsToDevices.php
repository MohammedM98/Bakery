<?php

namespace App\Models\Concerns;

use App\Models\Bakery;
use Illuminate\Support\Facades\DB;

/**
 * Stamps every save with the bakery's next sync version and leaves a tombstone on delete, for the
 * mobile app's `/sync/pull`. The version is taken under the bakery row lock inside the same
 * transaction as the write, so a version a device has seen never has an uncommitted row below it.
 *
 * Lock order is the app's usual one: this row first (an update or delete), then the bakery.
 * Mass updates and deletes through the query builder skip this (RecordMovements stamps its own).
 */
trait SyncsToDevices
{
    protected static function bootSyncsToDevices(): void
    {
        static::saving(function ($model) {
            if ($model->isDirty()) {
                $model->sync_version = Bakery::nextSyncVersion($model->bakery_id);
            }
        });

        static::deleted(fn ($model) => DB::table('sync_tombstones')->insert([
            'bakery_id' => $model->bakery_id,
            'entity' => $model->getTable(),
            'entity_id' => $model->getKey(),
            'sync_version' => Bakery::nextSyncVersion($model->bakery_id),
            'created_at' => now(),
        ]));
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options) {
            $this->lockOwnRow();

            return parent::save($options);
        });
    }

    public function delete(): ?bool
    {
        return DB::transaction(function () {
            $this->lockOwnRow();

            return parent::delete();
        });
    }

    private function lockOwnRow(): void
    {
        if ($this->exists) {
            static::query()->whereKey($this->getKey())->lockForUpdate()->value('id');
        }
    }
}
