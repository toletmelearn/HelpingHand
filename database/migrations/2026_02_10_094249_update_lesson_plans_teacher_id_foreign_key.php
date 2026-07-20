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
        // Drop the existing foreign key constraint and recreate it to reference teachers table
        try {
            Schema::table('lesson_plans', function (Blueprint $table) {
                $table->dropForeign(['teacher_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnUpdate()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key constraint to teachers
        try {
            Schema::table('lesson_plans', function (Blueprint $table) {
                $table->dropForeign(['teacher_id']);
            });
        } catch (\Exception $e) {}
        
        // Recreate the foreign key constraint to reference users table
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('users')->cascadeOnUpdate()->restrictOnDelete();
        });
    }
};
