<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the mobile app's API (section 13) needs:
 * - a per-bakery change version, handed out under the bakery row lock like the movement numbers, stamped on
 *   every customer, bank and movement change, so `/sync/pull` has a cursor no committed change can slip behind;
 * - tombstones, so devices drop deleted customers and banks;
 * - the bakery's price history, so an offline sale's price can be checked against a price the bakery really had;
 * - durable idempotency keys, so a replayed client operation never writes twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', function (Blueprint $table) {
            $table->unsignedBigInteger('sync_version')->default(0);
        });

        foreach (['customers', 'banks', 'movements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('sync_version')->default(0);
                $table->index(['bakery_id', 'sync_version']);
            });
        }

        Schema::create('sync_tombstones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bakery_id')->constrained()->cascadeOnDelete();
            $table->string('entity', 20);
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('sync_version');
            $table->timestamp('created_at')->nullable();

            $table->index(['bakery_id', 'sync_version']);
        });

        Schema::create('bakery_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bakery_id')->constrained()->cascadeOnDelete();
            $table->decimal('regular_price_per_kg', 10, 2);
            $table->decimal('flour_exchange_fee_per_kg', 10, 2);
            $table->timestamp('effective_from');

            $table->index(['bakery_id', 'effective_from']);
        });

        // No foreign keys: an insert here must not take a shared lock on the bakery row before the
        // operation's own exclusive lock (the MySQL deadlock session 7 fixed in RecordSale).
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bakery_id')->nullable()->index();
            $table->unsignedBigInteger('user_id');
            $table->uuid('client_uuid');
            $table->string('operation', 40);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('status')->nullable();
            $table->json('response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'client_uuid']);
            $table->index(['bakery_id', 'operation', 'client_uuid']);
        });

        // Existing rows get versions a device's first pull (from 0) picks up. Movements take their own number, so no
        // version holds more than a pull page of them (/sync/pull pages by version); the bakery's version is above all.
        DB::table('customers')->update(['sync_version' => 1]);
        DB::table('banks')->update(['sync_version' => 1]);
        DB::table('movements')->update(['sync_version' => DB::raw('number')]);
        DB::table('bakeries')->update(['sync_version' => DB::raw('last_movement_number + 1')]);

        // The price each bakery has now, in effect since it was created: the best history there is.
        DB::table('bakeries')->orderBy('id')->each(fn ($bakery) => DB::table('bakery_prices')->insert([
            'bakery_id' => $bakery->id,
            'regular_price_per_kg' => $bakery->regular_price_per_kg,
            'flour_exchange_fee_per_kg' => $bakery->flour_exchange_fee_per_kg,
            'effective_from' => $bakery->created_at ?? now(),
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('bakery_prices');
        Schema::dropIfExists('sync_tombstones');

        foreach (['customers', 'banks', 'movements'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['bakery_id', 'sync_version']);
                $table->dropColumn('sync_version');
            });
        }

        Schema::table('bakeries', function (Blueprint $table) {
            $table->dropColumn('sync_version');
        });
    }
};
