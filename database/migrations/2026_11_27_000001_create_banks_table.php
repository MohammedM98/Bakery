<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Each bakery's own list of banks and e-wallets, used for both sides of a transfer. */
    public function up(): void
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bakery_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['bakery_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banks');
    }
};
