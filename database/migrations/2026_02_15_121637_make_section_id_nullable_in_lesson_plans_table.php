<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make section_id nullable to fix SQL error
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to non-nullable (requires all records to have section_id)
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('section_id')->nullable(false)->change();
        });
    }
};