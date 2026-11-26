<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per money or flour movement: the single source of both customer balances.
 * `amount` is signed: positive = عليه (the customer took / owes), negative = له.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', function (Blueprint $table) {
            // Last movement number handed out; taken under a row lock so numbers are never reused.
            $table->unsignedInteger('last_movement_number')->default(0);
        });

        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->unsignedInteger('number');
            // Null for a walk-in buyer's sale and payment.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->string('ledger', 10);
            $table->string('type', 30);
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->text('note')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->foreignId('corrects_movement_id')->nullable()->constrained('movements')->nullOnDelete();
            // Transfer details, filled from session 5 on; a cash payment leaves them empty.
            $table->string('from_bank')->nullable();
            $table->string('to_bank')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recorded_by_name');
            $table->date('movement_date');
            $table->timestamps();

            $table->unique(['bakery_id', 'number']);
            $table->index(['customer_id', 'ledger', 'movement_date']);
            $table->index(['bakery_id', 'movement_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');

        Schema::table('bakeries', function (Blueprint $table) {
            $table->dropColumn('last_movement_number');
        });
    }
};
