<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_collections', function (Blueprint $table) {
            $table->string('payment_mode', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_collections', function (Blueprint $table) {
            $table->enum('payment_mode', ['cash', 'upi', 'bank', 'online'])->change();
        });
    }
};
