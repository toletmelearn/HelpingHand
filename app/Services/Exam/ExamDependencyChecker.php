<?php

namespace App\Services\Exam;

use Illuminate\Support\Facades\DB;

/**
 * Exams V1: single source of truth for "what depends on this Exam row?".
 * Every one of these tables has a foreign key on exams.id with
 * ON DELETE CASCADE (results, cbse_results, exam_papers, exam_blueprints,
 * admit_cards, exam_seating_arrangements) -- an unguarded Exam::delete()
 * therefore silently wipes out every student's recorded marks, published
 * question papers, blueprints, admit cards, and seating plan for that
 * exam with zero warning. Both Admin\ExamController::destroy() and
 * Teacher\TeacherExamController::destroy() previously called delete()
 * directly with no check at all; this is the one place that check now
 * lives, so neither caller can drift out of sync with the other.
 *
 * Deliberately pure: no HTTP, redirect, or flash-message behavior lives
 * here -- callers decide how to react to a block.
 */
class ExamDependencyChecker
{
    /**
     * @return array{results: int, cbse_results: int, exam_papers: int, exam_blueprints: int, admit_cards: int, seating_arrangements: int}
     */
    public function check(int $examId): array
    {
        return [
            'results' => DB::table('results')->where('exam_id', $examId)->count(),
            'cbse_results' => DB::table('cbse_results')->where('exam_id', $examId)->count(),
            'exam_papers' => DB::table('exam_papers')->where('exam_id', $examId)->count(),
            'exam_blueprints' => DB::table('exam_blueprints')->where('exam_id', $examId)->count(),
            'admit_cards' => DB::table('admit_cards')->where('exam_id', $examId)->count(),
            'seating_arrangements' => DB::table('exam_seating_arrangements')->where('exam_id', $examId)->count(),
        ];
    }

    public function isBlocked(array $dependencies): bool
    {
        return array_sum($dependencies) > 0;
    }

    /**
     * Narrower than isBlocked(): true only when marks have actually been
     * recorded (results/cbse_results). Used to gate changes to an exam's
     * class/subject/total_marks/passing_marks specifically -- an exam
     * paper, blueprint, admit card, or seating arrangement existing
     * doesn't make those fields unsafe to edit, only a recorded result
     * whose percentage/grade was computed against the old value does.
     */
    public function hasRecordedMarks(array $dependencies): bool
    {
        return ($dependencies['results'] ?? 0) > 0 || ($dependencies['cbse_results'] ?? 0) > 0;
    }

    /**
     * Human-readable "used by ..." list, e.g. "3 results, 2 exam papers".
     */
    public function summarize(array $dependencies): string
    {
        $labels = [
            'results' => 'result',
            'cbse_results' => 'CBSE result',
            'exam_papers' => 'exam paper',
            'exam_blueprints' => 'exam blueprint',
            'admit_cards' => 'admit card',
            'seating_arrangements' => 'seating arrangement',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            $n = $dependencies[$key] ?? 0;
            if ($n > 0) {
                $parts[] = "{$n} {$label}" . ($n === 1 ? '' : 's');
            }
        }

        return implode(', ', $parts);
    }
}
