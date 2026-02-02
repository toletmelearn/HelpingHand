<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherBiometricRecord;
use App\Models\BiometricSetting;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Concerns\FromCollection;

class BiometricController extends Controller
{
    /**
     * Display the teacher's biometric dashboard
     */
    public function dashboard()
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        
        if (!$teacher) {
            // Try to find teacher by email if not directly linked
            $teacher = Teacher::where('email', $user->email)->first();
        }
        
        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }

        // Get biometric records for the teacher
        $currentMonth = now()->format('Y-m');
        $records = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->whereYear('date', now()->year)
            ->whereMonth('date', now()->month)
            ->orderBy('date', 'desc')
            ->limit(30)
            ->get();

        // Calculate statistics for the current month
        $stats = $this->calculateTeacherStats($teacher->id, now()->year, now()->month);

        // Get recent notifications related to biometric records
        $notifications = $this->getRecentNotifications($teacher->id, 5);

        return view('teachers.biometric.dashboard', compact('teacher', 'records', 'stats', 'notifications'));
    }

    /**
     * Get teacher's biometric records with filters
     */
    public function getRecords(Request $request)
    {
        $teacher = Auth::user()->teacher;
        
        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $limit = $request->get('limit', 50);

        $records = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['biometricDevice:id,name'])
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'date' => $record->date->format('Y-m-d'),
                    'day' => $record->date->format('l'),
                    'first_in_time' => $record->first_in_time ? $record->first_in_time->format('h:i A') : 'N/A',
                    'last_out_time' => $record->last_out_time ? $record->last_out_time->format('h:i A') : 'N/A',
                    'working_duration' => $record->working_duration_formatted,
                    'status' => $record->status_badge['text'],
                    'status_class' => $record->status_badge['class'],
                    'source' => $record->import_source === 'manual' ? 'Manual Entry' : 'Device',
                    'device' => $record->biometricDevice ? $record->biometricDevice->name : 'N/A',
                    'late_minutes' => $record->late_minutes ?? 0,
                    'early_departure_minutes' => $record->early_departure_minutes ?? 0,
                    'remarks' => $record->remarks ?? 'N/A'
                ];
            });

        return response()->json([
            'success' => true,
            'records' => $records
        ]);
    }

    /**
     * Get teacher's monthly summary
     */
    public function monthlySummary(Request $request)
    {
        $teacher = Auth::user()->teacher;
        
        if (!$teacher) {
            return response()->json(['error' => 'Teacher profile not found'], 404);
        }

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $summary = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('COUNT(*) as total_days,
                         SUM(CASE WHEN arrival_status = "late" THEN 1 ELSE 0 END) as late_arrivals,
                         SUM(CASE WHEN departure_status = "early_exit" THEN 1 ELSE 0 END) as early_departures,
                         SUM(CASE WHEN departure_status = "half_day" THEN 1 ELSE 0 END) as half_days,
                         AVG(calculated_duration) as avg_working_hours,
                         MIN(date) as from_date,
                         MAX(date) as to_date')
            ->first();

        $presentDays = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->count();

        $attendancePercentage = $summary->total_days > 0 ? ($presentDays / $summary->total_days) * 100 : 0;

        return response()->json([
            'success' => true,
            'summary' => [
                'total_days' => $summary->total_days ?? 0,
                'present_days' => $presentDays,
                'attendance_percentage' => round($attendancePercentage, 2),
                'late_arrivals' => $summary->late_arrivals ?? 0,
                'early_departures' => $summary->early_departures ?? 0,
                'half_days' => $summary->half_days ?? 0,
                'avg_working_hours' => number_format($summary->avg_working_hours ?? 0, 2),
                'from_date' => $summary->from_date ? $summary->from_date->format('Y-m-d') : null,
                'to_date' => $summary->to_date ? $summary->to_date->format('Y-m-d') : null,
            ]
        ]);
    }

    /**
     * Download teacher biometric report
     */
    public function downloadReport(Request $request)
    {
        $teacher = Auth::user()->teacher;
        
        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }

        $request->validate([
            'type' => 'required|in:attendance,performance,detailed',
            'format' => 'required|in:pdf,excel',
            'month' => 'nullable|date_format:Y-m'
        ]);

        $reportType = $request->get('type', 'attendance');
        $format = $request->get('format', 'pdf');
        $month = $request->get('month', now()->format('Y-m'));

        $year = substr($month, 0, 4);
        $monthNum = substr($month, 5, 2);

        // Get records for the specified month
        $records = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $monthNum)
            ->orderBy('date', 'asc')
            ->get();

        // Get summary for the month
        $summary = $this->calculateTeacherStats($teacher->id, $year, $monthNum);

        $data = [
            'teacher' => $teacher,
            'records' => $records,
            'summary' => $summary,
            'month' => $month,
            'reportType' => $reportType,
            'year' => $year,
            'monthName' => date('F Y', mktime(0, 0, 0, $monthNum, 1, $year))
        ];

        $fileName = 'biometric_report_' . $teacher->id . '_' . $month . '_' . $reportType;

        if ($format === 'excel') {
            return $this->exportToExcel($data, $fileName);
        } else {
            return $this->exportToPDF($data, $fileName);
        }
    }

    /**
     * Show notification preferences
     */
    public function notificationPreferences()
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        
        if (!$teacher) {
            // Try to find teacher by email if not directly linked
            $teacher = Teacher::where('email', $user->email)->first();
        }
        
        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }

        // Get current biometric settings
        $settings = \App\Models\BiometricSetting::getActiveSettings();
        if (!$settings) {
            $settings = new \App\Models\BiometricSetting(\App\Models\BiometricSetting::getDefaultSettings());
        }

        return view('teachers.biometric.notification-preferences', compact('teacher', 'settings'));
    }

    /**
     * Update notification preferences
     */
    public function updateNotificationPreferences(Request $request)
    {
        $user = Auth::user();
        $teacher = $user->teacher;
        
        if (!$teacher) {
            // Try to find teacher by email if not directly linked
            $teacher = Teacher::where('email', $user->email)->first();
        }
        
        if (!$teacher) {
            abort(404, 'Teacher profile not found');
        }

        // Validate the request
        $request->validate([
            'enable_late_arrival_notifications' => 'boolean',
            'enable_early_departure_notifications' => 'boolean',
            'enable_half_day_notifications' => 'boolean',
            'enable_performance_notifications' => 'boolean',
        ]);

        // Get current biometric settings
        $settings = \App\Models\BiometricSetting::getActiveSettings();
        if (!$settings) {
            $settings = new \App\Models\BiometricSetting(\App\Models\BiometricSetting::getDefaultSettings());
        }

        // Update notification preferences
        $notificationPrefs = $settings->notification_preferences ?? [];
        $notificationPrefs['teacher_' . $teacher->id] = [
            'enable_late_arrival_notifications' => $request->get('enable_late_arrival_notifications', false),
            'enable_early_departure_notifications' => $request->get('enable_early_departure_notifications', false),
            'enable_half_day_notifications' => $request->get('enable_half_day_notifications', false),
            'enable_performance_notifications' => $request->get('enable_performance_notifications', false),
        ];

        // Update settings
        $settings->update(['notification_preferences' => $notificationPrefs]);

        return redirect()->back()->with('success', 'Notification preferences updated successfully!');
    }

    private function exportToExcel($data, $fileName)
    {
        $records = collect($data['records'])->map(function ($record) {
            return [
                'Date' => $record->date->format('Y-m-d'),
                'Day' => $record->date->format('l'),
                'First In Time' => $record->first_in_time ? $record->first_in_time->format('H:i') : 'N/A',
                'Last Out Time' => $record->last_out_time ? $record->last_out_time->format('H:i') : 'N/A',
                'Working Hours' => number_format($record->calculated_duration, 2),
                'Arrival Status' => ucfirst(str_replace('_', ' ', $record->arrival_status)),
                'Departure Status' => ucfirst(str_replace('_', ' ', $record->departure_status)),
                'Late Minutes' => $record->late_minutes ?? 0,
                'Early Departure Minutes' => $record->early_departure_minutes ?? 0,
                'Remarks' => $record->remarks ?? 'N/A',
            ];
        });

        return Excel::download(new class($records) implements FromCollection {
            private $data;
            
            public function __construct($data)
            {
                $this->data = $data;
            }
            
            public function collection()
            {
                return $this->data;
            }
        }, $fileName . '.xlsx');
    }

    private function exportToPDF($data, $fileName)
    {
        $pdf = Pdf::loadView('admin.teacher-biometrics.export-pdf', $data);
        return $pdf->download($fileName . '.pdf');
    }

    /**
     * Calculate teacher statistics
     */
    private function calculateTeacherStats($teacherId, $year, $month)
    {
        $totalDays = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->count();

        $presentDays = $totalDays; // Since we're getting records that exist

        $lateArrivals = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('arrival_status', 'late')
            ->count();

        $earlyDepartures = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('departure_status', 'early_exit')
            ->count();

        $halfDays = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->where('departure_status', 'half_day')
            ->count();

        $avgWorkingHours = TeacherBiometricRecord::where('teacher_id', $teacherId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->avg('calculated_duration');

        $attendancePercentage = $totalDays > 0 ? ($presentDays / $totalDays) * 100 : 0;

        // Calculate punctuality score (inverse of lateness)
        $punctualityScore = $totalDays > 0 ? max(0, 100 - (($lateArrivals / $totalDays) * 100)) : 100;
        
        // Calculate discipline score (combines punctuality and early departures)
        $disciplineScore = max(0, $punctualityScore - (($earlyDepartures / max(1, $totalDays)) * 50));

        return [
            'total_days' => $totalDays,
            'present_days' => $presentDays,
            'late_arrivals' => $lateArrivals,
            'early_departures' => $earlyDepartures,
            'half_days' => $halfDays,
            'avg_working_hours' => round($avgWorkingHours ?? 0, 2),
            'attendance_percentage' => round($attendancePercentage, 2),
            'punctuality_score' => round($punctualityScore, 1),
            'discipline_score' => round($disciplineScore, 1),
        ];
    }

    /**
     * Get recent notifications for the teacher
     */
    private function getRecentNotifications($teacherId, $limit = 5)
    {
        // Get teacher to access notifications
        $teacher = Teacher::find($teacherId);
        
        if (!$teacher) {
            return [];
        }

        // Get recent biometric notifications from the teacher's user
        $notifications = $teacher->getUnreadBiometricNotifications()->limit($limit)->get();

        $formattedNotifications = [];
        
        foreach ($notifications as $notification) {
            $data = $notification->data;
            $formattedNotifications[] = [
                'title' => $this->getNotificationTitle($data['type']),
                'message' => $data['message'],
                'timestamp' => $notification->created_at->diffForHumans(),
                'type' => $this->getNotificationType($data['type']),
                'link' => route('teacher.biometric.dashboard'),
                'read_at' => $notification->read_at,
                'id' => $notification->id
            ];
        }

        return $formattedNotifications;
    }
    
    private function getNotificationTitle($type)
    {
        switch ($type) {
            case 'late_arrival':
                return 'Late Arrival Alert';
            case 'early_departure':
                return 'Early Departure Alert';
            case 'half_day':
                return 'Half Day Notification';
            case 'on_time':
                return 'Great Job!';
            default:
                return 'Biometric Notification';
        }
    }
    
    private function getNotificationType($type)
    {
        switch ($type) {
            case 'late_arrival':
                return 'warning';
            case 'early_departure':
                return 'info';
            case 'half_day':
                return 'danger';
            case 'on_time':
                return 'success';
            default:
                return 'info';
        }
    }

}