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
        if (!Schema::hasColumn('school_classes', 'class_order')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->unsignedInteger('class_order')->after('name');
            });
        }

        if (!Schema::hasColumn('school_classes', 'is_active')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('description');
            });
        }

        // Ensure unique index on class_order exists
        $indexExists = false;
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            $indexExists = !empty(DB::select("SELECT INDEX_NAME FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'school_classes' AND column_name = 'class_order'"));
        }
        if (!$indexExists) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->unique('class_order');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropColumn(['class_order', 'is_active']);
        });
    }
};
