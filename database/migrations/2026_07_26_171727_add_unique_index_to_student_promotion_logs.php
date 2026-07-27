<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DB-level backstop for the app-level idempotency guard added in
     * StudentPromotionController::store() (remediation Task 2): a student
     * cannot be logged as promoted to the same destination class in the
     * same academic session twice. The application already skips repeat
     * submissions before this constraint would ever be hit -- this index
     * exists so that can never regress silently (e.g. a future direct
     * DB::table('student_promotion_logs')->insert() bypassing the guard).
     *
     * `to_class` is a string matching SchoolClass::name, not a FK -- this
     * table has no to_school_class_id column (only from_class/to_class
     * strings; see 2026_01_30_041845_create_student_promotion_logs_table).
     * If a future cleanup adds a to_school_class_id FK and drops to_class,
     * this index should be recreated on (student_id, academic_session_id,
     * to_school_class_id) instead.
     *
     * Verified read-only against the live DB before writing this migration:
     * zero rows currently violate (student_id, academic_session_id, to_class).
     */
    public function up(): void
    {
        Schema::table('student_promotion_logs', function (Blueprint $table) {
            $table->unique(['student_id', 'academic_session_id', 'to_class'], 'student_promotion_logs_student_session_to_class_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_promotion_logs', function (Blueprint $table) {
            $table->dropUnique('student_promotion_logs_student_session_to_class_unique');
        });
    }
};
