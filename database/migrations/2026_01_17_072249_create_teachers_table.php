<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->string('name');
$table->string('email')->unique();
$table->string('phone');
$table->string('designation');

            $table->string('qualification')->nullable();
$table->string('subject_specialization')->nullable();
$table->date('date_of_joining')->nullable();
$table->decimal('salary', 10, 2)->nullable();
$table->text('address')->nullable();
$table->string('aadhar_number')->nullable();
$table->string('bank_account_number')->nullable();
$table->string('ifsc_code')->nullable();
$table->string('experience_details')->nullable();
$table->string('profile_image')->nullable();
$table->string('gender')->nullable();
$table->string('wing')->nullable();
$table->string('teacher_type')->nullable();
$table->json('subjects')->nullable();
$table->string('employee_id')->nullable();
$table->string('uan_number')->nullable();
$table->string('pan_number')->nullable();
$table->string('employment_type')->nullable();
$table->date('date_of_birth')->nullable();
$table->string('emergency_contact')->nullable();
$table->string('educational_qualification')->nullable();
$table->string('training_certificates')->nullable();

            $table->timestamps();
            $table->softDeletes(); // deleted_at column
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};