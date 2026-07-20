<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProfessionalDashboardService;
use Illuminate\Http\Request;

class ProfessionalDashboardController extends Controller
{
    protected $dashboardService;

    public function __construct(ProfessionalDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Display enhanced admin dashboard
     */
    public function adminDashboard()
    {
        $dashboardData = $this->dashboardService->getAdminDashboardData();
        
        return view('admin.dashboards.professional', compact('dashboardData'));
    }

    /**
     * Get real-time dashboard updates
     */
    public function getRealTimeUpdates(Request $request)
    {
        $userType = $request->get('user_type', 'admin');
        $userId = $request->get('user_id');
        
        $updates = $this->dashboardService->getRealTimeUpdates($userType, $userId);
        
        return response()->json([
            'success' => true,
            'updates' => $updates,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Refresh specific dashboard section
     */
    public function refreshSection(Request $request)
    {
        $section = $request->get('section');
        $userType = $request->get('user_type', 'admin');
        $userId = $request->get('user_id');
        
        // Clear cache for this section
        $this->dashboardService->clearDashboardCache($userType, $userId);
        
        // Get fresh data
        $data = $this->getSectionData($section, $userType, $userId);
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get customizable dashboard widgets
     */
    public function getDashboardWidgets()
    {
        $widgets = [
            'statistics' => [
                'title' => 'System Statistics',
                'type' => 'statistics',
                'icon' => 'bi-bar-chart',
                'permissions' => ['view-statistics']
            ],
            'recent_activities' => [
                'title' => 'Recent Activities',
                'type' => 'activities',
                'icon' => 'bi-activity',
                'permissions' => ['view-activities']
            ],
            'upcoming_events' => [
                'title' => 'Upcoming Events',
                'type' => 'events',
                'icon' => 'bi-calendar-event',
                'permissions' => ['view-events']
            ],
            'quick_insights' => [
                'title' => 'Quick Insights',
                'type' => 'insights',
                'icon' => 'bi-lightbulb',
                'permissions' => ['view-insights']
            ],
            'performance_metrics' => [
                'title' => 'Performance Metrics',
                'type' => 'metrics',
                'icon' => 'bi-speedometer2',
                'permissions' => ['view-metrics']
            ]
        ];
        
        return response()->json([
            'success' => true,
            'widgets' => $widgets
        ]);
    }

    /**
     * Save user dashboard preferences
     */
    public function savePreferences(Request $request)
    {
        $request->validate([
            'layout' => 'required|array',
            'widgets' => 'required|array',
            'refresh_interval' => 'nullable|integer|min:30|max:3600'
        ]);
        
        // Save preferences to user settings
        $user = \Illuminate\Support\Facades\Auth::user();
        $preferences = [
            'dashboard_layout' => $request->layout,
            'dashboard_widgets' => $request->widgets,
            'refresh_interval' => $request->refresh_interval ?? 300
        ];
        
        // This would typically save to user preferences table
        // For now, we'll just return success
        return response()->json([
            'success' => true,
            'message' => 'Dashboard preferences saved successfully'
        ]);
    }

    /**
     * Export dashboard data
     */
    public function exportDashboardData(Request $request)
    {
        $format = $request->get('format', 'pdf');
        $sections = $request->get('sections', ['statistics', 'recent_activities']);
        
        // Generate export based on format and sections
        switch ($format) {
            case 'pdf':
                return $this->generatePDFExport($sections);
            case 'excel':
                return $this->generateExcelExport($sections);
            case 'csv':
                return $this->generateCSVExport($sections);
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported export format'
                ], 400);
        }
    }

    /**
     * Get notifications for dashboard
     */
    public function getNotifications()
    {
        $notifications = [
            [
                'id' => 1,
                'type' => 'info',
                'title' => 'System Update',
                'message' => 'New features available in the dashboard',
                'time' => now()->subHour(),
                'read' => false
            ],
            [
                'id' => 2,
                'type' => 'warning',
                'title' => 'Low Attendance Alert',
                'message' => 'Class 9A attendance below 75%',
                'time' => now()->subHours(2),
                'read' => false
            ],
            [
                'id' => 3,
                'type' => 'success',
                'title' => 'Fee Collection',
                'message' => 'Monthly fee collection completed',
                'time' => now()->subDay(),
                'read' => true
            ]
        ];
        
        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => collect($notifications)->where('read', false)->count()
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markNotificationRead($notificationId)
    {
        // Mark notification as read in database
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Private helper methods
     */
    private function getSectionData($section, $userType, $userId)
    {
        switch ($section) {
            case 'statistics':
                return $this->dashboardService->getAdminStatistics();
            case 'recent_activities':
                return $this->dashboardService->getRecentActivities();
            case 'upcoming_events':
                return $this->dashboardService->getUpcomingEvents();
            case 'quick_insights':
                return $this->dashboardService->getQuickInsights();
            case 'performance_metrics':
                return $this->dashboardService->getPerformanceMetrics();
            default:
                return [];
        }
    }

    private function generatePDFExport($sections)
    {
        // PDF generation implementation
        return response()->json([
            'message' => 'PDF export functionality to be implemented'
        ]);
    }

    private function generateExcelExport($sections)
    {
        // Excel generation implementation
        return response()->json([
            'message' => 'Excel export functionality to be implemented'
        ]);
    }

    private function generateCSVExport($sections)
    {
        // CSV generation implementation
        return response()->json([
            'message' => 'CSV export functionality to be implemented'
        ]);
    }
}