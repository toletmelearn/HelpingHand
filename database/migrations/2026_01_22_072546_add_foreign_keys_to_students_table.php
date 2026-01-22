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
        Schema::table('students', function (Blueprint $table) {
            // Add foreign key columns if they don't exist
            if (!Schema::hasColumn('students', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            
            if (!Schema::hasColumn('students', 'guardian_id')) {
                $table->unsignedBigInteger('guardian_id')->nullable()->after('user_id');
            }
            
            if (!Schema::hasColumn('students', 'class_id')) {
                $table->unsignedBigInteger('class_id')->nullable()->after('class');
            }
            
            if (!Schema::hasColumn('students', 'section_id')) {
                $table->unsignedBigInteger('section_id')->nullable()->after('class_id');
            }
            
            // Add indexes for performance (check if they don't exist)
            if (!Schema::hasIndex('students', 'students_user_id_index')) {
                $table->index('user_id');
            }
            if (!Schema::hasIndex('students', 'students_guardian_id_index')) {
                $table->index('guardian_id');
            }
            if (!Schema::hasIndex('students', 'students_class_id_index')) {
                $table->index('class_id');
            }
            if (!Schema::hasIndex('students', 'students_section_id_index')) {
                $table->index('section_id');
            }
            
            // Add foreign key constraints (skip if already exist)
            try {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                $table->foreign('guardian_id')->references('id')->on('guardians')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                $table->foreign('class_id')->references('id')->on('class_managements')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
            
            try {
                $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
            } catch (\Exception $e) {
                // Foreign key already exists, continue
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['user_id']);
            $table->dropForeign(['guardian_id']);
            $table->dropForeign(['class_id']);
            $table->dropForeign(['section_id']);
            
            // Drop columns
            $table->dropColumn(['user_id', 'guardian_id', 'class_id', 'section_id']);
        });
    }
};
