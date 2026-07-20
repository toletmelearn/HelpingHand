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
        Schema::dropIfExists('exam_papers');
        Schema::create('exam_papers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('exam_type');
            $table->string('academic_year')->nullable();
            $table->string('semester')->nullable();
            $table->string('paper_type')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('subject');
            $table->text('instructions')->nullable();
            $table->longText('paper_content')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('file_extension')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->boolean('is_answer_key')->default(false);
            $table->integer('duration_minutes')->nullable();
            $table->integer('total_marks')->nullable();
            $table->date('exam_date')->nullable();
            $table->string('access_level')->default('private');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('created_by_teacher')->nullable();
            $table->enum('status', ['draft','submitted','approved','rejected'])->default('draft');
            $table->unsignedBigInteger('approved_by_admin')->nullable();
            $table->unsignedBigInteger('approved_by_exam_dept')->nullable();
            $table->timestamps();
            
            $table->foreign('exam_id')->references('id')->on('exams')->onDelete('cascade');
            $table->foreign('class_id')->references('id')->on('school_classes')->onDelete('cascade');
            $table->index(['exam_id', 'class_id', 'subject']);
            $table->index('status');
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
        Schema::dropIfExists('exam_papers');
    }
};
