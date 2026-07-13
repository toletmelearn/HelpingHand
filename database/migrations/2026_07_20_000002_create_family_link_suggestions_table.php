<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The detection queue for sibling matches found by phone number at
     * admission -- Student::booted() creates a 'pending' row here instead
     * of silently setting family_id, per the "admin confirmation" spec.
     * matched_family_id is set when the candidate sibling already belongs
     * to a family; candidate_student_id is set instead when the candidate
     * is another unlinked student (no Family exists yet -- confirming
     * creates one).
     */
    public function up(): void
    {
        Schema::create('family_link_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('matched_family_id')->nullable()->constrained('families')->nullOnDelete();
            $table->foreignId('candidate_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('match_basis', 30)->default('mobile');
            $table->string('matched_value', 50)->nullable();
            $table->string('status', 20)->default('pending'); // pending, confirmed, dismissed
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_link_suggestions');
    }
};
