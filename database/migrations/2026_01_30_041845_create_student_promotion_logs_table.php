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
        Schema::create('student_promotion_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('academic_session_id')->nullable();
            $table->string('from_class');
            $table->string('to_class');
            $table->unsignedBigInteger('promoted_by')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('academic_session_id')->references('id')->on('academic_sessions')->onDelete('set null');
            $table->foreign('promoted_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index(['student_id', 'academic_session_id']);
            $table->index('promoted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_promotion_logs');
    }
};
