<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks manual exam-eligibility exceptions granted to a defaulting
     * student (Admin/Principal/Accountant only) -- lets a student sit an
     * exam despite their admit card otherwise being auto-revoked once they
     * reach the "Exam Restriction" defaulter stage. A row with
     * revoked_at = null is the currently-active override for that student;
     * history is kept rather than deleted so grants/revocations stay
     * auditable, matching how defaulter_logs already tracks this module's
     * other actions.
     */
    public function up(): void
    {
        Schema::create('defaulter_exam_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('granted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defaulter_exam_overrides');
    }
};
