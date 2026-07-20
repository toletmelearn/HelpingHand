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
        Schema::table('student_transport_dues', function (Blueprint $table) {
            $table->unique(['student_id', 'month', 'academic_year'], 'student_transport_dues_unique');
        });

        Schema::table('student_fee_ledgers', function (Blueprint $table) {
            $table->unique(['student_id', 'reference_type', 'reference_id', 'description'], 'student_fee_ledgers_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_transport_dues', function (Blueprint $table) {
            $table->dropUnique('student_transport_dues_unique');
        });

        Schema::table('student_fee_ledgers', function (Blueprint $table) {
            $table->dropUnique('student_fee_ledgers_unique');
        });
    }
};
