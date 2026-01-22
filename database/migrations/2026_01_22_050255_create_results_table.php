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
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('exam_id');
            $table->string('subject');
            $table->decimal('marks_obtained', 8, 2);
            $table->decimal('total_marks', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade');
            $table->string('academic_year');
            $table->string('term')->nullable();
            $table->text('comments')->nullable();
            $table->string('result_status')->default('pass'); // pass, fail, absent
            $table->timestamps();
            
            $table->index(['student_id', 'exam_id']);
            $table->unique(['student_id', 'exam_id', 'subject']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
