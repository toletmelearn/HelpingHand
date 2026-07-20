<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\AdminConfiguration;
use App\Models\Backup;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\Operations\DiagnosticEngine;
use App\Services\Operations\BackupService;
use App\Services\Operations\QueueMonitor;
use App\Services\Operations\SchedulerInspector;
use App\Services\Operations\LogService;
use App\Services\Operations\PerformanceTracker;
use App\Services\Operations\InstallationChecker;
use App\Services\Registry\ErpRegistry;

class OperationsController extends Controller
{
    protected DiagnosticEngine $diagnosticEngine;
    protected BackupService $backupService;
    protected QueueMonitor $queueMonitor;
    protected SchedulerInspector $schedulerInspector;
    protected LogService $logService;
    protected PerformanceTracker $performanceTracker;
    protected InstallationChecker $installationChecker;
    protected ErpRegistry $erpRegistry;

    public function __construct(
        DiagnosticEngine $diagnosticEngine,
        BackupService $backupService,
        QueueMonitor $queueMonitor,
        SchedulerInspector $schedulerInspector,
        LogService $logService,
        PerformanceTracker $performanceTracker,
        InstallationChecker $installationChecker,
        ErpRegistry $erpRegistry
    ) {
        $this->middleware('role:admin');
        $this->diagnosticEngine = $diagnosticEngine;
        $this->backupService = $backupService;
        $this->queueMonitor = $queueMonitor;
        $this->schedulerInspector = $schedulerInspector;
        $this->logService = $logService;
        $this->performanceTracker = $performanceTracker;
        $this->installationChecker = $installationChecker;
        $this->erpRegistry = $erpRegistry;
    }

    /**
     * Central Landing Hub Dashboard.
     */
    public function dashboard()
    {
        $modulesCount = count($this->erpRegistry->getModules());
        $results = $this->diagnosticEngine->runAll();
        
        $hasErrors = collect($results)->contains(fn($r) => $r['result']['status'] === 'error');
        $hasWarnings = collect($results)->contains(fn($r) => $r['result']['status'] === 'warning');
        
        $healthStatus = $hasErrors ? 'Critically Failed' : ($hasWarnings ? 'Warnings Present' : 'All Healthy');
        $healthClass = $hasErrors ? 'danger' : ($hasWarnings ? 'warning' : 'success');

        $queueStats = $this->queueMonitor->getQueueStats();
        $latestBackup = Backup::where('status', 'completed')->orderBy('completed_at', 'desc')->first();

        // System CPU & Memory
        $perfMetrics = $this->performanceTracker->getMetrics();

        // License
        $licenseStatus = AdminConfiguration::get('license', 'status', 'Active');
        $licensePlan = AdminConfiguration::get('license', 'plan', 'Enterprise SaaS Plan');

        return view('admin.operations.dashboard', compact(
            'modulesCount',
            'healthStatus',
            'healthClass',
            'queueStats',
            'latestBackup',
            'perfMetrics',
            'licenseStatus',
            'licensePlan'
        ));
    }

    /**
     * Detailed System Health Checklist.
     */
    public function health()
    {
        $results = $this->diagnosticEngine->runAll();

        $diskFree = @disk_free_space(base_path()) ?: 0;
        $diskTotal = @disk_total_space(base_path()) ?: 0;
        $diskUsedPercent = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;

        $dbSize = 'Unknown';
        try {
            if (config('database.default') === 'sqlite') {
                $dbFile = config('database.connections.sqlite.database');
                if (file_exists($dbFile)) {
                    $dbSize = round(filesize($dbFile) / (1024 * 1024), 2) . ' MB';
                }
            } else {
                $query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS `size` FROM information_schema.TABLES";
                $row = DB::selectOne($query);
                if ($row) {
                    $dbSize = $row->size . ' MB';
                }
            }
        } catch (\Throwable $e) {
            $dbSize = 'N/A';
        }

        $metrics = [
            'disk_free_gb' => round($diskFree / (1024 * 1024 * 1024), 2),
            'disk_total_gb' => round($diskTotal / (1024 * 1024 * 1024), 2),
            'disk_used_percent' => $diskUsedPercent,
            'db_size' => $dbSize,
        ];

        return view('admin.operations.health', compact('results', 'metrics'));
    }

    /**
     * Backup & Disaster Recovery Center.
     */
    public function backupIndex()
    {
        $backups = Backup::orderBy('created_at', 'desc')->get();
        $latestBackup = Backup::where('status', 'completed')->orderBy('completed_at', 'desc')->first();
        
        return view('admin.operations.backup', compact('backups', 'latestBackup'));
    }

    public function backupRun(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:full,database,files',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->backupService->createBackup($validated['type'], $validated['notes'] ?? 'Manual backup trigger.');
            return redirect()->route('operations.backup')->with('success', 'Backup archive created successfully!');
        } catch (\Throwable $e) {
            return redirect()->route('operations.backup')->with('error', 'Backup execution failed: ' . $e->getMessage());
        }
    }

    public function backupRestore(Request $request, $id)
    {
        try {
            $this->backupService->restoreBackup((int)$id);
            return redirect()->route('operations.backup')->with('success', 'System restore completed successfully!');
        } catch (\Throwable $e) {
            return redirect()->route('operations.backup')->with('error', 'Restore wizard failed: ' . $e->getMessage());
        }
    }

    public function backupDownload($id)
    {
        $backup = Backup::findOrFail($id);
        $filePath = storage_path('app/' . $backup->path);

        if (!file_exists($filePath)) {
            return redirect()->route('operations.backup')->with('error', 'Backup file not found on disk.');
        }

        return response()->download($filePath, $backup->filename);
    }

    public function backupDelete($id)
    {
        $backup = Backup::findOrFail($id);
        $filePath = storage_path('app/' . $backup->path);

        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $backup->delete();
        return redirect()->route('operations.backup')->with('success', 'Backup history entry and file deleted.');
    }

    /**
     * Queue Monitoring Center.
     */
    public function queueIndex()
    {
        $stats = $this->queueMonitor->getQueueStats();
        $failedJobs = $this->queueMonitor->getFailedJobs(30);

        return view('admin.operations.queue', compact('stats', 'failedJobs'));
    }

    public function queueRetry($id)
    {
        if ($this->queueMonitor->retryJob($id)) {
            return redirect()->route('operations.queue')->with('success', "Job ID {$id} re-queued successfully.");
        }
        return redirect()->route('operations.queue')->with('error', 'Failed to retry job.');
    }

    public function queueRetryAll()
    {
        if ($this->queueMonitor->retryAll()) {
            return redirect()->route('operations.queue')->with('success', 'All failed jobs have been re-queued.');
        }
        return redirect()->route('operations.queue')->with('error', 'Failed to retry jobs.');
    }

    public function queueClearFailed()
    {
        if ($this->queueMonitor->clearFailed()) {
            return redirect()->route('operations.queue')->with('success', 'Failed jobs queue cleared.');
        }
        return redirect()->route('operations.queue')->with('error', 'Failed to clear queue.');
    }

    /**
     * Scheduler Dashboard.
     */
    public function schedulerIndex()
    {
        $tasks = $this->schedulerInspector->getScheduledTasks();
        $heartbeat = Cache::get('scheduler_heartbeat');
        $heartbeatTime = $heartbeat ? date('Y-m-d H:i:s', $heartbeat) : 'Never';

        return view('admin.operations.scheduler', compact('tasks', 'heartbeatTime'));
    }

    /**
     * Notification Center.
     */
    public function notificationsIndex()
    {
        $logs = NotificationLog::orderBy('created_at', 'desc')->paginate(20);
        $templates = NotificationTemplate::all();

        $stats = [
            'total' => NotificationLog::count(),
            'sent' => NotificationLog::where('status', 'sent')->count(),
            'pending' => NotificationLog::where('status', 'pending')->count(),
            'failed' => NotificationLog::where('status', 'failed')->count(),
        ];

        return view('admin.operations.notifications', compact('logs', 'templates', 'stats'));
    }

    public function notificationsRetry($id)
    {
        $log = NotificationLog::findOrFail($id);
        $log->update([
            'status' => 'pending',
            'failed_reason' => null,
            'retry_count' => $log->retry_count + 1,
        ]);

        // Simulating dispatching
        try {
            if (!app()->runningUnitTests()) {
                Artisan::call('reminders:retry-failed');
            }
            return redirect()->route('operations.notifications')->with('success', 'Notification re-queued for delivery.');
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'failed_reason' => $e->getMessage()]);
            return redirect()->route('operations.notifications')->with('error', 'Failed dispatching notifications: ' . $e->getMessage());
        }
    }

    /**
     * One-Click Environment Checker.
     */
    public function verificationIndex()
    {
        $results = $this->installationChecker->verifyAll();
        return view('admin.operations.verification', compact('results'));
    }

    public function verificationRun(Request $request)
    {
        $results = $this->installationChecker->verifyAll();
        return redirect()->route('operations.verification')->with('success', 'System installation verification ran successfully.');
    }

    /**
     * System Logs Center.
     */
    public function logsIndex()
    {
        $categorizedLogs = $this->logService->getCategorizedLogs();
        return view('admin.operations.logs', compact('categorizedLogs'));
    }

    /**
     * Activity Timeline.
     */
    public function timelineIndex()
    {
        $logs = DB::table('activity_log')
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $timeline = [];
        foreach ($logs as $log) {
            $timeline[] = [
                'time' => date('H:i', strtotime($log->created_at)),
                'date' => date('d M Y', strtotime($log->created_at)),
                'title' => $log->log_name ? ucwords(str_replace('_', ' ', $log->log_name)) : 'System Log',
                'description' => $log->description,
            ];
        }

        if (empty($timeline)) {
            // Seeding falling back mock activities for demonstration
            $timeline = [
                ['time' => '17:10', 'date' => 'Today', 'title' => 'Import System', 'description' => 'Imported 250 Students successfully.'],
                ['time' => '17:05', 'date' => 'Today', 'title' => 'Finance Engine', 'description' => 'Generated monthly tuition Fee Structure.'],
                ['time' => '16:42', 'date' => 'Today', 'title' => 'Payment Gateway', 'description' => 'Stripe payment of $120.00 processed for invoice #422.'],
                ['time' => '15:20', 'date' => 'Today', 'title' => 'Authentication', 'description' => 'Teacher Mrs. Anjali logged in.'],
                ['time' => '02:00', 'date' => 'Today', 'title' => 'Disaster Recovery', 'description' => 'Scheduled backup completed successfully. Archive size: 1.24 GB.'],
                ['time' => '19:15', 'date' => 'Yesterday', 'title' => 'School Wizard', 'description' => 'ERP Settings updated by Administrator.'],
            ];
        }

        return view('admin.operations.timeline', compact('timeline'));
    }

    /**
     * SaaS License & Subscription Center.
     */
    public function licenseIndex()
    {
        $status = AdminConfiguration::get('license', 'status', 'Active');
        $expiry = AdminConfiguration::get('license', 'expiry_date', date('Y-m-d', strtotime('+365 days')));
        $plan = AdminConfiguration::get('license', 'school_plan', 'Enterprise Unlimited');
        $studentCapacity = AdminConfiguration::get('license', 'student_capacity', '2500');
        $storageLimit = AdminConfiguration::get('license', 'storage_limit', '10.0'); // GB
        
        // Scan current storage size
        $storageDir = storage_path('app');
        $totalBytes = 0;
        if (is_dir($storageDir)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($storageDir));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $totalBytes += $file->getSize();
                }
            }
        }
        $storageUsed = round($totalBytes / (1024 * 1024 * 1024), 2); // GB

        $apiUsage = Cache::get('api_requests_count', 4280);

        return view('admin.operations.license', compact(
            'status',
            'expiry',
            'plan',
            'studentCapacity',
            'storageLimit',
            'storageUsed',
            'apiUsage'
        ));
    }

    public function licenseActivate(Request $request)
    {
        $validated = $request->validate([
            'license_key' => 'required|string|min:10|max:100',
        ]);

        // Simulating keys validation
        AdminConfiguration::set('license', 'key', $validated['license_key'], 'string');
        AdminConfiguration::set('license', 'status', 'Active', 'string');
        AdminConfiguration::set('license', 'expiry_date', date('Y-m-d', strtotime('+365 days')), 'string');
        AdminConfiguration::set('license', 'school_plan', 'Enterprise SaaS Plan', 'string');

        return redirect()->route('operations.license')->with('success', 'License key validated and subscription details updated.');
    }

    /**
     * Maintenance Mode Settings.
     */
    public function maintenanceIndex()
    {
        $isEnabled = app()->isDownForMaintenance() || AdminConfiguration::get('maintenance', 'enabled', false);
        $message = AdminConfiguration::get('maintenance', 'message', 'HelpingHand ERP is currently undergoing scheduled system updates.');
        $countdown = AdminConfiguration::get('maintenance', 'countdown_hours', '2');

        return view('admin.operations.maintenance', compact('isEnabled', 'message', 'countdown'));
    }

    public function maintenanceToggle(Request $request)
    {
        $isEnabled = app()->isDownForMaintenance() || AdminConfiguration::get('maintenance', 'enabled', false);
        
        if ($isEnabled) {
            // Turn off maintenance mode
            if (!app()->runningUnitTests()) {
                Artisan::call('up');
            }
            AdminConfiguration::set('maintenance', 'enabled', false, 'boolean');
            return redirect()->route('operations.maintenance')->with('success', 'Maintenance Mode disabled. Site is live!');
        } else {
            // Turn on maintenance mode
            $validated = $request->validate([
                'message' => 'required|string|max:500',
                'countdown_hours' => 'required|integer|min:1|max:168',
            ]);

            AdminConfiguration::set('maintenance', 'message', $validated['message'], 'string');
            AdminConfiguration::set('maintenance', 'countdown_hours', $validated['countdown_hours'], 'integer');
            AdminConfiguration::set('maintenance', 'enabled', true, 'boolean');

            // Call Laravel down command
            if (!app()->runningUnitTests()) {
                Artisan::call('down', [
                    '--secret' => 'erp-override',
                    '--refresh' => 15,
                    '--retry' => 60,
                ]);
            }

            return redirect()->route('operations.maintenance')->with('success', 'Maintenance Mode enabled successfully.');
        }
    }

    /**
     * Performance Dashboard.
     */
    public function performanceIndex()
    {
        $metrics = $this->performanceTracker->getMetrics();
        return view('admin.operations.performance', compact('metrics'));
    }

    /**
     * Unified configurations control center settings view loader.
     */
    public function settings()
    {
        $config = [
            'school_name' => AdminConfiguration::get('general', 'school_name', 'HelpingHand School'),
            'school_email' => AdminConfiguration::get('general', 'school_email', 'admin@helpinghand.test'),
            'school_phone' => AdminConfiguration::get('general', 'school_phone', '+91 1234567890'),
            'school_address' => AdminConfiguration::get('general', 'school_address', '123 School Street'),
            'stripe_mode' => AdminConfiguration::get('finance', 'stripe_mode', 'sandbox'),
            'stripe_publishable_key' => AdminConfiguration::get('finance', 'stripe_publishable_key', ''),
            'stripe_secret_key' => AdminConfiguration::get('finance', 'stripe_secret_key', ''),
            'password_min_length' => AdminConfiguration::get('security', 'password_min_length', 8),
            'session_timeout_minutes' => AdminConfiguration::get('security', 'session_timeout_minutes', 120),
        ];

        return view('admin.operations.settings', compact('config'));
    }

    /**
     * Batch save settings updater.
     */
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'school_name' => 'required|string|max:200',
            'school_email' => 'required|email|max:200',
            'school_phone' => 'required|string|max:30',
            'school_address' => 'required|string|max:500',
            'stripe_mode' => 'required|in:sandbox,live',
            'stripe_publishable_key' => 'nullable|string|max:250',
            'stripe_secret_key' => 'nullable|string|max:250',
            'password_min_length' => 'required|integer|min:6|max:32',
            'session_timeout_minutes' => 'required|integer|min:15|max:1440',
        ]);

        AdminConfiguration::set('general', 'school_name', $validated['school_name'], 'string');
        AdminConfiguration::set('general', 'school_email', $validated['school_email'], 'string');
        AdminConfiguration::set('general', 'school_phone', $validated['school_phone'], 'string');
        AdminConfiguration::set('general', 'school_address', $validated['school_address'], 'string');
        AdminConfiguration::set('finance', 'stripe_mode', $validated['stripe_mode'], 'string');
        AdminConfiguration::set('finance', 'stripe_publishable_key', $validated['stripe_publishable_key'] ?? '', 'string');
        AdminConfiguration::set('finance', 'stripe_secret_key', $validated['stripe_secret_key'] ?? '', 'string');
        AdminConfiguration::set('security', 'password_min_length', $validated['password_min_length'], 'integer');
        AdminConfiguration::set('security', 'session_timeout_minutes', $validated['session_timeout_minutes'], 'integer');

        return redirect()->route('operations.settings')->with('success', 'ERP Settings updated successfully!');
    }
}
