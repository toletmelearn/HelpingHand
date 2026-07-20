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
        Schema::create('defaulter_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->unique();
            $table->string('stage'); // 'Reminder', 'Phone Call', 'Warning', 'Principal Notice', 'Exam Restriction', 'Result Hold', 'TC Hold', 'Cleared'
            $table->decimal('outstanding_amount', 12, 2)->default(0.00);
            $table->timestamp('last_action_date')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('defaulter_stages');
    }
};
