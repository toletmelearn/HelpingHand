<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Free-text (an existing family's admission number, or just a
            // name) rather than a validated FK -- office staff record this
            // at admission time and it's read-only input for the discount
            // engine's referral rule, not a relationship the rest of the
            // app needs to join against.
            $table->string('referred_by_admission_no')->nullable()->after('is_rte');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('referred_by_admission_no');
        });
    }
};
