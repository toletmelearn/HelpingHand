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
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('class_name');
            $table->string('section');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('chapters')->nullable(); // Store chapters as JSON array with objectives
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('academic_session')->nullable();
            $table->decimal('total_duration_hours', 8, 2)->nullable(); // Total duration in hours
            $table->json('learning_objectives')->nullable(); // Learning objectives per chapter/unit
            $table->json('assessment_criteria')->nullable(); // Assessment criteria
            $table->string('status')->default('active'); // active, archived, draft
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes for performance
            $table->index(['subject', 'class_name', 'section']);
            $table->index(['status', 'academic_session']);
            $table->index(['created_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
