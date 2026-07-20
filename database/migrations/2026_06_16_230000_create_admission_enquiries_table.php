<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('candidate_name');
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('status')->default('enquiry'); // enquiry, interview, selected, closed
            $table->date('interview_date')->nullable();
            $table->decimal('interview_score', 5, 2)->nullable();
            $table->decimal('entrance_score', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_enquiries');
    }
};
