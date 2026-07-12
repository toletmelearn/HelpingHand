<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fee_refunds.type was a DB-level enum('refund','reversal'). Adding a
     * 'deposit_refund' value the portable way -- widen to a plain string,
     * same approach already used for fee_collections.payment_mode in
     * 2026_06_19_160000_change_payment_mode_in_fee_collections_table.php --
     * rather than a driver-specific enum ALTER.
     */
    public function up(): void
    {
        Schema::table('fee_refunds', function (Blueprint $table) {
            $table->string('type', 30)->change();
        });
    }

    public function down(): void
    {
        Schema::table('fee_refunds', function (Blueprint $table) {
            $table->enum('type', ['refund', 'reversal'])->change();
        });
    }
};
