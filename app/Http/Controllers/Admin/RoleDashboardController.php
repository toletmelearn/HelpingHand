<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Support\Attendance\AttendanceCreditCalculator;

class RoleDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show dashboard based on user role
     */
    public function showDashboard()
    {
        $user = Auth::user();
        
        // Redirect based on user role
        if ($this->hasRole($user, 'admin') || $user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        } elseif ($this->hasRole($user, 'teacher') || $user->role === 'teacher') {
            return redirect()->route('teacher.dashboard');
        } elseif ($this->hasRole($user, 'student') || $user->role === 'student') {
            return redirect()->route('student.dashboard');
        } elseif ($this->hasRole($user, 'accountant') || $user->role === 'accountant') {
            return redirect()->route('accountant.dashboard');
        } else {
            // Default to admin dashboard for users without specific roles
            return redirect()->route('admin.dashboard');
        }
    }

    /**
     * Check if user has a specific role
     */
    private function hasRole($user, $roleName)
    {
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($roleName);
        }
        
        // Fallback: check the role column directly
        return $user->role === $roleName;
    }

    /**
     * Admin dashboard
     */
    public function adminDashboard()
    {
        $user = Auth::user();
        
        // Check if user has admin role
        if (!$this->hasRole($user, 'admin') && $user->role !== 'admin') {
            abort(403, 'Unauthorized access');
        }

        // Get dashboard statistics
        $metricsService = new \App\Services\FinanceMetricsService();
        $stats = [
            'total_students' => \App\Models\Student::count(),
            'total_teachers' => \App\Models\Teacher::count(),
            'today_attendance' => \App\Models\Attendance::whereDate('date', today())->count(),
            'pending_fees' => $metricsService->getOutstandingAmount(),
            'upcoming_exams' => $this->getUpcomingExams(),
            'notices_count' => \App\Models\HomeworkNotice::where('type', 'notice')->active()->count(),
            'today_collection' => $metricsService->getNetCollection(today()->toDateString(), today()->toDateString()),
            'monthly_revenue' => $metricsService->getNetCollection(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    /**
     * Teacher dashboard
     */
    public function teacherDashboard()
    {
        $user = Auth::user();
        
        // Check if user has teacher role
        if (!$this->hasRole($user, 'teacher') && $user->role !== 'teacher') {
            abort(403, 'Unauthorized access');
        }

        // Get teacher-specific data
        $teacher = $user->teacher;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher profile not found. Please contact administrator.');
        }

        $stats = [
            'my_students' => $teacher->assignedStudents()->count(),
            'my_classes' => $teacher->assignedClasses()->count(),
            'my_subjects' => $teacher->assignedSubjects()->count(),
            'pending_assignments' => \App\Models\HomeworkNotice::where('assigned_by', $user->id)
                ->where('type', 'homework')
                ->where('due_date', '>=', today())
                ->count(),
            'attendance_taken' => \App\Models\Attendance::where('marked_by', $user->id)
                ->whereDate('date', today())
                ->count(),
            'my_exams' => \App\Models\Exam::where('created_by', $user->id)->count(),
            'recent_notifications' => $this->getUserUnreadNotifications($user, 5)
        ];

        return view('teacher.dashboard', compact('stats', 'teacher'));
    }

    /**
     * Get user's unread notifications
     */
    private function getUserUnreadNotifications($user, $limit = null)
    {
        if (method_exists($user, 'unreadNotifications')) {
            $query = $user->unreadNotifications();
            if ($limit) {
                return $query->take($limit)->get();
            }
            return $query->get();
        }
        
        // Fallback if the method doesn't exist
        return collect([]);
    }

    /**
     * Student dashboard
     */
    public function studentDashboard()
    {
        $user = Auth::user();
        
        // Check if user has student role
        if (!$this->hasRole($user, 'student') && $user->role !== 'student') {
            abort(403, 'Unauthorized access');
        }

        $student = $user->student;
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found. Please contact administrator.');
        }

        $stats = [
            'my_class' => $student->schoolClass?->name ?? $student->class ?? 'N/A',
            'my_section' => $student->schoolSection?->name ?? $student->section ?? 'N/A',
            'attendance_rate' => $this->getStudentAttendanceRate($student->id),
            'pending_homework' => \App\Models\HomeworkNotice::where('class_id', $student->school_class_id)
                ->where('type', 'homework')
                ->where('due_date', '>=', today())
                ->count(),
            'my_results' => \App\Models\Result::where('student_id', $student->id)->count(),
            'fee_status' => $this->getStudentFeeStatus($student->id),
            'upcoming_exams' => \App\Models\Exam::where('class_name', $student->class)
                ->where('exam_date', '>=', today())
                ->take(5)
                ->get()
        ];

        return view('student.dashboard', compact('stats', 'student'));
    }

    /**
     * Accountant dashboard
     */
    public function accountantDashboard()
    {
        $user = Auth::user();
        
        // Check if user has accountant role
        if (!$this->hasRole($user, 'accountant') && $user->role !== 'accountant') {
            abort(403, 'Unauthorized access');
        }

        $metricsService = new \App\Services\FinanceMetricsService();
        $stats = [
            'today_collections' => $metricsService->getNetCollection(today()->toDateString(), today()->toDateString()),
            'monthly_collections' => $metricsService->getNetCollection(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()),
            'pending_fees' => $metricsService->getOutstandingAmount(),
            'expenses_this_month' => \App\Models\Expense::currentMonth()->sum('amount'),
            'total_students' => \App\Models\Student::count(),
            'fee_defaulter_count' => $this->getFeeDefaulterCount(),
            'recent_transactions' => \App\Models\FeeCollection::latest()->take(10)->get()
        ];

        return view('accountant.dashboard', compact('stats'));
    }

    /**
     * Calculate pending fees
     */
    private function calculatePendingFees()
    {
        // Calculate pending fees from the fee automation system
        $students = \App\Models\Student::with([
            'feeAssignments.feeStructure.feeStructureItems',
            'feeCollections.feeCollectionItems'
        ])->get();
        
        $pendingAmount = 0;
        
        foreach ($students as $student) {
            $assignments = $student->feeAssignments;
            
            foreach ($assignments as $assignment) {
                $structure = $assignment->feeStructure;
                
                if (!$structure) continue;
                
                $frequency = $structure->frequency;
                $months = $this->generateMonths($frequency);
                
                foreach ($months as $month) {
                    $totalExpected = 0;
                    $totalPaid = 0;
                    
                    foreach ($structure->feeStructureItems as $item) {
                        $totalExpected += $item->amount;
                        
                        // For each fee type, check if it's been paid
                        $paidItems = $student->feeCollections()
                            ->whereHas('feeCollectionItems', function($query) use ($item) {
                                $query->where('fee_collection_items.fee_type_id', $item->fee_type_id);
                            })
                            ->get();
                        
                        foreach ($paidItems as $collection) {
                            foreach ($collection->feeCollectionItems as $collectionItem) {
                                if ($collectionItem->fee_type_id == $item->fee_type_id) {
                                    $totalPaid += $collectionItem->amount;
                                }
                            }
                        }
                    }
                    
                    $pendingAmount += ($totalExpected - $totalPaid);
                }
            }
        }
        
        return $pendingAmount;
    }
    
    private function generateMonths($frequency)
    {
        switch ($frequency) {
            case 'monthly':
                return ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
            case 'quarterly':
                return ['Q1', 'Q2', 'Q3', 'Q4'];
            case 'yearly':
                return ['Annual'];
            default:
                return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        }
    }

    /**
     * Get upcoming exams
     */
    private function getUpcomingExams()
    {
        // Get upcoming exams in the next 30 days
        return \App\Models\Exam::whereBetween('exam_date', [today(), today()->addDays(30)])
            ->count();
    }

    /**
     * Get student attendance rate
     */
    private function getStudentAttendanceRate($studentId)
    {
        $records = \App\Models\Attendance::where('student_id', $studentId)->get(['status']);
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return $summary['attendance_rate'];
    }

    /**
     * Get student fee status
     */
    private function getStudentFeeStatus($studentId)
    {
        $totalAssigned = \App\Models\StudentFeeAssignment::where('student_id', $studentId)
            ->with(['feeStructure.feeStructureItems'])
            ->get()
            ->sum(function($assignment) {
                return $assignment->feeStructure->total_amount ?? 0;
            });
        
        $totalPaid = \App\Models\FeeCollection::where('student_id', $studentId)
            ->sum('final_amount');
        
        $pending = $totalAssigned - $totalPaid;
        
        return [
            'total_assigned' => $totalAssigned,
            'total_paid' => $totalPaid,
            'pending' => $pending,
            'status' => $pending > 0 ? 'pending' : 'clear'
        ];
    }

    /**
     * Get fee defaulter count
     */
    private function getFeeDefaulterCount()
    {
        // Get students with pending fees for 2+ months
        return \App\Models\Student::whereHas('feeAssignments', function($query) {
            $query->whereHas('feeStructure.feeStructureItems', function($subQuery) {
                $subQuery->whereRaw('(SELECT SUM(final_amount) FROM fee_collections WHERE fee_collections.student_id = student_fee_assignments.student_id AND fee_collections.fee_structure_id = student_fee_assignments.fee_structure_id) < (SELECT SUM(amount) FROM fee_structure_items WHERE fee_structure_items.fee_structure_id = student_fee_assignments.fee_structure_id)');
            });
        })->count();
    }
}