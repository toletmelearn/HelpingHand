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
        $driver = Schema::getConnection()->getDriverName();
        // First, disable foreign key checks to avoid constraint violations
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        
        // Drop the existing foreign key constraint to teacher_id
        try {
            Schema::table('lesson_plans', function (Blueprint $table) {
                $table->dropForeign(['teacher_id']);
            });
        } catch (\Exception $e) {
            // If constraint doesn't exist or query fails, continue
        }
        
        // Recreate the foreign key constraint to reference teachers table instead of users
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade')->onUpdate('cascade');
        });
        
        // Re-enable foreign key checks
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        // Reverse the foreign key constraint
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        
        // Drop the foreign key constraint to teacher_id
        try {
            Schema::table('lesson_plans', function (Blueprint $table) {
                $table->dropForeign(['teacher_id']);
            });
        } catch (\Exception $e) {
            // If constraint doesn't exist or query fails, continue
        }
        
        // Recreate the original foreign key constraint to users table
        Schema::table('lesson_plans', function (Blueprint $table) {
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
        
        // Re-enable foreign key checks
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }
};
