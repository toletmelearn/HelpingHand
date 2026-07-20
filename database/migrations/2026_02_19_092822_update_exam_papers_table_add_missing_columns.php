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
        Schema::table('exam_papers', function (Blueprint $table) {
            // Add columns if they don't exist
            if (!Schema::hasColumn('exam_papers', 'class_id')) {
                $table->unsignedBigInteger('class_id')->nullable()->after('exam_id');
            }
            
            if (!Schema::hasColumn('exam_papers', 'subject')) {
                $table->string('subject')->nullable()->after('class_id');
            }
            
            if (!Schema::hasColumn('exam_papers', 'paper_content')) {
                $table->longText('paper_content')->nullable()->after('subject');
            }
            
            if (!Schema::hasColumn('exam_papers', 'file_path')) {
                $table->string('file_path')->nullable()->after('paper_content');
            }
            
            if (!Schema::hasColumn('exam_papers', 'created_by_teacher')) {
                $table->unsignedBigInteger('created_by_teacher')->nullable()->after('file_path');
            }
            
            if (!Schema::hasColumn('exam_papers', 'status')) {
                $table->enum('status', ['draft','submitted','approved','rejected'])->default('draft')->after('created_by_teacher');
            }
            
            if (!Schema::hasColumn('exam_papers', 'approved_by_admin')) {
                $table->unsignedBigInteger('approved_by_admin')->nullable();
            }
            
            if (!Schema::hasColumn('exam_papers', 'approved_by_exam_dept')) {
                $table->unsignedBigInteger('approved_by_exam_dept')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
