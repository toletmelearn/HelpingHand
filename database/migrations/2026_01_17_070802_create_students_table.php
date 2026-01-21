<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('father_name');
            $table->string('mother_name');
            $table->date('date_of_birth');
            $table->string('aadhar_number')->unique();
            $table->string('address');
            $table->string('phone');
            
            // === CATEGORIZATION COLUMNS ===
            $table->enum('gender', ['male', 'female', 'other'])->default('male');
            $table->string('category')->default('General');
            $table->string('class')->nullable();
            $table->string('section')->nullable();
            $table->integer('roll_number')->nullable();
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])->nullable();
            $table->string('nationality')->default('Indian');
            $table->text('medical_history')->nullable();
            $table->string('previous_school')->nullable();
            // ==============================
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};