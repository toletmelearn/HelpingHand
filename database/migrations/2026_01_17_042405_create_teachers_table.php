<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->string('qualification'); // B.Ed, M.Ed, etc.
            $table->string('subject_specialization'); // Math, Science, etc.
            $table->date('date_of_joining');
            $table->decimal('salary', 10, 2); // Monthly salary
            $table->text('address');
            $table->string('aadhar_number')->unique();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->text('experience_details')->nullable(); // Previous experience
            $table->string('profile_image')->nullable();
            $table->timestamps();
            $table->softDeletes(); // For archiving (Feature #18)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};