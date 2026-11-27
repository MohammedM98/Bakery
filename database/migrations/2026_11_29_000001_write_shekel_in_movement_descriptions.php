<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The currency is now written "شيكل" instead of "₪". Rewrites the generated descriptions already
 * stored (e.g. "خبز 30 كغ × 4.00 ₪"). Display text only: no amount, date or number changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('movements')->where('description', 'like', '%₪%')
            ->update(['description' => DB::raw("REPLACE(description, ' ₪', ' شيكل')")]);
    }

    public function down(): void
    {
        DB::table('movements')->where('description', 'like', '% شيكل%')
            ->update(['description' => DB::raw("REPLACE(description, ' شيكل', ' ₪')")]);
    }
};
