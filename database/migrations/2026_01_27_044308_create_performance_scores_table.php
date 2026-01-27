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
        Schema::create('performance_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('period_type'); // daily, weekly, monthly
            $table->decimal('punctuality_score', 5, 2)->default(0); // 0-100
            $table->decimal('discipline_rating', 5, 2)->default(0); // 0-100
            $table->decimal('consistency_index', 5, 2)->default(0); // 0-100
            $table->string('performance_grade')->nullable(); // A+, A, B+, B, C, D
            $table->integer('total_days')->default(0);
            $table->integer('present_days')->default(0);
            $table->integer('late_arrivals')->default(0);
            $table->integer('early_departures')->default(0);
            $table->integer('absent_days')->default(0);
            $table->decimal('avg_working_hours', 8, 2)->default(0);
            $table->json('detailed_metrics')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['teacher_id', 'period_start']);
            $table->index('period_type');
            $table->index('performance_grade');
            
            // Foreign key
            $table->foreign('teacher_id')->references('id')->on('teachers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_scores');
    }
};
