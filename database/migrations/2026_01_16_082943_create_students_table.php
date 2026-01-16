<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id(); // Auto-increment ID
            $table->string('name'); // Student ka naam
            $table->string('father_name'); // Father ka naam
            $table->string('mother_name'); // Mother ka naam
            $table->date('date_of_birth'); // Janam tithi
            $table->string('aadhar_number')->unique(); // Unique Aadhar
            $table->string('address'); // Ghar ka pata
            $table->string('phone'); // Phone number
            $table->timestamps(); // created_at aur updated_at automatically
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};