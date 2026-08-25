<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Fee;
use App\Services\ProfessionalDashboardService;
use App\Services\Timetable\SubstitutionDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    protected $dashboardService;

    protected $substitutionDashboardService;

    public function __construct(ProfessionalDashboardService $dashboardService, SubstitutionDashboardService $substitutionDashboardService)
    {
        $this->dashboardService = $dashboardService;
        $this->substitutionDashboardService = $substitutionDashboardService;
    }

    public function index()
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('receptionist') || $user->hasRole('reception'))) {
            return redirect()->route('admin.front-office.dashboard');
        }

        // NOTE: an admin/super-admin-only gate was tried here and reverted --
        // SidebarPermissionVisibilityTest, SidebarAcademicAssignmentLinksTest,
        // ReconciliationUpiYearClosingPermissionTest, and
        // FeeCollectionAndOperationsPermissionTest all deliberately use this
        // exact route as a generic "does this staff role see the sidebar"
        // probe for clerk/accountant/teacher/staff roles with no special
        // permissions, and assert 200. The dashboard shell is intentionally
        // reachable by any authenticated staff role; permission-gating
        // happens in the sidebar/content itself. See FINAL REPORT.

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

        // T5 item 4: today's substitution count + unfilled arrangements.
        // Same degrade pattern as upcoming events -- a service hiccup here
        // must never break the whole dashboard.
        try {
            $substitutionSummary = $this->substitutionDashboardService->getTodaysSummary();
        } catch (\Throwable $e) {
            Log::error('Failed to load substitution summary for admin dashboard: ' . $e->getMessage());
            $substitutionSummary = ['count' => 0, 'unfilled' => 0];
        }
        $substitutionsToday = $substitutionSummary['count'];
        $unfilledArrangements = $substitutionSummary['unfilled'];

        // Fetch recent admission enquiries
        if ($isAdminOrSuperAdmin) {
            $recentEnquiries = \App\Models\AdmissionEnquiry::with('counsellor')->latest()->take(5)->get();
            $myEnquiries = collect();
        } else {
            $recentEnquiries = collect();
            $myEnquiries = \App\Models\AdmissionEnquiry::where('counsellor_id', $user->id)->latest()->take(5)->get();
        }

        return view('admin.dashboard', compact('stats', 'showOnboardingChecklist', 'recentImports', 'recentEnquiries', 'myEnquiries', 'upcomingEvents', 'substitutionsToday', 'unfilledArrangements'));
    }
    
    private function getUpcomingExams()
    {
        // Get upcoming exams in the next 30 days
        return \App\Models\Exam::whereBetween('exam_date', [today(), today()->addDays(30)])
            ->count();
    }
}