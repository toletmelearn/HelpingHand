<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporarily rename the status column, add new one with updated enum, then copy data back
        Schema::table('exam_papers', function (Blueprint $table) {
            // Add any missing columns
            if (!Schema::hasColumn('exam_papers', 'class_section')) {
                $table->string('class_section')->nullable();
            } else {
                $table->string('class_section')->nullable()->change();
            }
            
            if (!Schema::hasColumn('exam_papers', 'admin_approved_by')) {
                $table->unsignedBigInteger('admin_approved_by')->nullable();
            } else {
                $table->unsignedBigInteger('admin_approved_by')->nullable()->change();
            }
            
            if (!Schema::hasColumn('exam_papers', 'exam_approved_by')) {
                $table->unsignedBigInteger('exam_approved_by')->nullable();
            } else {
                $table->unsignedBigInteger('exam_approved_by')->nullable()->change();
            }
            
            if (!Schema::hasColumn('exam_papers', 'is_published')) {
                $table->boolean('is_published')->default(false);
            }
        });
        
        // Recreate the enum column
        Schema::table('exam_papers', function (Blueprint $table) {
            $table->enum('status', ['draft', 'submitted', 'admin_approved', 'exam_approved', 'published'])->default('draft')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_papers', function (Blueprint $table) {
            // Revert the status enum to original
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->change();
        });
    }
};
