<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherBiometricRecord;
use App\Models\PerformanceScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PerformanceScoringService
{
    /**
     * Calculate punctuality score for a teacher (0-100)
     * Based on late arrivals percentage
     */
    public function calculatePunctualityScore($teacherId, $startDate, $endDate)
    {
        $records = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->get();

        if ($records->isEmpty()) {
            return 0; // No records, score is 0
        }

        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            return 0;
        }

        $totalRecords = $records->count();
        $lateCount = 0;

        foreach ($records as $record) {
            $scheduledStart = $teacher->getScheduledStartTime();
            if ($scheduledStart && $record->punch_time && $record->punch_time->gt($scheduledStart)) {
                $lateCount++;
            }
        }

        $latePercentage = ($lateCount / $totalRecords) * 100;
        
        // Higher punctuality score for lower late percentage
        $punctualityScore = max(0, 100 - $latePercentage);
        
        return round($punctualityScore, 2);
    }

    /**
     * Calculate discipline rating based on multiple factors
     * Factors: punctuality, early departures, absences, consistency
     */
    public function calculateDisciplineRating($teacherId, $startDate, $endDate)
    {
        $records = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            return 0;
        }

        // Calculate working days in the period
        $periodStart = Carbon::parse($startDate);
        $periodEnd = Carbon::parse($endDate);
        $totalWorkingDays = 0;
        $currentDate = clone $periodStart;
        
        while ($currentDate <= $periodEnd) {
            if ($currentDate->isWeekday()) { // Assuming weekdays are working days
                $totalWorkingDays++;
            }
            $currentDate->addDay();
        }

        // Group records by date to analyze daily patterns
        $dailyRecords = $records->groupBy(function ($item) {
            return $item->punch_time->format('Y-m-d');
        });

        $presentDays = $dailyRecords->count();
        $absentDays = $totalWorkingDays - $presentDays;

        // Calculate late arrivals
        $lateCount = 0;
        foreach ($dailyRecords as $date => $dayRecords) {
            $firstPunch = $dayRecords->sortBy('punch_time')->first();
            $scheduledStart = $teacher->getScheduledStartTime();
            
            if ($firstPunch->punch_time && $scheduledStart && $firstPunch->punch_time->gt($scheduledStart)) {
                $lateCount++;
            }
        }

        // Calculate early departures
        $earlyDepartureCount = 0;
        foreach ($dailyRecords as $date => $dayRecords) {
            $lastPunch = $dayRecords->sortByDesc('punch_time')->first();
            $scheduledEnd = $teacher->getScheduledEndTime();
            
            if ($lastPunch->punch_time && $scheduledEnd && $lastPunch->punch_time->lt($scheduledEnd)) {
                $earlyDepartureCount++;
            }
        }

        // Calculate various penalty factors
        $absencePenalty = ($absentDays / $totalWorkingDays) * 40; // Absences worth 40% of total score
        $latePenalty = ($lateCount / max(1, $presentDays)) * 25; // Lates worth 25% of total score
        $earlyPenalty = ($earlyDepartureCount / max(1, $presentDays)) * 25; // Early departures worth 25% of total score
        $consistencyBonus = $this->calculateConsistencyBonus($dailyRecords, $teacher);

        // Calculate final discipline rating
        $disciplineRating = max(0, 100 - $absencePenalty - $latePenalty - $earlyPenalty + $consistencyBonus);
        
        return round($disciplineRating, 2);
    }

    /**
     * Calculate consistency index based on regularity of attendance patterns
     */
    public function calculateConsistencyIndex($teacherId, $startDate, $endDate)
    {
        $records = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->get();

        if ($records->isEmpty()) {
            return 0;
        }

        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            return 0;
        }

        $dailyRecords = $records->groupBy(function ($item) {
            return $item->punch_time->format('Y-m-d');
        });

        if ($dailyRecords->count() < 2) {
            return 50; // Not enough data to determine consistency
        }

        $deviations = [];
        foreach ($dailyRecords as $date => $dayRecords) {
            $firstPunch = $dayRecords->sortBy('punch_time')->first();
            $lastPunch = $dayRecords->sortByDesc('punch_time')->last();
            
            $scheduledStart = $teacher->getScheduledStartTime();
            $scheduledEnd = $teacher->getScheduledEndTime();
            
            if ($firstPunch->punch_time && $scheduledStart) {
                $startDeviation = $firstPunch->punch_time->diffInMinutes($scheduledStart);
                $deviations[] = abs($startDeviation);
            }
            
            if ($lastPunch->punch_time && $scheduledEnd) {
                $endDeviation = $lastPunch->punch_time->diffInMinutes($scheduledEnd);
                $deviations[] = abs($endDeviation);
            }
        }

        if (empty($deviations)) {
            return 50; // No deviations calculated
        }

        // Calculate average deviation
        $avgDeviation = array_sum($deviations) / count($deviations);
        
        // Convert to consistency score (lower deviation = higher consistency)
        $consistencyIndex = max(0, 100 - ($avgDeviation / 60)); // Convert minutes to hours for scaling
        
        return round(min(100, $consistencyIndex), 2);
    }

    /**
     * Calculate monthly performance grade (A, B, C, D, F)
     */
    public function calculateMonthlyPerformanceGrade($teacherId, $year, $month)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $overallScore = $this->calculateOverallPerformanceScore($teacherId, $startDate, $endDate);

        // Assign grade based on score
        if ($overallScore >= 90) {
            return 'A';
        } elseif ($overallScore >= 80) {
            return 'B';
        } elseif ($overallScore >= 70) {
            return 'C';
        } elseif ($overallScore >= 60) {
            return 'D';
        } else {
            return 'F';
        }
    }

    /**
     * Calculate overall performance score combining all metrics
     */
    public function calculateOverallPerformanceScore($teacherId, $startDate, $endDate)
    {
        $punctualityScore = $this->calculatePunctualityScore($teacherId, $startDate, $endDate);
        $disciplineRating = $this->calculateDisciplineRating($teacherId, $startDate, $endDate);
        $consistencyIndex = $this->calculateConsistencyIndex($teacherId, $startDate, $endDate);

        // Weighted average: Punctuality 40%, Discipline 40%, Consistency 20%
        $overallScore = ($punctualityScore * 0.4) + ($disciplineRating * 0.4) + ($consistencyIndex * 0.2);

        return round($overallScore, 2);
    }

    /**
     * Calculate consistency bonus based on regular attendance patterns
     */
    private function calculateConsistencyBonus($dailyRecords, $teacher)
    {
        if ($dailyRecords->count() < 5) {
            return 0; // Not enough data for consistency evaluation
        }

        $startTimes = [];
        $endTimes = [];

        foreach ($dailyRecords as $date => $dayRecords) {
            $firstPunch = $dayRecords->sortBy('punch_time')->first();
            $lastPunch = $dayRecords->sortByDesc('punch_time')->last();
            
            if ($firstPunch->punch_time) {
                $startTimes[] = $firstPunch->punch_time->format('H:i');
            }
            
            if ($lastPunch->punch_time) {
                $endTimes[] = $lastPunch->punch_time->format('H:i');
            }
        }

        // Calculate variance in start times
        $startTimeVariance = $this->calculateTimeVariance($startTimes);
        $endTimeVariance = $this->calculateTimeVariance($endTimes);

        // Lower variance means higher consistency bonus
        $consistencyBonus = max(0, 10 - ($startTimeVariance + $endTimeVariance));
        
        return min(10, $consistencyBonus); // Cap at 10 points
    }

    /**
     * Calculate variance of time values
     */
    private function calculateTimeVariance($timeArray)
    {
        if (empty($timeArray)) {
            return 0;
        }

        // Convert time strings to minutes since midnight for calculation
        $minutesArray = array_map(function ($time) {
            $parts = explode(':', $time);
            return ($parts[0] * 60) + $parts[1];
        }, $timeArray);

        if (count($minutesArray) < 2) {
            return 0;
        }

        $mean = array_sum($minutesArray) / count($minutesArray);
        $variance = 0;

        foreach ($minutesArray as $minute) {
            $variance += pow($minute - $mean, 2);
        }

        $variance = $variance / count($minutesArray);
        
        // Normalize variance to 0-10 scale
        return min(10, sqrt($variance) / 10); // Square root to reduce impact of outliers
    }

    /**
     * Generate comparative analytics against other teachers in department
     */
    public function generateComparativeAnalytics($teacherId, $startDate, $endDate)
    {
        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            return null;
        }

        // Get all teachers in the same department
        $departmentTeachers = Teacher::where('department', $teacher->department)
            ->whereHas('biometricRecords', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('punch_time', [$startDate, $endDate]);
            })
            ->get();

        $comparisons = [];
        foreach ($departmentTeachers as $deptTeacher) {
            $score = $this->calculateOverallPerformanceScore($deptTeacher->id, $startDate, $endDate);
            $comparisons[] = [
                'teacher_id' => $deptTeacher->id,
                'teacher_name' => $deptTeacher->name,
                'overall_score' => $score,
                'punctuality_score' => $this->calculatePunctualityScore($deptTeacher->id, $startDate, $endDate),
                'discipline_rating' => $this->calculateDisciplineRating($deptTeacher->id, $startDate, $endDate)
            ];
        }

        // Sort by overall score descending
        usort($comparisons, function ($a, $b) {
            return $b['overall_score'] <=> $a['overall_score'];
        });

        $teacherRank = 0;
        foreach ($comparisons as $index => $comparison) {
            if ($comparison['teacher_id'] == $teacherId) {
                $teacherRank = $index + 1;
                break;
            }
        }

        return [
            'teacher_rank' => $teacherRank,
            'department_size' => count($comparisons),
            'teacher_percentile' => count($comparisons) > 0 ? round(($teacherRank / count($comparisons)) * 100, 2) : 0,
            'top_performer' => $comparisons[0]['teacher_name'] ?? null,
            'average_department_score' => count($comparisons) > 0 ? 
                round(array_sum(array_column($comparisons, 'overall_score')) / count($comparisons), 2) : 0,
            'comparisons' => $comparisons
        ];
    }

    /**
     * Save performance score to database
     */
    public function savePerformanceScore($teacherId, $periodStart, $periodEnd, $type = 'monthly')
    {
        $overallScore = $this->calculateOverallPerformanceScore($teacherId, $periodStart, $periodEnd);
        $punctualityScore = $this->calculatePunctualityScore($teacherId, $periodStart, $periodEnd);
        $disciplineRating = $this->calculateDisciplineRating($teacherId, $periodStart, $periodEnd);
        $consistencyIndex = $this->calculateConsistencyIndex($teacherId, $periodStart, $periodEnd);
        $grade = $this->calculateMonthlyPerformanceGrade($teacherId, 
            Carbon::parse($periodStart)->year, 
            Carbon::parse($periodStart)->month);

        $performanceScore = PerformanceScore::updateOrCreate(
            [
                'teacher_id' => $teacherId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'type' => $type
            ],
            [
                'overall_score' => $overallScore,
                'punctuality_score' => $punctualityScore,
                'discipline_rating' => $disciplineRating,
                'consistency_index' => $consistencyIndex,
                'grade' => $grade,
                'calculated_at' => Carbon::now()
            ]
        );

        return $performanceScore;
    }

    /**
     * Get historical performance trends
     */
    public function getHistoricalTrends($teacherId, $months = 6)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months);

        $trends = [];
        $currentMonth = $startDate->copy()->startOfMonth();

        while ($currentMonth <= $endDate) {
            $periodStart = $currentMonth->copy()->startOfMonth();
            $periodEnd = $currentMonth->copy()->endOfMonth();

            $score = $this->calculateOverallPerformanceScore($teacherId, $periodStart, $periodEnd);
            $punctuality = $this->calculatePunctualityScore($teacherId, $periodStart, $periodEnd);
            $discipline = $this->calculateDisciplineRating($teacherId, $periodStart, $periodEnd);

            $trends[] = [
                'month' => $periodStart->format('M Y'),
                'overall_score' => $score,
                'punctuality_score' => $punctuality,
                'discipline_rating' => $discipline,
                'date' => $periodStart->format('Y-m')
            ];

            $currentMonth->addMonth();
        }

        return $trends;
    }
}