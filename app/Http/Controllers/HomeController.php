<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\ClassTeacherAssignment;
use App\Models\FieldPermission;
use App\Models\User;
use App\Models\Role; // Add Role model import
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\BellTiming;
use App\Models\ExamPaper;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('welcome');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('receptionist') || $user->hasRole('reception'))) {
            return redirect()->route('admin.front-office.dashboard');
        }

        // Get audit log stats for the dashboard
        $todayChanges = AuditLog::whereDate('performed_at', now()->toDateString())->count();
        $mostEditedRecords = AuditLog::selectRaw('model_type, model_id, count(*) as count')
                                    ->groupBy('model_type', 'model_id')
                                    ->orderByDesc('count')
                                    ->take(5)
                                    ->get();
        
        $topEditingUsers = AuditLog::selectRaw('user_id, user_type, count(*) as count')
                                      ->groupBy('user_id', 'user_type')
                                      ->orderByDesc('count')
                                      ->take(5)
                                      ->get();
        
        $lockedRecords = ClassTeacherAssignment::where('is_active', false)->count();
        
        // Additional stats
        $teacherRoleId = Role::where('name', 'teacher')->value('id');
        $studentRoleId = Role::where('name', 'student')->value('id');
        
        $totalTeachers = $teacherRoleId ? User::whereHas('roles', function($query) use ($teacherRoleId) {
            $query->where('role_id', $teacherRoleId);
        })->count() : 0;
        
        $totalStudents = $studentRoleId ? User::whereHas('roles', function($query) use ($studentRoleId) {
            $query->where('role_id', $studentRoleId);
        })->count() : 0;
        
        $activeClassTeachers = ClassTeacherAssignment::where('is_active', true)->count();
        $totalFieldPermissions = FieldPermission::count();
        $todayAuditLogs = AuditLog::whereDate('performed_at', now()->toDateString())->count();
        
        // Fetch assigned enquiries for logged-in user as counsellor
        $assignedEnquiries = \App\Models\AdmissionEnquiry::where('counsellor_id', $user->id)
            ->latest()
            ->take(5)
            ->get();
        
        return view('home', compact(
            'todayChanges',
            'mostEditedRecords', 
            'topEditingUsers',
            'lockedRecords',
            'totalTeachers',
            'totalStudents',
            'activeClassTeachers',
            'totalFieldPermissions',
            'todayAuditLogs',
            'assignedEnquiries'
        ));
    }
    
    /**
     * Show the welcome page with statistics.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function welcome()
    {
        $stats = [
            'students' => Student::count(),
            'teachers' => Teacher::count(),
            'attendance' => Attendance::count(),
            'bell_timing' => BellTiming::count(),
            'exam_papers' => ExamPaper::count(),
            'applications_this_month' => \App\Models\AdmissionEnquiry::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return view('welcome', compact('stats'));
    }
}