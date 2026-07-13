<?php

namespace App\Services;

use App\Models\Family;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ranks a family's students for sibling-discount purposes -- kept separate
 * from DiscountEngineService to keep that class focused on rule
 * evaluation, not family/ranking mechanics.
 */
class FamilyDiscountService
{
    /**
     * Returns the family's students ordered from eldest/most-senior (rank
     * 1 / "1st child") to youngest/most-junior (last rank), excluding
     * anyone who has left the school (TC issued or passed out) -- both are
     * append-only status-log entries, not a single current-status column,
     * so presence of either status row is enough to exclude a student from
     * live ranking regardless of when it happened.
     */
    public static function rankFamily(Family $family, string $rankBy = 'age'): Collection
    {
        $students = Student::where('family_id', $family->id)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('student_statuses')
                    ->whereColumn('student_statuses.student_id', 'students.id')
                    ->whereIn('status', ['tc_issued', 'passed_out']);
            })
            ->with('schoolClass')
            ->get();

        if ($rankBy === 'class') {
            return $students->sortByDesc(function (Student $student) {
                return optional($student->resolveCanonicalSchoolClass())->class_order ?? -1;
            })->values();
        }

        return $students->sortBy(function (Student $student) {
            return $student->date_of_birth ? $student->date_of_birth->timestamp : PHP_INT_MAX;
        })->values();
    }
}
