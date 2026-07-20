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
        Schema::create('teacher_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->unique()->constrained('teachers')->onDelete('cascade');
            $table->unsignedBigInteger('school_id')->nullable(); // Remove FK constraint - schools table may not exist
            $table->string('username')->unique(); // mobile OR employee_id
            $table->string('password');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->boolean('force_password_change')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->rememberToken();
            $table->timestamps();
            
            $table->index(['username', 'status']);
            $table->index('teacher_id');
            $table->index('school_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_logins');
    }
};
