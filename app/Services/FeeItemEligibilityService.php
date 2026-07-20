<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\FeeStructureItem;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for "should this fee structure item be billed to
 * this student right now" -- used by every code path that generates fee
 * charges (new-structure assignment, student promotion, and the
 * StudentFeeAssignment model event), so a fee marked as admission-only or
 * one-time can't be re-billed just because one of those paths forgot to
 * check billing_frequency.
 */
class FeeItemEligibilityService
{
    public static function isBillable(FeeStructureItem $item, Student $student, ?string $structureAcademicYear): bool
    {
        $frequency = $item->billing_frequency;

        // Session-admission and one-time items must never be charged twice
        // to the same student, regardless of which academic year's fee
        // structure (re-)generated the charge.
        if (in_array($frequency, ['session_wise_admission', 'one_time'], true)
            && self::alreadyCharged($student->id, $item->fee_type_id)) {
            return false;
        }

        if ($frequency === 'session_wise_admission') {
            return self::isNewAdmissionForYear($student, $structureAcademicYear);
        }

        if ($frequency === 'session_wise_continuing') {
            return !self::isNewAdmissionForYear($student, $structureAcademicYear);
        }

        return true;
    }

    protected static function alreadyCharged(int $studentId, ?int $feeTypeId): bool
    {
        if (!$feeTypeId) {
            return false;
        }

        return StudentFeeLedger::where('student_id', $studentId)
            ->where('fee_type_id', $feeTypeId)
            ->where('reference_type', 'fee_structure_item')
            ->exists();
    }

    /**
     * Fails open (treats the student as a new admission) when session data
     * can't be resolved, matching the existing BulkFeeAssignmentService
     * behavior -- fee assignment must never silently break for schools
     * without academic-session data configured.
     */
    protected static function isNewAdmissionForYear(Student $student, ?string $academicYear): bool
    {
        if (!$academicYear
            || !Schema::hasTable('academic_sessions')
            || !Schema::hasColumn('students', 'admission_session_id')) {
            return true;
        }

        $session = AcademicSession::where('code', $academicYear)
            ->orWhere('name', $academicYear)
            ->first();

        if (!$session) {
            return true;
        }

        return $student->admission_session_id === $session->id;
    }
}
