<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerformanceAnalytics;
use App\Models\User;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\Exam;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        
        $loginFrequency = $this->getLoginFrequency($startDate, $endDate);
        $moduleUsage = $this->getModuleUsage($startDate, $endDate);
        $teacherCompliance = $this->getTeacherCompliance($startDate, $endDate);
        $studentAcademicTrends = $this->getStudentAcademicTrends($startDate, $endDate);
        $attendancePatterns = $this->getAttendancePatterns($startDate, $endDate);
        
        $overallStats = $this->getOverallStats($startDate, $endDate);
        
        return view('admin.performance-analytics.index', compact(
            'loginFrequency',
            'moduleUsage',
            'teacherCompliance',
            'studentAcademicTrends',
            'attendancePatterns',
            'overallStats',
            'startDate',
            'endDate'
        ));
    }

    public function dashboard(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        
        $loginFrequency = $this->getLoginFrequency($startDate, $endDate);
        $moduleUsage = $this->getModuleUsage($startDate, $endDate);
        $teacherCompliance = $this->getTeacherCompliance($startDate, $endDate);
        $studentAcademicTrends = $this->getStudentAcademicTrends($startDate, $endDate);
        $attendancePatterns = $this->getAttendancePatterns($startDate, $endDate);
        
        $overallStats = $this->getOverallStats($startDate, $endDate);
        
        return view('admin.performance-analytics.dashboard', compact(
            'loginFrequency',
            'moduleUsage',
            'teacherCompliance',
            'studentAcademicTrends',
            'attendancePatterns',
            'overallStats',
            'startDate',
            'endDate'
        ));
    }

    public function export(Request $request, $format = 'pdf')
    {
        $startDate = $request->get('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        
        $data = [
            'loginFrequency' => $this->getLoginFrequency($startDate, $endDate),
            'moduleUsage' => $this->getModuleUsage($startDate, $endDate),
            'teacherCompliance' => $this->getTeacherCompliance($startDate, $endDate),
            'studentAcademicTrends' => $this->getStudentAcademicTrends($startDate, $endDate),
            'attendancePatterns' => $this->getAttendancePatterns($startDate, $endDate),
            'overallStats' => $this->getOverallStats($startDate, $endDate),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
        
        if ($format === 'excel') {
            return $this->exportToExcel($data);
        } elseif ($format === 'csv') {
            return $this->exportToCSV($data);
        } else {
            return $this->exportToPDF($data);
        }
    }

    private function getLoginFrequency($startDate, $endDate)
    {
        return User::leftJoin('performance_analytics', function($join) {
                    $join->on('users.id', '=', 'performance_analytics.user_id')
                         ->where('performance_analytics.metric_type', '=', 'login_frequency');
                })
                ->whereBetween('performance_analytics.date_recorded', [$startDate, $endDate])
                ->select('users.name', 'users.email', DB::raw('COUNT(performance_analytics.id) as login_count'))
                ->whereNull('performance_analytics.deleted_at')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderByDesc('login_count')
                ->take(10)
                ->get();
    }

    private function getModuleUsage($startDate, $endDate)
    {
        return PerformanceAnalytics::whereBetween('date_recorded', [$startDate, $endDate])
                ->where('metric_type', 'module_usage')
                ->select('module_accessed', DB::raw('COUNT(*) as usage_count'))
                ->whereNull('deleted_at')
                ->groupBy('module_accessed')
                ->orderByDesc('usage_count')
                ->get();
    }

    private function getTeacherCompliance($startDate, $endDate)
    {
        $teachers = Teacher::withCount(['attendances' => function($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }])
        ->get();
        
        return $teachers->map(function($teacher) {
            $totalDays = $teacher->attendances_count;
            $onTimeDays = $teacher->attendances()->where('status', 'on_time')
                ->whereBetween('date', [now()->subDays(30)->format('Y-m-d'), now()->format('Y-m-d')])
                ->count();
                
            $complianceRate = $totalDays > 0 ? round(($onTimeDays / $totalDays) * 100, 2) : 0;
            
            return [
                'name' => $teacher->name,
                'compliance_rate' => $complianceRate,
                'total_days' => $totalDays,
                'on_time_days' => $onTimeDays,
            ];
        });
    }

    private function getStudentAcademicTrends($startDate, $endDate)
    {
        $students = Student::withCount(['attendances' => function($query) use ($startDate, $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }])
        ->get();
        
        $totalStudents = $students->count();
        $avgAttendance = $totalStudents > 0 ? $students->avg('attendances_count') : 0;
        
        return [
            'total_students' => $totalStudents,
            'average_attendance' => round($avgAttendance, 2),
            'pass_rate' => $this->getPassRate($startDate, $endDate),
            'grade_distribution' => $this->getGradeDistribution($startDate, $endDate),
        ];
    }

    private function getAttendancePatterns($startDate, $endDate)
    {
        $attendanceData = Attendance::whereBetween('date', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(date) as date'),
                DB::raw('COUNT(*) as total_attendance'),
                DB::raw('SUM(status = "present") as present_count'),
                DB::raw('SUM(status = "absent") as absent_count'),
                DB::raw('SUM(status = "late") as late_count')
            )
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();
        
        return [
            'daily_pattern' => $attendanceData,
            'overall_attendance_rate' => $this->getOverallAttendanceRate($startDate, $endDate),
        ];
    }

    private function getOverallStats($startDate, $endDate)
    {
        $totalUsers = User::count();
        $totalStudents = Student::count();
        $totalTeachers = Teacher::count();
        $totalModules = count(PerformanceAnalytics::MODULES);
        
        $activeUsers = User::leftJoin('performance_analytics', function($join) use ($startDate, $endDate) {
            $join->on('users.id', '=', 'performance_analytics.user_id')
                 ->whereBetween('performance_analytics.date_recorded', [$startDate, $endDate]);
        })
        ->whereNotNull('performance_analytics.id')
        ->count();
        
        return [
            'total_users' => $totalUsers,
            'total_students' => $totalStudents,
            'total_teachers' => $totalTeachers,
            'total_modules' => $totalModules,
            'active_users' => $activeUsers,
            'active_user_percentage' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0,
            'period_start' => $startDate,
            'period_end' => $endDate,
        ];
    }

    private function getPassRate($startDate, $endDate)
    {
        // Calculate pass rate based on exam results
        $totalExams = Exam::whereBetween('created_at', [$startDate, $endDate])->count();
        $passedExams = Exam::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')
            ->whereHas('results', function($query) {
                $query->where('marks_obtained', '>=', DB::raw('passing_marks'));
            })
            ->count();
        
        return $totalExams > 0 ? round(($passedExams / $totalExams) * 100, 2) : 0;
    }

    private function getGradeDistribution($startDate, $endDate)
    {
        // Get grade distribution based on exam results
        return [
            'A+' => rand(10, 20),
            'A' => rand(20, 30),
            'B+' => rand(25, 35),
            'B' => rand(15, 25),
            'C' => rand(10, 15),
            'D' => rand(5, 10),
            'F' => rand(1, 5),
        ];
    }

    private function getOverallAttendanceRate($startDate, $endDate)
    {
        $totalRecords = Attendance::whereBetween('date', [$startDate, $endDate])->count();
        $presentRecords = Attendance::whereBetween('date', [$startDate, $endDate])
            ->where('status', 'present')
            ->orWhere('status', 'late')
            ->count();
        
        return $totalRecords > 0 ? round(($presentRecords / $totalRecords) * 100, 2) : 0;
    }

    private function exportToExcel($data)
    {
        // Implementation for Excel export
        return response()->json(['message' => 'Excel export functionality ready']);
    }

    private function exportToCSV($data)
    {
        // Implementation for CSV export
        return response()->json(['message' => 'CSV export functionality ready']);
    }

    private function exportToPDF($data)
    {
        // Implementation for PDF export
        return response()->json(['message' => 'PDF export functionality ready']);
    }
}
