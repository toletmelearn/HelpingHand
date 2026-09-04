<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Support\Facades\DB;

/**
 * Academic setup completion: Class Teacher assignment. The canonical source
 * of truth is teacher_class_subject_assignments.is_class_teacher -- it's the
 * only one of the four historical "class teacher" tables with real data
 * (266 rows, 28 class-teacher flags) and the only one any real consumer
 * (Timetable\GeneratorService, FeasibilityService, TeacherDashboardController,
 * API\TeacherController, TeacherAcademicService/teacher portal) actually
 * reads. ClassTeacherAssignment (free-text class, no section),
 * TeacherClassAssignment (class FK but no section at all), and the bare
 * class_teacher table (0 rows, backs the currently-broken
 * Admin\ClassTeacherController/ClassTeacherPolicy) are legacy dead weight --
 * left in place, not deleted, but not built on.
 *
 * This service centralizes the "assign/change/remove a class teacher" rules
 * so the new dedicated admin screen enforces exactly the same safety
 * invariants Admin\TeacherSubjectAssignmentController::store()/update()
 * already established for the is_class_teacher checkbox on the generic
 * subject-assignment form: at most 2 classes per teacher, and assigning a
 * new class teacher for a class+section automatically un-assigns whoever
 * held it before (never two teachers flagged for the same class+section).
 * Deliberately NOT wired into that existing controller -- this is new call
 * sites reusing the same rules, not a refactor of already-tested code.
 */
class ClassTeacherAssignmentService
{
    public const MAX_CLASS_TEACHER_ASSIGNMENTS = 2;

    /**
     * @return array{success: bool, error: ?string, assignment: ?TeacherClassSubjectAssignment}
     */
    public function assign(SchoolClass $schoolClass, ?int $sectionId, int $teacherId, int $subjectId, string $academicYear): array
    {
        $sectionError = $this->sectionOwnershipError($schoolClass, $sectionId);
        if ($sectionError) {
            return ['success' => false, 'error' => $sectionError, 'assignment' => null];
        }

        $existingClassTeacherCount = TeacherClassSubjectAssignment::where('teacher_id', $teacherId)
            ->where('is_class_teacher', true)
            ->where('class_id', '!=', $schoolClass->id)
            ->count();

        if ($existingClassTeacherCount >= self::MAX_CLASS_TEACHER_ASSIGNMENTS) {
            return [
                'success' => false,
                'error' => 'This teacher is already class teacher for the maximum allowed number of classes ('.self::MAX_CLASS_TEACHER_ASSIGNMENTS.').',
                'assignment' => null,
            ];
        }

        $assignment = DB::transaction(function () use ($schoolClass, $sectionId, $teacherId, $subjectId, $academicYear) {
            // Never leave two teachers flagged as class teacher of the same
            // class+section -- whoever held it before is safely displaced.
            TeacherClassSubjectAssignment::where('class_id', $schoolClass->id)
                ->where('section_id', $sectionId)
                ->where('academic_year', $academicYear)
                ->where('is_class_teacher', true)
                ->update(['is_class_teacher' => false]);

            return TeacherClassSubjectAssignment::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                    'class_id' => $schoolClass->id,
                    'section_id' => $sectionId,
                    'subject_id' => $subjectId,
                    'academic_year' => $academicYear,
                ],
                ['is_class_teacher' => true]
            );
        });

        return ['success' => true, 'error' => null, 'assignment' => $assignment];
    }

    /**
     * Clears the class-teacher flag on this specific assignment row without
     * deleting the underlying subject assignment -- "remove as class
     * teacher" and "stop teaching this subject to this class" are different
     * admin actions.
     */
    public function remove(TeacherClassSubjectAssignment $assignment): void
    {
        $assignment->update(['is_class_teacher' => false]);
    }

    public function currentClassTeacher(int $classId, ?int $sectionId, string $academicYear): ?TeacherClassSubjectAssignment
    {
        return TeacherClassSubjectAssignment::where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('academic_year', $academicYear)
            ->where('is_class_teacher', true)
            ->with(['teacher', 'subject'])
            ->first();
    }

    /**
     * Same ownership rule Admin\TeacherSubjectAssignmentController,
     * Admin\TimetableController, and Admin\TeacherSubstitutionController all
     * already enforce: a section is only valid for a class if the
     * legacy_class_map -> class_sections bridge actually says so
     * (SchoolClass::validSectionIds()). A null section_id is this form's
     * "whole class" case and is always valid.
     */
    private function sectionOwnershipError(SchoolClass $schoolClass, ?int $sectionId): ?string
    {
        if ($sectionId === null) {
            return null;
        }

        if (! in_array($sectionId, $schoolClass->validSectionIds(), true)) {
            return "That section does not belong to \"{$schoolClass->name}\" -- choose a section that actually belongs to this class.";
        }

        return null;
    }
}
