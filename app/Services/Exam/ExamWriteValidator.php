<?php

namespace App\Services\Exam;

use App\Models\Exam;

/**
 * Sync-audit loophole L-09: DatesheetPublishService::publish() called
 * Exam::create() directly, skipping the duplicate-exam check and marks
 * validation Admin\ExamController::store()/update() enforce on every
 * other creation path -- a second, divergent door into the `exams` table.
 * This is the single place both now go through, so the two paths can't
 * drift out of sync with each other again.
 */
class ExamWriteValidator
{
    /** True when passing_marks does not exceed total_marks (either being null skips the check). */
    public function marksValid(?float $totalMarks, ?float $passingMarks): bool
    {
        if ($totalMarks === null || $passingMarks === null) {
            return true;
        }

        return $passingMarks <= $totalMarks;
    }

    /** True when this class already has an exam for the same subject/academic year/term. */
    public function duplicateExists(int $classId, string $subject, string $academicYear, string $term, ?int $excludeExamId = null): bool
    {
        return Exam::where('class_id', $classId)
            ->where('subject', $subject)
            ->where('academic_year', $academicYear)
            ->where('term', $term)
            ->when($excludeExamId, fn ($q) => $q->where('id', '!=', $excludeExamId))
            ->exists();
    }
}
