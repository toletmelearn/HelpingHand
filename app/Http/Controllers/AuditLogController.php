<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderBy('performed_at', 'desc');

        // Apply filters
        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->filled('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $startDate = $request->start_date ?: '1970-01-01';
            $endDate = $request->end_date ?: now()->format('Y-m-d');
            $query->forDateRange($startDate, $endDate);
        }

        $logs = $query->paginate(20);

        // Get all users for filter dropdown
        $users = User::all();

        return view('admin.audit-logs.index', compact('logs', 'users'));
    }

    public function show(AuditLog $log)
    {
        return view('admin.audit-logs.show', compact('log'));
    }

    public function destroy(AuditLog $log)
    {
        $log->delete();

        return redirect()->route('admin.audit-logs.index')
                         ->with('success', 'Audit log entry deleted successfully.');
    }

    // Method to get audit logs for a specific model
    public function getModelLogs(Request $request, $modelType, $modelId)
    {
        $query = AuditLog::where('model_type', $modelType)
                         ->where('model_id', $modelId)
                         ->orderBy('performed_at', 'desc');

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        $logs = $query->paginate(20);

        return view('admin.audit-logs.model-logs', compact('logs', 'modelType', 'modelId'));
    }
}