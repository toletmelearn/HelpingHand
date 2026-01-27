<?php

namespace App\Services;

use App\Models\TeacherBiometricRecord;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportGenerationService
{
    /**
     * Generate daily summary report
     */
    public function generateDailySummary($date)
    {
        return TeacherBiometricRecord::getDailySummary($date);
    }
    
    /**
     * Generate monthly report for a teacher
     */
    public function generateMonthlyReport($teacherId, $year, $month)
    {
        return TeacherBiometricRecord::getMonthlyReport($teacherId, $year, $month);
    }
    
    /**
     * Generate late arrival report
     */
    public function generateLateArrivalReport($startDate, $endDate)
    {
        return TeacherBiometricRecord::with('teacher')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('arrival_status', 'late')
            ->orderBy('late_minutes', 'desc')
            ->get();
    }
    
    /**
     * Generate early departure report
     */
    public function generateEarlyDepartureReport($startDate, $endDate)
    {
        return TeacherBiometricRecord::with('teacher')
            ->whereBetween('date', [$startDate, $endDate])
            ->where('departure_status', 'early_exit')
            ->orderBy('early_departure_minutes', 'desc')
            ->get();
    }
    
    /**
     * Generate attendance summary report
     */
    public function generateAttendanceSummary($startDate, $endDate)
    {
        return DB::table('teacher_biometric_records')
            ->join('teachers', 'teacher_biometric_records.teacher_id', '=', 'teachers.id')
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('teachers.name as teacher_name,
                         teachers.employee_id,
                         COUNT(*) as total_days,
                         SUM(CASE WHEN arrival_status = "late" THEN 1 ELSE 0 END) as late_days,
                         SUM(CASE WHEN departure_status = "early_exit" THEN 1 ELSE 0 END) as early_exit_days,
                         SUM(CASE WHEN departure_status = "half_day" THEN 1 ELSE 0 END) as half_days,
                         AVG(calculated_duration) as avg_working_hours')
            ->groupBy('teachers.id', 'teachers.name', 'teachers.employee_id')
            ->orderBy('teachers.name')
            ->get();
    }
    
    /**
     * Generate working hours comparison report
     */
    public function generateWorkingHoursComparison($startDate, $endDate)
    {
        $records = TeacherBiometricRecord::with('teacher')
            ->whereBetween('date', [$startDate, $endDate])
            ->get();
            
        $teacherStats = [];
        
        foreach ($records as $record) {
            $teacherId = $record->teacher_id;
            
            if (!isset($teacherStats[$teacherId])) {
                $teacherStats[$teacherId] = [
                    'teacher' => $record->teacher->name,
                    'total_records' => 0,
                    'total_hours' => 0,
                    'avg_hours' => 0,
                    'min_hours' => PHP_FLOAT_MAX,
                    'max_hours' => 0,
                ];
            }
            
            $teacherStats[$teacherId]['total_records']++;
            $teacherStats[$teacherId]['total_hours'] += $record->calculated_duration;
            $teacherStats[$teacherId]['min_hours'] = min($teacherStats[$teacherId]['min_hours'], $record->calculated_duration);
            $teacherStats[$teacherId]['max_hours'] = max($teacherStats[$teacherId]['max_hours'], $record->calculated_duration);
        }
        
        // Calculate averages
        foreach ($teacherStats as &$stats) {
            if ($stats['total_records'] > 0) {
                $stats['avg_hours'] = $stats['total_hours'] / $stats['total_records'];
            }
            if ($stats['min_hours'] === PHP_FLOAT_MAX) {
                $stats['min_hours'] = 0;
            }
        }
        
        return collect($teacherStats)->sortByDesc('avg_hours');
    }
    
    /**
     * Export data to array format for Excel/CSV
     */
    public function exportToArray($data, $reportType)
    {
        $headers = [];
        $rows = [];
        
        switch ($reportType) {
            case 'daily_summary':
                $headers = ['Date', 'Total Records', 'Late Arrivals', 'Early Departures', 'Half Days', 'Avg Working Hours'];
                $rows[] = [
                    $data->date ?? 'N/A',
                    $data->total ?? 0,
                    $data->late_arrivals ?? 0,
                    $data->early_departures ?? 0,
                    $data->half_days ?? 0,
                    number_format($data->avg_working_hours ?? 0, 2)
                ];
                break;
                
            case 'monthly_report':
                $headers = ['Teacher', 'Employee ID', 'Total Days', 'Late Days', 'Early Exit Days', 'Half Days', 'Avg Working Hours'];
                foreach ($data as $item) {
                    $rows[] = [
                        $item->teacher_name ?? 'N/A',
                        $item->employee_id ?? 'N/A',
                        $item->total_days ?? 0,
                        $item->late_days ?? 0,
                        $item->early_exit_days ?? 0,
                        $item->half_days ?? 0,
                        number_format($item->avg_working_hours ?? 0, 2)
                    ];
                }
                break;
                
            case 'late_arrival':
                $headers = ['Date', 'Teacher Name', 'Employee ID', 'First In Time', 'Late Minutes', 'Grace Used'];
                foreach ($data as $record) {
                    $rows[] = [
                        $record->date->format('Y-m-d'),
                        $record->teacher->name,
                        $record->teacher->employee_id ?? 'N/A',
                        $record->first_in_time ? Carbon::createFromTimeString($record->first_in_time)->format('H:i') : 'N/A',
                        $record->late_minutes,
                        $record->grace_minutes_used
                    ];
                }
                break;
        }
        
        return [
            'headers' => $headers,
            'rows' => $rows
        ];
    }
    
    /**
     * Get report metadata
     */
    public function getReportMetadata($reportType, $startDate, $endDate)
    {
        return [
            'report_type' => $reportType,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'period_start' => $startDate,
            'period_end' => $endDate,
            'total_records' => TeacherBiometricRecord::whereBetween('date', [$startDate, $endDate])->count(),
        ];
    }
}
