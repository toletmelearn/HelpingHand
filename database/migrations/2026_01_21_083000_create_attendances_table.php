<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'half_day']);
            $table->text('remarks')->nullable();
            $table->string('period')->nullable(); // Period 1, Period 2, etc.
            $table->string('subject')->nullable(); // Subject name
            $table->string('class')->nullable(); // Class/Section
            $table->string('session')->nullable(); // Academic session (2023-24)
            $table->unsignedBigInteger('marked_by')->nullable(); // User ID who marked attendance
            $table->string('ip_address')->nullable();
            $table->string('device_info')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
            $table->foreign('marked_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for better performance
            $table->index(['date', 'class']);
            $table->index(['student_id', 'date']);
            $table->index(['teacher_id', 'date']);
            $table->index('status');
            $table->index('period');
            $table->unique(['student_id', 'date', 'period']); // Prevent duplicate attendance entries
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};