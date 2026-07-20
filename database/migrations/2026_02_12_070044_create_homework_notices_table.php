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
        Schema::create('homework_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['homework', 'notice', 'announcement'])->default('notice');
            $table->foreignId('class_id')->constrained('school_classes')->onDelete('cascade');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->onDelete('cascade');
            $table->date('due_date')->nullable();
            $table->timestamp('publish_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'published', 'archived'])->default('active');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homework_notices');
    }
};