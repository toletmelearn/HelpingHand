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
        Schema::create('student_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('status'); // passed_out, tc_issued, left_school, active, inactive
            $table->date('status_date');
            $table->string('reason')->nullable();
            $table->string('remarks')->nullable();
            $table->string('document_number')->nullable(); // TC number or certificate number
            $table->date('document_issue_date')->nullable();
            $table->string('issued_by')->nullable();
            $table->timestamps();
            
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->index(['student_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_statuses');
    }
};
