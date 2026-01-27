<?php

namespace App\Services;

use App\Models\TeacherBiometricRecord;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    /**
     * Generate attendance heatmap data for a given period
     */
    public function generateAttendanceHeatmap($startDate, $endDate, $teacherId = null)
    {
        $query = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->orderBy('punch_time');

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $records = $query->get();

        // Group records by date
        $dates = collect();
        $currentDate = Carbon::parse($startDate);
        $endDateObj = Carbon::parse($endDate);

        while ($currentDate <= $endDateObj) {
            $dates->push($currentDate->copy());
            $currentDate->addDay();
        }

        $heatmapData = [];
        foreach ($dates as $date) {
            $dayRecords = $records->filter(function ($record) use ($date) {
                return $record->punch_time->format('Y-m-d') === $date->format('Y-m-d');
            });

            $heatmapData[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'punch_count' => $dayRecords->count(),
                'present_teachers' => $dayRecords->pluck('teacher_id')->unique()->count(),
                'intensity' => $this->calculateIntensity($dayRecords->count())
            ];
        }

        return $heatmapData;
    }

    /**
     * Calculate intensity level for heatmap (0-5 scale)
     */
    private function calculateIntensity($count)
    {
        if ($count === 0) return 0;
        if ($count <= 5) return 1;
        if ($count <= 10) return 2;
        if ($count <= 20) return 3;
        if ($count <= 30) return 4;
        return 5;
    }

    /**
     * Get late arrival trends by teacher
     */
    public function getLateArrivalTrends($startDate, $endDate, $teacherId = null, $department = null)
    {
        $query = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereHas('teacher', function ($q) {
                $q->whereNotNull('working_hours_config_id');
            });

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($department) {
            $query->whereHas('teacher', function ($q) use ($department) {
                $q->where('department', $department);
            });
        }

        $records = $query->get();

        $trends = [];
        foreach ($records as $record) {
            $scheduledStart = $record->teacher->getScheduledStartTime();
            $actualTime = $record->punch_time;

            if ($actualTime && $scheduledStart && $actualTime->gt($scheduledStart)) {
                $lateMinutes = $actualTime->diffInMinutes($scheduledStart);
                
                $dateKey = $actualTime->format('Y-m-d');
                $teacherKey = $record->teacher_id;

                if (!isset($trends[$dateKey])) {
                    $trends[$dateKey] = [];
                }

                $trends[$dateKey][$teacherKey] = [
                    'teacher_name' => $record->teacher->name,
                    'department' => $record->teacher->department,
                    'punch_time' => $actualTime->format('H:i'),
                    'scheduled_start' => $scheduledStart->format('H:i'),
                    'late_minutes' => $lateMinutes
                ];
            }
        }

        return $trends;
    }

    /**
     * Get early departure analysis
     */
    public function getEarlyDepartureAnalysis($startDate, $endDate, $teacherId = null, $department = null)
    {
        $query = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereHas('teacher', function ($q) {
                $q->whereNotNull('working_hours_config_id');
            })
            ->orderBy('punch_time', 'desc');

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        if ($department) {
            $query->whereHas('teacher', function ($q) use ($department) {
                $q->where('department', $department);
            });
        }

        $records = $query->get();

        // Group by teacher and date to find last punch of the day
        $dailyLastPunches = [];
        foreach ($records as $record) {
            $dateKey = $record->punch_time->format('Y-m-d');
            $teacherKey = $record->teacher_id;

            if (!isset($dailyLastPunches[$dateKey])) {
                $dailyLastPunches[$dateKey] = [];
            }

            if (!isset($dailyLastPunches[$dateKey][$teacherKey]) || 
                $record->punch_time->gt($dailyLastPunches[$dateKey][$teacherKey]->punch_time)) {
                $dailyLastPunches[$dateKey][$teacherKey] = $record;
            }
        }

        $earlyDepartures = [];
        foreach ($dailyLastPunches as $date => $teachers) {
            foreach ($teachers as $teacherId => $lastPunch) {
                $scheduledEnd = $lastPunch->teacher->getScheduledEndTime();
                
                if ($scheduledEnd && $lastPunch->punch_time && $lastPunch->punch_time->lt($scheduledEnd)) {
                    $earlyMinutes = $scheduledEnd->diffInMinutes($lastPunch->punch_time);
                    
                    $earlyDepartures[$date][$teacherId] = [
                        'teacher_name' => $lastPunch->teacher->name,
                        'department' => $lastPunch->teacher->department,
                        'punch_time' => $lastPunch->punch_time->format('H:i'),
                        'scheduled_end' => $scheduledEnd->format('H:i'),
                        'early_minutes' => $earlyMinutes
                    ];
                }
            }
        }

        return $earlyDepartures;
    }

    /**
     * Get department-wise performance comparison
     */
    public function getDepartmentPerformance($startDate, $endDate)
    {
        $records = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereHas('teacher', function ($q) {
                $q->whereNotNull('working_hours_config_id');
            })
            ->get();

        $departments = $records->pluck('teacher.department')->unique()->filter();

        $performance = [];
        foreach ($departments as $dept) {
            $deptRecords = $records->filter(function ($record) use ($dept) {
                return $record->teacher->department === $dept;
            });

            $totalTeachers = $deptRecords->pluck('teacher_id')->unique()->count();
            $totalPunches = $deptRecords->count();

            // Calculate late arrivals
            $lateArrivals = 0;
            foreach ($deptRecords as $record) {
                $scheduledStart = $record->teacher->getScheduledStartTime();
                if ($scheduledStart && $record->punch_time && $record->punch_time->gt($scheduledStart)) {
                    $lateArrivals++;
                }
            }

            // Calculate early departures
            $earlyDepartures = 0;
            $dailyLastPunches = [];
            
            // Group by teacher and date to find last punch of the day
            foreach ($deptRecords as $record) {
                $dateKey = $record->punch_time->format('Y-m-d');
                $teacherKey = $record->teacher_id;

                if (!isset($dailyLastPunches[$dateKey])) {
                    $dailyLastPunches[$dateKey] = [];
                }

                if (!isset($dailyLastPunches[$dateKey][$teacherKey]) || 
                    $record->punch_time->gt($dailyLastPunches[$dateKey][$teacherKey]->punch_time)) {
                    $dailyLastPunches[$dateKey][$teacherKey] = $record;
                }
            }

            foreach ($dailyLastPunches as $date => $teachers) {
                foreach ($teachers as $teacherId => $lastPunch) {
                    $scheduledEnd = $lastPunch->teacher->getScheduledEndTime();
                    if ($scheduledEnd && $lastPunch->punch_time && $lastPunch->punch_time->lt($scheduledEnd)) {
                        $earlyDepartures++;
                    }
                }
            }

            $avgPunchesPerTeacher = $totalTeachers > 0 ? $totalPunches / $totalTeachers : 0;

            $performance[$dept] = [
                'department' => $dept,
                'total_teachers' => $totalTeachers,
                'total_punches' => $totalPunches,
                'avg_punches_per_teacher' => round($avgPunchesPerTeacher, 2),
                'late_arrivals' => $lateArrivals,
                'early_departures' => $earlyDepartures,
                'punctuality_rate' => $totalPunches > 0 ? round((($totalPunches - $lateArrivals) / $totalPunches) * 100, 2) : 0,
                'departure_compliance_rate' => $totalPunches > 0 ? round((($totalPunches - $earlyDepartures) / $totalPunches) * 100, 2) : 0
            ];
        }

        return $performance;
    }

    /**
     * Get working hour efficiency index
     */
    public function getWorkingHourEfficiency($startDate, $endDate, $teacherId = null)
    {
        $query = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereHas('teacher', function ($q) {
                $q->whereNotNull('working_hours_config_id');
            });

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $records = $query->get();

        $efficiencyData = [];
        $dailyGroups = $records->groupBy(function ($item) {
            return $item->punch_time->format('Y-m-d');
        });

        foreach ($dailyGroups as $date => $dayRecords) {
            $teacherGroups = $dayRecords->groupBy('teacher_id');

            foreach ($teacherGroups as $teacherId => $teacherRecords) {
                $teacher = $teacherRecords->first()->teacher;
                
                // Sort records by time to find first and last punch of the day
                $sortedRecords = $teacherRecords->sortBy('punch_time');
                $firstPunch = $sortedRecords->first()->punch_time;
                $lastPunch = $sortedRecords->last()->punch_time;

                $scheduledStart = $teacher->getScheduledStartTime();
                $scheduledEnd = $teacher->getScheduledEndTime();

                if ($firstPunch && $lastPunch && $scheduledStart && $scheduledEnd) {
                    // Calculate actual working hours
                    $actualDuration = $firstPunch->diffInMinutes($lastPunch);
                    $scheduledDuration = $scheduledStart->diffInMinutes($scheduledEnd);

                    $efficiencyPercentage = $scheduledDuration > 0 ? 
                        min(100, ($actualDuration / $scheduledDuration) * 100) : 0;

                    $efficiencyData[] = [
                        'date' => $date,
                        'teacher_id' => $teacherId,
                        'teacher_name' => $teacher->name,
                        'department' => $teacher->department,
                        'first_punch' => $firstPunch->format('H:i'),
                        'last_punch' => $lastPunch->format('H:i'),
                        'scheduled_start' => $scheduledStart->format('H:i'),
                        'scheduled_end' => $scheduledEnd->format('H:i'),
                        'actual_duration' => round($actualDuration / 60, 2), // hours
                        'scheduled_duration' => round($scheduledDuration / 60, 2), // hours
                        'efficiency_percentage' => round($efficiencyPercentage, 2)
                    ];
                }
            }
        }

        return $efficiencyData;
    }

    /**
     * Get monthly discipline score
     */
    public function getMonthlyDisciplineScore($year, $month, $teacherId = null)
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $query = TeacherBiometricRecord::with('teacher')
            ->whereBetween('punch_time', [$startDate, $endDate])
            ->whereHas('teacher', function ($q) {
                $q->whereNotNull('working_hours_config_id');
            });

        if ($teacherId) {
            $query->where('teacher_id', $teacherId);
        }

        $records = $query->get();

        $monthlyStats = [];
        $teacherGroups = $records->groupBy('teacher_id');

        foreach ($teacherGroups as $teacherId => $teacherRecords) {
            $teacher = $teacherRecords->first()->teacher;

            // Count late arrivals
            $lateCount = 0;
            $earlyDepartureCount = 0;
            $absentDays = 0;

            // Get working days in the month
            $workingDays = 0;
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                // Assuming Monday to Friday are working days (can be customized)
                if ($currentDate->isWeekday()) {
                    $workingDays++;
                }
                $currentDate->addDay();
            }

            // Count daily punches
            $dailyPunches = $teacherRecords->groupBy(function ($item) {
                return $item->punch_time->format('Y-m-d');
            });

            foreach ($dailyPunches as $date => $dayRecords) {
                $firstPunch = $dayRecords->sortBy('punch_time')->first();
                $scheduledStart = $teacher->getScheduledStartTime();
                
                if ($firstPunch->punch_time && $scheduledStart && $firstPunch->punch_time->gt($scheduledStart)) {
                    $lateCount++;
                }

                // Check for early departure
                $lastPunch = $dayRecords->sortByDesc('punch_time')->first();
                $scheduledEnd = $teacher->getScheduledEndTime();
                
                if ($lastPunch->punch_time && $scheduledEnd && $lastPunch->punch_time->lt($scheduledEnd)) {
                    $earlyDepartureCount++;
                }
            }

            // Calculate absent days (working days without any punch)
            $presentDays = $dailyPunches->count();
            $absentDays = $workingDays - $presentDays;

            // Calculate discipline score (0-100)
            $maxPossibleDeductions = $workingDays * 2; // Max 2 deductions per day (late + early)
            $actualDeductions = $lateCount + $earlyDepartureCount + ($absentDays * 2); // Absences count double
            
            $disciplineScore = max(0, 100 - (($actualDeductions / $maxPossibleDeductions) * 100));

            $monthlyStats[$teacherId] = [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher->name,
                'department' => $teacher->department,
                'month' => $month,
                'year' => $year,
                'working_days' => $workingDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'late_arrivals' => $lateCount,
                'early_departures' => $earlyDepartureCount,
                'discipline_score' => round($disciplineScore, 2)
            ];
        }

        return $monthlyStats;
    }
}