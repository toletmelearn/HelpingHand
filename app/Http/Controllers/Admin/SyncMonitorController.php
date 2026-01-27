<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\BiometricSyncService;

class SyncMonitorController extends Controller
{
    protected BiometricSyncService $syncService;
    
    public function __construct(BiometricSyncService $syncService)
    {
        $this->syncService = $syncService;
    }
    
    public function index()
    {
        $statistics = $this->syncService->getSyncStatistics();
        return view('admin.sync-monitor.index', compact('statistics'));
    }
    
    public function statistics()
    {
        $days = request()->get('days', 7);
        $statistics = $this->syncService->getSyncStatistics($days);
        return response()->json($statistics);
    }
    
    public function syncAll()
    {
        $results = $this->syncService->syncAllDevices(request()->get('force', false));
        return response()->json(['success' => true, 'results' => $results]);
    }
}
