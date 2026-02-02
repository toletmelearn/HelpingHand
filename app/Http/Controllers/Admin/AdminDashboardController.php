<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Fee;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index()
    {
        // Get dashboard statistics
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'today_attendance' => Attendance::whereDate('date', today())->count(),
            'pending_fees' => Fee::where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}