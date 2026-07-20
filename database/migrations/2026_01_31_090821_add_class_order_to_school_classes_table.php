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
                $table->integer('class_order')->default(0)->after('name');
            });
        }

        if (!Schema::hasColumn('school_classes', 'is_active')) {
            Schema::table('school_classes', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('class_order');
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
