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
        Schema::table('exams', function (Blueprint $table) {
            // Add class_id and subject_id columns after exam_type
            $table->unsignedBigInteger('class_id')->nullable()->after('exam_type');
            $table->unsignedBigInteger('subject_id')->nullable()->after('class_id');
            
            // Add foreign key constraints
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['class_id']);
            $table->dropForeign(['subject_id']);
            
            // Drop columns
            $table->dropColumn(['class_id', 'subject_id']);
        });
    }
};
