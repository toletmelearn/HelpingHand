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
        Schema::table('lesson_plans', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('lesson_plans', 'title')) {
                $table->string('title')->after('subject_id');
            }
            if (!Schema::hasColumn('lesson_plans', 'start_date')) {
                $table->date('start_date')->nullable()->after('plan_type');
            }
            if (!Schema::hasColumn('lesson_plans', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('lesson_plans', 'full_content')) {
                $table->text('full_content')->after('end_date');
            }
            if (!Schema::hasColumn('lesson_plans', 'parent_visible_content')) {
                $table->text('parent_visible_content')->nullable()->after('full_content');
            }
            if (!Schema::hasColumn('lesson_plans', 'show_to_parents')) {
                $table->boolean('show_to_parents')->default(false)->after('parent_visible_content');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_plans', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['title', 'start_date', 'end_date', 'full_content', 'parent_visible_content', 'show_to_parents'] as $col) {
                if (Schema::hasColumn('lesson_plans', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};