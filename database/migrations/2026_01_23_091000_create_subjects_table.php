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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., 'Mathematics', 'Physics', 'Chemistry'
            $table->string('code')->unique(); // e.g., 'MATH', 'PHY', 'CHEM'
            $table->text('description')->nullable();
            $table->integer('max_marks')->default(100);
            $table->integer('pass_marks')->default(33);
            $table->enum('type', ['theory', 'practical', 'both'])->default('theory');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};