<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use App\Models\GateEntry;
use App\Models\Appointment;
use App\Models\CallLog;
use App\Models\Courier;
use App\Models\GatePass;
use App\Models\StudentDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FrontOfficeDashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today()->toDateString();

        // 1. Gather Metrics
        $totalEnquiries = AdmissionEnquiry::count();
        $admittedEnquiries = AdmissionEnquiry::where('status', 'admitted')->count();
        $conversionRate = $totalEnquiries > 0 ? round(($admittedEnquiries / $totalEnquiries) * 100, 1) : 0;

        $stats = [
            'today_visitors' => GateEntry::whereDate('check_in', $today)->count(),
            'currently_inside' => GateEntry::whereNull('check_out')->count(),
            'today_enquiries' => AdmissionEnquiry::whereDate('created_at', $today)->count(),
            'conversion_rate' => $conversionRate,
            'today_calls' => CallLog::whereDate('created_at', $today)->count(),
            'pending_calls' => CallLog::where('status', 'follow_up_required')->count(),
            'pending_follow_ups' => AdmissionEnquiry::where('status', 'follow_up')->whereDate('follow_up_date', '<=', $today)->count(),
            'appointments_today' => Appointment::whereDate('scheduled_date', $today)->count(),
            'pending_documents' => StudentDocument::where('is_verified', false)->count(),
            'courier_pending' => Courier::where('status', 'pending')->count(),
            'gate_passes_today' => GatePass::whereDate('request_date', $today)->count(),
        ];

        // 2. Fetch Lists for widgets
        $recentVisitors = GateEntry::with('host')->latest()->take(5)->get();
        $recentEnquiries = AdmissionEnquiry::latest()->take(5)->get();
        $upcomingAppointments = Appointment::with(['teacher', 'guardian'])
            ->whereDate('scheduled_date', '>=', $today)
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->take(5)
            ->get();
            
        $recentGatePasses = GatePass::with(['student', 'requester'])->latest()->take(5)->get();

        return view('admin.front-office.dashboard', compact(
            'stats',
            'recentVisitors',
            'recentEnquiries',
            'upcomingAppointments',
            'recentGatePasses'
        ));
    }
}
