<?php

namespace App\Services\Academic;

use App\Models\Result;
use App\Models\Exam;
use Illuminate\Support\Facades\DB;

class AssessmentModeratorService
{
    /**
     * Apply flat percentage moderation to all student marks for a given exam and subject.
     */
    public function applyModeration($examId, $subject, $adjustmentPercentage, $moderatedBy = null, $reason = null)
    {
        $exam = Exam::findOrFail($examId);
        $results = Result::where('exam_id', $examId)
            ->where('subject', $subject)
            ->get();

        return DB::transaction(function () use ($results, $adjustmentPercentage, $moderatedBy, $reason) {
            $updatedCount = 0;

            foreach ($results as $result) {
                // Keep original score reference
                if (is_null($result->original_marks_obtained)) {
                    $result->original_marks_obtained = $result->marks_obtained;
                }

                $newMarks = $result->original_marks_obtained * (1 + $adjustmentPercentage / 100);
                
                // Cap at maximum possible total marks
                $newMarks = min($newMarks, $result->total_marks);
                $newMarks = max($newMarks, 0);

                $result->marks_obtained = $newMarks;
                $result->moderated_by = $moderatedBy;
                $result->moderation_reason = $reason;
                $result->save();

                $result->updateResultStatus();
                $updatedCount++;
            }

            return $updatedCount;
        });
    }

    /**
     * Automatically apply grace marks to close-to-passing failing grades for a student.
     */
    public function applyGraceMarks($studentId, $academicYear, $maxGraceMarksTotal = 5)
    {
        // Get all failing results for student in specific academic year
        $results = Result::where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->where('result_status', 'fail')
            ->get();

        if ($results->isEmpty()) {
            return 0;
        }

        // Calculate marks needed to pass for each subject
        $failingSubjects = [];
        foreach ($results as $result) {
            $passingScore = $result->exam->passing_marks ?? ($result->total_marks * 0.33);
            $needed = $passingScore - $result->marks_obtained;

            if ($needed > 0) {
                $failingSubjects[] = [
                    'result' => $result,
                    'needed' => $needed,
                    'passing_score' => $passingScore,
                ];
            }
        }

        // Sort by minimum marks needed to pass (easy passes first)
        usort($failingSubjects, function ($a, $b) {
            return $a['needed'] <=> $b['needed'];
        });

        return DB::transaction(function () use ($failingSubjects, $maxGraceMarksTotal) {
            $graceMarksUsed = 0;
            $appliedCount = 0;

            foreach ($failingSubjects as $item) {
                $result = $item['result'];
                $needed = $item['needed'];

                if ($graceMarksUsed + $needed <= $maxGraceMarksTotal) {
                    if (is_null($result->original_marks_obtained)) {
                        $result->original_marks_obtained = $result->marks_obtained;
                    }

                    $result->marks_obtained += $needed;
                    $result->grace_marks_applied = $needed;
                    $result->moderation_reason = 'Automated Grace Marks';
                    $result->save();

                    $result->updateResultStatus();

                    $graceMarksUsed += $needed;
                    $appliedCount++;
                }
            }

            return $appliedCount;
        });
    }
}
