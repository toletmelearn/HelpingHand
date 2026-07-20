<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\FeeCollection;
use App\Models\Result;
use App\Models\Exam;
use App\Models\LessonPlan;
use App\Models\HomeworkNotice;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Support\Attendance\AttendanceCreditCalculator;

class ProfessionalDashboardService
{
    protected $cacheTtl = 1800; // 30 minutes cache
    
    /**
     * Get comprehensive admin dashboard data
     */
    public function getAdminDashboardData()
    {
        return Cache::remember('admin_dashboard_data', $this->cacheTtl, function() {
            return [
                'statistics' => $this->getAdminStatistics(),
                'recent_activities' => $this->getRecentActivities(),
                'upcoming_events' => $this->getUpcomingEvents(),
                'quick_insights' => $this->getQuickInsights(),
                'performance_metrics' => $this->getPerformanceMetrics()
            ];
        });
    }
    
    /**
     * Get teacher dashboard data
     */
    public function getTeacherDashboardData($teacherId)
    {
        $cacheKey = "teacher_dashboard_{$teacherId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($teacherId) {
            return [
                'assignments' => $this->getTeacherAssignments($teacherId),
                'class_performance' => $this->getTeacherClassPerformance($teacherId),
                'recent_activities' => $this->getTeacherRecentActivities($teacherId),
                'upcoming_tasks' => $this->getTeacherUpcomingTasks($teacherId),
                'quick_stats' => $this->getTeacherQuickStats($teacherId)
            ];
        });
    }
    
    /**
     * Get parent dashboard data
     */
    public function getParentDashboardData($parentId)
    {
        $cacheKey = "parent_dashboard_{$parentId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($parentId) {
            return [
                'children_data' => $this->getParentChildrenData($parentId),
                'recent_updates' => $this->getParentRecentUpdates($parentId),
                'upcoming_events' => $this->getParentUpcomingEvents($parentId),
                'financial_summary' => $this->getParentFinancialSummary($parentId),
                'quick_actions' => $this->getParentQuickActions($parentId)
            ];
        });
    }
    
    /**
     * Get student dashboard data
     */
    public function getStudentDashboardData($studentId)
    {
        $cacheKey = "student_dashboard_{$studentId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function() use ($studentId) {
            return [
                'academic_progress' => $this->getStudentAcademicProgress($studentId),
                'assignments' => $this->getStudentAssignments($studentId),
                'attendance' => $this->getStudentAttendance($studentId),
                'recent_activities' => $this->getStudentRecentActivities($studentId),
                'upcoming_events' => $this->getStudentUpcomingEvents($studentId)
            ];
        });
    }
    
    /**
     * Get real-time dashboard updates
     */
    public function getRealTimeUpdates($userType, $userId = null)
    {
        $updates = [];
        
        switch ($userType) {
            case 'admin':
                $updates = $this->getAdminRealTimeUpdates();
                break;
            case 'teacher':
                $updates = $this->getTeacherRealTimeUpdates($userId);
                break;
            case 'parent':
                $updates = $this->getParentRealTimeUpdates($userId);
                break;
            case 'student':
                $updates = $this->getStudentRealTimeUpdates($userId);
                break;
        }
        
        return $updates;
    }
    
    /**
     * Clear dashboard cache
     */
    public function clearDashboardCache($userType, $userId = null)
    {
        switch ($userType) {
            case 'admin':
                Cache::forget('admin_dashboard_data');
                break;
            case 'teacher':
                Cache::forget("teacher_dashboard_{$userId}");
                break;
            case 'parent':
                Cache::forget("parent_dashboard_{$userId}");
                break;
            case 'student':
                Cache::forget("student_dashboard_{$userId}");
                break;
        }
    }
    
    /**
     * Public helper methods for admin dashboard
     */
    public function getAdminStatistics()
    {
        return [
            'students' => [
                'total' => Student::count(),
                'new_this_month' => Student::whereMonth('created_at', now()->month)->count(),
                'active' => Student::where('status', 'active')->count()
            ],
            'teachers' => [
                'total' => Teacher::count(),
                'present_today' => $this->getTeachersPresentToday(),
                'on_leave' => $this->getTeachersOnLeave()
            ],
            'attendance' => [
                'today_rate' => $this->getTodayAttendanceRate(),
                'this_month_rate' => $this->getMonthlyAttendanceRate()
            ],
            'finance' => [
                'monthly_collection' => $this->getMonthlyCollections(),
                'pending_dues' => $this->getPendingDues(),
                'collection_rate' => $this->getCollectionRate()
            ],
            'academics' => [
                'active_exams' => Exam::where('status', 'active')->count(),
                'results_pending' => $this->getPendingResults(),
                'lesson_plans' => LessonPlan::where('status', 'approved')->count()
            ]
        ];
    }
    
    public function getRecentActivities()
    {
        // This would fetch recent system activities
        // For now, returning sample data
        return [
            [
                'type' => 'student_registration',
                'description' => 'New student registered',
                'time' => '2 hours ago',
                'icon' => 'bi-person-plus'
            ],
            [
                'type' => 'fee_collection',
                'description' => 'Fee collected for Class 10A',
                'time' => '4 hours ago',
                'icon' => 'bi-currency-rupee'
            ],
            [
                'type' => 'attendance',
                'description' => 'Attendance marked for 85% students',
                'time' => '6 hours ago',
                'icon' => 'bi-calendar-check'
            ]
        ];
    }
    
    public function getUpcomingEvents()
    {
        $events = [];
        
        // Get upcoming exams
        $upcomingExams = Exam::where('date', '>=', now())
                            ->where('date', '<=', now()->addDays(7))
                            ->orderBy('date')
                            ->take(5)
                            ->get();
        
        foreach ($upcomingExams as $exam) {
            $events[] = [
                'title' => $exam->name,
                'date' => $exam->date,
                'type' => 'exam',
                'description' => "Exam for {$exam->subject} in {$exam->class}"
            ];
        }
        
        // Add other events (holidays, meetings, etc.)
        return $events;
    }
    
    public function getQuickInsights()
    {
        return [
            'low_attendance_classes' => $this->getLowAttendanceClasses(),
            'pending_fee_classes' => $this->getPendingFeeClasses(),
            'teacher_substitutions' => $this->getTeacherSubstitutions(),
            'academic_alerts' => $this->getAcademicAlerts()
        ];
    }
    
    public function getPerformanceMetrics()
    {
        return [
            'student_growth' => $this->calculateStudentGrowth(),
            'teacher_performance' => $this->calculateTeacherPerformance(),
            'financial_health' => $this->calculateFinancialHealth(),
            'operational_efficiency' => $this->calculateOperationalEfficiency()
        ];
    }
    
    /**
     * Private helper methods for teacher dashboard
     */
    private function getTeacherAssignments($teacherId)
    {
        $teacher = Teacher::findOrFail($teacherId);
        
        return [
            'classes' => $teacher->classAssignments->count(),
            'subjects' => $teacher->subjectAssignments->count(),
            'students' => $teacher->classAssignments->sum(function($assignment) {
                return $assignment->schoolClass->students->count();
            }),
            'pending_assignments' => HomeworkNotice::where('assigned_by', $teacherId)
                                           ->where('due_date', '>=', now())
                                           ->where('type', 'homework')
                                           ->count()
        ];
    }
    
    private function getTeacherClassPerformance($teacherId)
    {
        // Calculate class-wise performance metrics
        return [];
    }
    
    private function getTeacherRecentActivities($teacherId)
    {
        return [
            [
                'type' => 'lesson_plan',
                'description' => 'Lesson plan submitted for approval',
                'time' => '1 day ago',
                'status' => 'pending'
            ],
            [
                'type' => 'attendance',
                'description' => 'Attendance marked for Class 9B',
                'time' => '2 days ago',
                'status' => 'completed'
            ]
        ];
    }
    
    private function getTeacherUpcomingTasks($teacherId)
    {
        return [
            [
                'title' => 'Submit Lesson Plan',
                'due_date' => now()->addDays(3),
                'priority' => 'high'
            ],
            [
                'title' => 'Parent-Teacher Meeting',
                'due_date' => now()->addDays(5),
                'priority' => 'medium'
            ]
        ];
    }
    
    private function getTeacherQuickStats($teacherId)
    {
        return [
            'attendance_rate' => $this->getTeacherAttendanceRate($teacherId),
            'homework_submitted' => $this->getTeacherHomeworkSubmitted($teacherId),
            'lesson_plans' => $this->getTeacherLessonPlans($teacherId)
        ];
    }
    
    /**
     * Private helper methods for parent dashboard
     */
    private function getParentChildrenData($parentId)
    {
        $students = Student::where('parent_id', $parentId)->get();
        
        return $students->map(function($student) {
            return [
                'student' => $student,
                'attendance_rate' => $this->getStudentAttendanceRate($student->id),
                'recent_results' => $this->getStudentRecentResults($student->id),
                'pending_fees' => $this->getStudentPendingFees($student->id)
            ];
        });
    }
    
    private function getParentRecentUpdates($parentId)
    {
        return [
            [
                'type' => 'result',
                'description' => 'Mathematics result published',
                'time' => '1 day ago'
            ],
            [
                'type' => 'notice',
                'description' => 'Parent-Teacher meeting scheduled',
                'time' => '2 days ago'
            ]
        ];
    }
    
    private function getParentFinancialSummary($parentId)
    {
        $students = Student::where('parent_id', $parentId)->get();
        $totalPending = 0;
        
        foreach ($students as $student) {
            $totalPending += $this->getStudentPendingFees($student->id);
        }
        
        return [
            'total_pending' => $totalPending,
            'last_payment' => $this->getParentLastPayment($parentId),
            'payment_history' => $this->getParentPaymentHistory($parentId)
        ];
    }
    
    /**
     * Helper methods for calculations
     */
    private function getTeachersPresentToday()
    {
        // This would check teacher attendance system
        return Teacher::count() * 0.9; // Sample calculation
    }
    
    private function getTeachersOnLeave()
    {
        return 2; // Sample data
    }
    
    private function getTodayAttendanceRate()
    {
        $records = Attendance::whereDate('date', today())->get(['status']);
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return $summary['attendance_rate'];
    }
    
    private function getMonthlyAttendanceRate()
    {
        $startDate = now()->startOfMonth();
        $endDate = now()->endOfMonth();
        
        $records = Attendance::whereBetween('date', [$startDate, $endDate])->get(['status']);
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return $summary['attendance_rate'];
    }
    
    private function getMonthlyCollections()
    {
        return FeeCollection::whereMonth('payment_date', now()->month)
                           ->sum('total_amount');
    }
    
    private function getPendingDues()
    {
        // Calculate actual pending dues
        return 150000; // Sample data
    }
    
    private function getCollectionRate()
    {
        $expected = Student::count() * 2500; // Monthly expectation
        $collected = $this->getMonthlyCollections();
        
        return $expected > 0 ? round(($collected / $expected) * 100, 2) : 0;
    }
    
    private function getPendingResults()
    {
        return Result::where('status', 'pending')->count();
    }
    
    private function getLowAttendanceClasses()
    {
        // Get classes with attendance below 75%
        return [];
    }
    
    private function getPendingFeeClasses()
    {
        // Get classes with high pending fees
        return [];
    }
    
    private function getTeacherSubstitutions()
    {
        // Get current teacher substitutions
        return [];
    }
    
    private function getAcademicAlerts()
    {
        // Get academic alerts and notifications
        return [];
    }
    
    private function getParentUpcomingEvents($parentId)
    {
        // Get upcoming events for parent's children
        return [];
    }
    
    private function getParentQuickActions($parentId)
    {
        // Get quick actions for parent
        return [];
    }
    
    private function getStudentAcademicProgress($studentId)
    {
        // Get student academic progress
        return [];
    }
    
    private function getStudentAssignments($studentId)
    {
        // Get student assignments
        return [];
    }
    
    private function getStudentAttendance($studentId)
    {
        // Get student attendance
        return [];
    }
    
    private function getStudentRecentActivities($studentId)
    {
        // Get student recent activities
        return [];
    }
    
    private function getStudentUpcomingEvents($studentId)
    {
        // Get student upcoming events
        return [];
    }
    
    private function getAdminRealTimeUpdates()
    {
        // Get admin real-time updates
        return [];
    }
    
    private function getTeacherRealTimeUpdates($teacherId)
    {
        // Get teacher real-time updates
        return [];
    }
    
    private function getParentRealTimeUpdates($parentId)
    {
        // Get parent real-time updates
        return [];
    }
    
    private function getStudentRealTimeUpdates($studentId)
    {
        // Get student real-time updates
        return [];
    }
    
    private function getTeacherAttendanceRate($teacherId)
    {
        // Get teacher attendance rate
        return 95.5; // Sample data
    }
    
    private function getTeacherHomeworkSubmitted($teacherId)
    {
        // Get teacher homework submission count
        return 25; // Sample data
    }
    
    private function getTeacherLessonPlans($teacherId)
    {
        // Get teacher lesson plans count
        return 12; // Sample data
    }
    
    private function getStudentAttendanceRate($studentId)
    {
        // Get student attendance rate
        return 85.5; // Sample data
    }
    
    private function getStudentRecentResults($studentId)
    {
        // Get student recent results
        return []; // Sample data
    }
    
    private function getStudentPendingFees($studentId)
    {
        // Get student pending fees
        return 2500; // Sample data
    }
    
    private function getParentLastPayment($parentId)
    {
        // Get parent's last payment
        return now()->subDays(5); // Sample data
    }
    
    private function getParentPaymentHistory($parentId)
    {
        // Get parent's payment history
        return []; // Sample data
    }
    
    private function calculateStudentGrowth()
    {
        // Calculate student academic growth metrics
        return [
            'current_year' => 85.5,
            'previous_year' => 82.3,
            'growth_rate' => 3.2
        ];
    }
    
    private function calculateTeacherPerformance()
    {
        // Calculate teacher performance metrics
        return [
            'lesson_plans_submitted' => 95,
            'attendance_marked' => 98,
            'homework_assigned' => 25
        ];
    }
    
    private function calculateFinancialHealth()
    {
        // Calculate financial health metrics
        return [
            'collection_rate' => $this->getCollectionRate(),
            'pending_dues' => $this->getPendingDues(),
            'monthly_target' => Student::count() * 2500
        ];
    }
    
    private function calculateOperationalEfficiency()
    {
        // Calculate operational efficiency metrics
        return [
            'system_uptime' => 99.8,
            'response_time' => 1.2,
            'user_satisfaction' => 4.5
        ];
    }
}