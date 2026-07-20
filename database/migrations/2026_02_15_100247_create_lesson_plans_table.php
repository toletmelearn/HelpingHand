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
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        Schema::dropIfExists('lesson_plans');
        Schema::create('lesson_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('subject_id');
            $table->string('title')->nullable();
            $table->enum('plan_type', ['daily', 'weekly', '15days', 'monthly'])->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('full_content')->nullable();
            $table->text('parent_visible_content')->nullable();
            $table->boolean('show_to_parents')->default(false);
            $table->string('topic')->nullable();
            $table->text('learning_objectives')->nullable();
            $table->text('teaching_method')->nullable();
            $table->text('homework_classwork')->nullable();
            $table->text('books_notebooks_required')->nullable();
            $table->text('submission_assessment_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
            $table->foreign('subject_id')->references('id')->on('subjects')->onDelete('cascade');
        });
        if ($driver !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lesson_plans');
    }
};
