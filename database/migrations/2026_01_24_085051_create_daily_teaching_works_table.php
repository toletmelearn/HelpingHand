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
        Schema::create('daily_teaching_works', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('class_name', 50);
            $table->string('section', 10);
            $table->string('subject', 100);
            $table->integer('period_number')->nullable();
            $table->unsignedBigInteger('teacher_id');
            $table->string('topic_covered', 255);
            $table->text('teaching_summary')->nullable();
            $table->json('attachments')->nullable();
            $table->json('homework')->nullable();
            $table->string('status', 20)->default('published');
            $table->string('academic_session', 20)->nullable();
            $table->json('syllabus_mapping')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Ensure one entry per class-subject-day
            $table->unique(['date', 'class_name', 'section', 'subject'], 'unique_daily_teaching_per_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_teaching_works');
    }
};
