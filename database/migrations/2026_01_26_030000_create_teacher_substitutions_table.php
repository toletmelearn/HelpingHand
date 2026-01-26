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
        Schema::create('teacher_substitutions', function (Blueprint $table) {
            $table->id();
            $table->date('substitution_date');
            $table->unsignedBigInteger('absent_teacher_id');
            $table->unsignedBigInteger('substitute_teacher_id')->nullable();
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('subject_id');
            $table->integer('period_number');
            $table->string('period_name')->nullable();
            $table->string('status')->default('pending'); // pending, assigned, approved, cancelled
            $table->text('reason')->nullable(); // reason for absence
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance (shortened names to avoid MySQL identifier limits)
            $table->index(['substitution_date', 'absent_teacher_id'], 'ts_date_absent_idx');
            $table->index(['substitution_date', 'substitute_teacher_id'], 'ts_date_sub_idx');
            $table->index(['substitution_date', 'class_id', 'section_id'], 'ts_date_cls_sec_idx');
            $table->index('status', 'ts_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_substitutions');
    }
};