<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;
use App\Models\ClassTeacherAssignment;
use App\Models\FieldPermission;
use App\Models\User;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
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
        $totalTeachers = User::role('teacher')->count();
        $totalStudents = User::role('student')->count();
        $activeClassTeachers = ClassTeacherAssignment::where('is_active', true)->count();
        $totalFieldPermissions = FieldPermission::count();
        $todayAuditLogs = AuditLog::whereDate('performed_at', now()->toDateString())->count();
        
        return view('home', compact(
            'todayChanges',
            'mostEditedRecords', 
            'topEditingUsers',
            'lockedRecords',
            'totalTeachers',
            'totalStudents',
            'activeClassTeachers',
            'totalFieldPermissions',
            'todayAuditLogs'
        ));
    }
}