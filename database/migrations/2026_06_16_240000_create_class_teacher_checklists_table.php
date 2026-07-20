<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uniform_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->date('check_date');
            $table->boolean('is_compliant')->default(true);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        Schema::create('slow_learners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->date('diagnostic_date');
            $table->text('remedial_notes')->nullable();
            $table->string('progress_status')->default('stagnant'); // improving, stagnant
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slow_learners');
        Schema::dropIfExists('uniform_checks');
    }
};
