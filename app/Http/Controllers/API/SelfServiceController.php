<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SelfServiceToken;
use App\Models\TeacherBiometricRecord;
use App\Models\PerformanceScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class SelfServiceController extends Controller
{
    /**
     * Authenticate teacher using self-service token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function authenticate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token format'
            ], 400);
        }

        $token = SelfServiceToken::findByToken($request->input('token'));

        if (!$token || !$token->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        // Mark token as used
        $token->useToken($request->ip());

        return response()->json([
            'success' => true,
            'message' => 'Authentication successful',
            'teacher' => [
                'id' => $token->teacher->id,
                'name' => $token->teacher->name,
                'email' => $token->teacher->email,
                'remaining_days' => $token->remaining_days
            ],
            'permissions' => $token->permissions,
            'expires_at' => $token->expires_at->toISOString()
        ]);
    }

    /**
     * Get teacher's attendance records
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAttendance(Request $request)
    {
        $token = $this->validateToken($request);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$token->can('view_attendance')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->subMonths(3);
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now();
        $limit = $request->input('limit', 50);

        $records = TeacherBiometricRecord::where('teacher_id', $token->teacher_id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->with(['biometricDevice:id,name'])
            ->orderBy('date', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'records' => $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'date' => $record->date->format('Y-m-d'),
                    'first_in_time' => $record->first_in_time ? $record->first_in_time->format('H:i:s') : null,
                    'last_out_time' => $record->last_out_time ? $record->last_out_time->format('H:i:s') : null,
                    'calculated_duration' => $record->calculated_duration,
                    'arrival_status' => $record->arrival_status,
                    'departure_status' => $record->departure_status,
                    'late_minutes' => $record->late_minutes,
                    'early_departure_minutes' => $record->early_departure_minutes,
                    'device_name' => $record->biometricDevice ? $record->biometricDevice->name : null,
                    'is_synced' => $record->is_synced
                ];
            }),
            'summary' => [
                'total_records' => $records->count(),
                'present_days' => $records->whereNotNull('first_in_time')->count(),
                'late_arrivals' => $records->where('arrival_status', 'late')->count(),
                'early_departures' => $records->where('departure_status', 'early_exit')->count(),
                'average_working_hours' => round($records->avg('calculated_duration') ?? 0, 2)
            ]
        ]);
    }

    /**
     * Get teacher's monthly summary
     *
     * @param Request $request
     * @param string|null $month Year-month format (YYYY-MM)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMonthlySummary(Request $request, ?string $month = null)
    {
        $token = $this->validateToken($request);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$token->can('view_reports')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        $targetDate = $month ? Carbon::createFromFormat('Y-m', $month) : Carbon::now();
        $startDate = $targetDate->copy()->startOfMonth();
        $endDate = $targetDate->copy()->endOfMonth();

        // Get performance score if exists
        $performanceScore = PerformanceScore::forTeacher($token->teacher_id)
            ->forPeriod($startDate, $endDate)
            ->monthly()
            ->first();

        // Get raw records for calculation
        $records = TeacherBiometricRecord::where('teacher_id', $token->teacher_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        $summary = [
            'month' => $targetDate->format('F Y'),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'statistics' => [
                'total_days' => $endDate->diffInDays($startDate) + 1,
                'present_days' => $records->whereNotNull('first_in_time')->count(),
                'absent_days' => $records->whereNull('first_in_time')->count(),
                'late_arrivals' => $records->where('arrival_status', 'late')->count(),
                'early_departures' => $records->where('departure_status', 'early_exit')->count(),
                'half_days' => $records->where('departure_status', 'half_day')->count(),
                'average_working_hours' => round($records->avg('calculated_duration') ?? 0, 2),
                'attendance_percentage' => $records->count() > 0 ? 
                    round(($records->whereNotNull('first_in_time')->count() / $records->count()) * 100, 2) : 0
            ]
        ];

        if ($performanceScore) {
            $summary['performance'] = [
                'punctuality_score' => $performanceScore->punctuality_score,
                'discipline_rating' => $performanceScore->discipline_rating,
                'consistency_index' => $performanceScore->consistency_index,
                'overall_score' => $performanceScore->overall_score,
                'performance_grade' => $performanceScore->performance_grade,
                'detailed_metrics' => $performanceScore->detailed_metrics
            ];
        }

        return response()->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    /**
     * Get teacher's performance trends
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPerformanceTrends(Request $request)
    {
        $token = $this->validateToken($request);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$token->can('view_reports')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        $months = $request->get('months', 6);
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths($months - 1)->startOfMonth();

        $scores = PerformanceScore::forTeacher($token->teacher_id)
            ->forPeriod($startDate, $endDate)
            ->monthly()
            ->orderBy('period_start')
            ->get();

        $trends = $scores->map(function ($score) {
            return [
                'month' => $score->period_start->format('M Y'),
                'period_start' => $score->period_start->format('Y-m-d'),
                'period_end' => $score->period_end->format('Y-m-d'),
                'punctuality_score' => $score->punctuality_score,
                'discipline_rating' => $score->discipline_rating,
                'consistency_index' => $score->consistency_index,
                'overall_score' => $score->overall_score,
                'performance_grade' => $score->performance_grade,
                'attendance_percentage' => $score->attendance_percentage
            ];
        });

        return response()->json([
            'success' => true,
            'trends' => $trends,
            'period' => [
                'months_shown' => $months,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ]
        ]);
    }

    /**
     * Download attendance report (returns data for client-side PDF generation)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downloadReport(Request $request)
    {
        $token = $this->validateToken($request);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!$token->can('view_reports')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'format' => 'nullable|in:pdf,excel,csv'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $fromDate = $request->input('from_date') ? Carbon::parse($request->input('from_date')) : Carbon::now()->subMonths(1);
        $toDate = $request->input('to_date') ? Carbon::parse($request->input('to_date')) : Carbon::now();
        $format = $request->input('format', 'pdf');

        $records = TeacherBiometricRecord::where('teacher_id', $token->teacher_id)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->get();

        // Prepare data for export
        $exportData = [
            'teacher_info' => [
                'name' => $token->teacher->name,
                'email' => $token->teacher->email,
                'report_period' => [
                    'from' => $fromDate->format('Y-m-d'),
                    'to' => $toDate->format('Y-m-d')
                ]
            ],
            'records' => $records->map(function ($record) {
                return [
                    'date' => $record->date->format('Y-m-d'),
                    'day_of_week' => $record->date->format('l'),
                    'first_in' => $record->first_in_time ? $record->first_in_time->format('H:i:s') : 'Absent',
                    'last_out' => $record->last_out_time ? $record->last_out_time->format('H:i:s') : '-',
                    'working_hours' => $record->calculated_duration ?? 0,
                    'arrival_status' => ucfirst(str_replace('_', ' ', $record->arrival_status ?? 'absent')),
                    'departure_status' => ucfirst(str_replace('_', ' ', $record->departure_status ?? '-')),
                    'late_minutes' => $record->late_minutes ?? 0,
                    'early_departure_minutes' => $record->early_departure_minutes ?? 0
                ];
            }),
            'summary' => [
                'total_days' => $records->count(),
                'present_days' => $records->whereNotNull('first_in_time')->count(),
                'absent_days' => $records->whereNull('first_in_time')->count(),
                'late_arrivals' => $records->where('arrival_status', 'late')->count(),
                'early_departures' => $records->where('departure_status', 'early_exit')->count(),
                'average_working_hours' => round($records->avg('calculated_duration') ?? 0, 2),
                'attendance_percentage' => $records->count() > 0 ? 
                    round(($records->whereNotNull('first_in_time')->count() / $records->count()) * 100, 2) : 0
            ]
        ];

        return response()->json([
            'success' => true,
            'format' => $format,
            'data' => $exportData,
            'message' => 'Report data prepared for ' . strtoupper($format) . ' export'
        ]);
    }

    /**
     * Validate authentication token
     *
     * @param Request $request
     * @return SelfServiceToken|null
     */
    protected function validateToken(Request $request): ?SelfServiceToken
    {
        $tokenString = $request->header('Authorization');
        
        if (!$tokenString || !str_starts_with($tokenString, 'Bearer ')) {
            return null;
        }

        $tokenString = substr($tokenString, 7); // Remove 'Bearer ' prefix
        $token = SelfServiceToken::findByToken($tokenString);

        if (!$token || !$token->isValid()) {
            return null;
        }

        // Mark token as used
        $token->useToken($request->ip());

        return $token;
    }
}