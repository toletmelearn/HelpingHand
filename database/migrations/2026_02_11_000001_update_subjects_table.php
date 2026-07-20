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
        // Add missing columns to existing subjects table
        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                if (!Schema::hasColumn('subjects', 'subject_type')) {
                    $table->string('subject_type')->default('scholastic')->after('code');
                }
                if (!Schema::hasColumn('subjects', 'sort_order')) {
                    $table->integer('sort_order')->default(0)->after('is_active');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('subjects')) {
            Schema::table('subjects', function (Blueprint $table) {
                if (Schema::hasColumn('subjects', 'subject_type')) {
                    $table->dropColumn('subject_type');
                }
                if (Schema::hasColumn('subjects', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
            });
        }
    }
};