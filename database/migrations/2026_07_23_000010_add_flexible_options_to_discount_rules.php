<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            // Every rule type previously computed a percentage-of-fee
            // discount only -- schools also commonly give flat-rupee
            // scholarships (e.g. "Rs 5,000 off", not "10% off"). Cross-
            // cutting so any type (old or new) can be either.
            $table->string('discount_mode')->default('percentage')->after('type'); // percentage|flat_amount
            $table->decimal('flat_amount', 10, 2)->nullable()->after('discount_mode');

            // No rule previously had a validity window -- a scholarship
            // scoped to one academic year, or an early-payment discount
            // scoped to a cutoff date, had no way to expire on its own.
            $table->date('valid_from')->nullable()->after('flat_amount');
            $table->date('valid_until')->nullable()->after('valid_from');

            // Absolute ceiling on a single application of this rule,
            // independent of the global stacking cap (which only applies
            // when multiple rules combine on the same fee head).
            $table->decimal('max_cap_amount', 10, 2)->nullable()->after('valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropColumn(['discount_mode', 'flat_amount', 'valid_from', 'valid_until', 'max_cap_amount']);
        });
    }
};
