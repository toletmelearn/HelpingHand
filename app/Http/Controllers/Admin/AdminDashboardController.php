<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Fee;
use App\Services\ProfessionalDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(ProfessionalDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('receptionist') || $user->hasRole('reception'))) {
            return redirect()->route('admin.front-office.dashboard');
        }

        $metricsService = new \App\Services\FinanceMetricsService();

        // Get dashboard statistics
        $stats = [
            'total_students' => Student::count(),
            'total_teachers' => Teacher::count(),
            'total_parents' => \App\Models\ParentModel::count(),
            'total_fee_structures' => \App\Models\FeeStructure::count(),
            'today_attendance' => Attendance::whereDate('date', today())->count(),
            'pending_fees' => $metricsService->getOutstandingAmount(),
            'upcoming_exams' => $this->getUpcomingExams(),
            'notices_count' => 5, // Placeholder - would come from notices system
            'today_collection' => $metricsService->getNetCollection(today()->toDateString(), today()->toDateString()),
            'monthly_revenue' => $metricsService->getNetCollection(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()),
        ];

        $showOnboardingChecklist = ($stats['total_teachers'] === 0 || $stats['total_students'] === 0 || $stats['total_parents'] === 0);

        $user = auth()->user();
        $isAdminOrSuperAdmin = $user->hasRole('admin') || $user->hasRole('super-admin');

        // Fetch recent import sessions for the dashboard widget
        $recentImports = \App\Models\ImportSession::with('creator')->latest()->take(5)->get();

        try {
            $upcomingEvents = $this->dashboardService->getUpcomingEvents();
        } catch (\Throwable $e) {
            Log::error('Failed to load upcoming events for admin dashboard: ' . $e->getMessage());
            $upcomingEvents = [];
        }

        // Fetch recent admission enquiries
        if ($isAdminOrSuperAdmin) {
            $recentEnquiries = \App\Models\AdmissionEnquiry::with('counsellor')->latest()->take(5)->get();
            $myEnquiries = collect();
        } else {
            $recentEnquiries = collect();
            $myEnquiries = \App\Models\AdmissionEnquiry::where('counsellor_id', $user->id)->latest()->take(5)->get();
        }

        return view('admin.dashboard', compact('stats', 'showOnboardingChecklist', 'recentImports', 'recentEnquiries', 'myEnquiries', 'upcomingEvents'));
    }
    
    private function getUpcomingExams()
    {
        // Get upcoming exams in the next 30 days
        return \App\Models\Exam::whereBetween('exam_date', [today(), today()->addDays(30)])
            ->count();
    }
}