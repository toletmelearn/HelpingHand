<?php

namespace App\Services\Academic;

use App\Models\Result;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\PromotionRule;
use Illuminate\Support\Facades\DB;

class AcademicRankerService
{
    /**
     * Compute and save student rankings (class & section wise) based on overall percentages.
     */
    public function calculateRanks($className, $academicYear)
    {
        // 1. Get all results for this class and academic year
        $results = Result::where('academic_year', $academicYear)
            ->whereHas('exam', function ($query) use ($className) {
                $query->where('class_name', $className);
            })
            ->get();

        if ($results->isEmpty()) {
            return [];
        }

        // Group results by student
        $studentScores = [];
        foreach ($results as $result) {
            $studentId = $result->student_id;
            if (!isset($studentScores[$studentId])) {
                $studentScores[$studentId] = [
                    'student_id' => $studentId,
                    'total_obtained' => 0.0,
                    'total_possible' => 0.0,
                ];
            }
            $studentScores[$studentId]['total_obtained'] += $result->marks_obtained;
            $studentScores[$studentId]['total_possible'] += $result->total_marks;
        }

        // Calculate overall percentage for each student
        foreach ($studentScores as $studentId => $data) {
            $possible = $data['total_possible'];
            $studentScores[$studentId]['percentage'] = $possible > 0 ? ($data['total_obtained'] / $possible) * 100 : 0;
        }

        // Sort students descending by overall percentage
        uasort($studentScores, function ($a, $b) {
            return $b['percentage'] <=> $a['percentage'];
        });

        // Assign Class Ranks
        $rank = 1;
        $classRankings = [];
        foreach ($studentScores as $studentId => $data) {
            $classRankings[$studentId] = $rank++;
        }

        // Update database results table
        DB::transaction(function () use ($className, $academicYear, $classRankings) {
            foreach ($classRankings as $studentId => $rank) {
                Result::where('student_id', $studentId)
                    ->where('academic_year', $academicYear)
                    ->whereHas('exam', function ($q) use ($className) {
                        $q->where('class_name', $className);
                    })
                    ->update([
                        'class_rank' => $rank,
                        'section_rank' => $rank, // Standard fallback
                    ]);
            }
        });

        return $classRankings;
    }

    /**
     * Evaluate if a student passes promotion rules to advance to the next academic grade.
     */
    public function evaluatePromotion($studentId, $academicYear)
    {
        $student = Student::findOrFail($studentId);

        // Fetch student's overall results
        $results = Result::where('student_id', $studentId)
            ->where('academic_year', $academicYear)
            ->get();

        if ($results->isEmpty()) {
            return [
                'promoted' => false,
                'reason' => 'No assessment results found for the student in this academic year.',
                'stats' => ['percentage' => 0, 'failed_count' => 0, 'attendance' => 0]
            ];
        }

        // 1. Calculate overall score percentage
        $totalObtained = $results->sum('marks_obtained');
        $totalPossible = $results->sum('total_marks');
        $overallPercentage = $totalPossible > 0 ? ($totalObtained / $totalPossible) * 100 : 0;

        // 2. Count failed subjects
        $failedCount = $results->where('result_status', 'fail')->count();

        // 3. Resolve class name (from results or class_id relationship)
        $className = $results->first()->exam->class_name ?? $student->class ?? 'Grade 10';

        // 4. Calculate attendance percentage
        $attendanceQuery = Attendance::where('student_id', $studentId);
        $totalDays = $attendanceQuery->count();
        $presentDays = $attendanceQuery->whereIn('status', ['present', 'late'])->count();
        $attendancePercentage = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 100.0; // Default to 100% if no attendance records exist

        // Get promotion rule
        $rule = PromotionRule::where('class_name', $className)
            ->where('academic_year', $academicYear)
            ->first();

        if (!$rule) {
            return [
                'promoted' => true,
                'reason' => 'No promotion policies defined for this class. Promoted by default.',
                'stats' => [
                    'percentage' => round($overallPercentage, 2),
                    'failed_count' => $failedCount,
                    'attendance' => round($attendancePercentage, 2)
                ]
            ];
        }

        // Validate policies
        $promoted = true;
        $reasons = [];

        if ($overallPercentage < $rule->min_overall_percentage) {
            $promoted = false;
            $reasons[] = "Overall percentage (" . round($overallPercentage, 2) . "%) is below the minimum required " . $rule->min_overall_percentage . "%.";
        }

        if ($failedCount > $rule->max_failed_subjects) {
            $promoted = false;
            $reasons[] = "Failed subjects count ({$failedCount}) exceeds the maximum allowed {$rule->max_failed_subjects}.";
        }

        if ($attendancePercentage < $rule->min_attendance_percentage) {
            $promoted = false;
            $reasons[] = "Attendance percentage (" . round($attendancePercentage, 2) . "%) is below the minimum required " . $rule->min_attendance_percentage . "%.";
        }

        return [
            'promoted' => $promoted,
            'reason' => $promoted ? 'Student satisfied all criteria for promotion.' : implode(' ', $reasons),
            'stats' => [
                'percentage' => round($overallPercentage, 2),
                'failed_count' => $failedCount,
                'attendance' => round($attendancePercentage, 2)
            ]
        ];
    }
}
