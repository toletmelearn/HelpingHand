<?php

namespace App\Services\Operations;

use App\Contracts\Operations\CheckInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DiagnosticEngine
{
    protected array $checks = [];

    public function __construct()
    {
        // Register default system checks
        $this->registerDefaultChecks();

        // Load dynamic checks from ErpRegistry
        try {
            $registry = app(\App\Services\Registry\ErpRegistry::class);
            foreach ($registry->getDiagnosticChecks() as $check) {
                $this->registerCheck($check);
            }
        } catch (\Throwable $e) {
            // ignore during early app boot phase
        }
    }

    public function registerCheck(CheckInterface $check): void
    {
        $this->checks[] = $check;
    }

    protected function registerDefaultChecks(): void
    {
        // 1. Database Connectivity Check
        $this->registerCheck(new class implements CheckInterface {
            public function getName(): string { return 'Database Connectivity'; }
            public function getCategory(): string { return 'Core Services'; }
            public function run(): array {
                try {
                    $start = microtime(true);
                    DB::connection()->getPdo();
                    $duration = round((microtime(true) - $start) * 1000, 2);
                    
                    // Count total tables
                    $tables = count(Schema::getTables());
                    
                    return [
                        'status' => 'success',
                        'message' => "Successfully connected to DB (MariaDB/SQLite).",
                        'meta' => [
                            'Response Time' => "{$duration}ms",
                            'Total Tables' => $tables,
                            'Driver' => DB::connection()->getDriverName()
                        ]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'status' => 'error',
                        'message' => "Database connection failure: " . $e->getMessage(),
                        'meta' => []
                    ];
                }
            }
        });

        // 2. Storage Permissions Check
        $this->registerCheck(new class implements CheckInterface {
            public function getName(): string { return 'Writable Storage Paths'; }
            public function getCategory(): string { return 'File System'; }
            public function run(): array {
                $paths = [
                    'Storage Directory' => storage_path(),
                    'Framework Cache' => storage_path('framework/cache'),
                    'Framework Views' => storage_path('framework/views'),
                    'Bootstrap Cache' => base_path('bootstrap/cache')
                ];
                
                $errors = [];
                foreach ($paths as $name => $path) {
                    if (!is_dir($path)) {
                        @mkdir($path, 0775, true);
                    }
                    if (!is_writable($path)) {
                        $errors[] = "{$name} is not writable";
                    }
                }
                
                if (!empty($errors)) {
                    return [
                        'status' => 'error',
                        'message' => "Storage permissions issues: " . implode(', ', $errors),
                        'meta' => $paths
                    ];
                }

                // Check disk space
                $freeSpace = @disk_free_space(base_path()) ?: 0;
                $totalSpace = @disk_total_space(base_path()) ?: 0;
                $usedPercent = $totalSpace > 0 ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 1) : 0;
                
                return [
                    'status' => 'success',
                    'message' => "All bootstrap and storage directories are fully writable.",
                    'meta' => [
                        'Free Space' => round($freeSpace / (1024 * 1024 * 1024), 2) . " GB",
                        'Total Space' => round($totalSpace / (1024 * 1024 * 1024), 2) . " GB",
                        'Disk Utilization' => "{$usedPercent}%"
                    ]
                ];
            }
        });

        // 3. Cache Driver Check
        $this->registerCheck(new class implements CheckInterface {
            public function getName(): string { return 'Cache Services'; }
            public function getCategory(): string { return 'Infrastructure'; }
            public function run(): array {
                try {
                    $testKey = 'diagnose_test_key_' . rand(1, 1000);
                    Cache::put($testKey, 'helpinghand_ok', 10);
                    $val = Cache::get($testKey);
                    Cache::forget($testKey);
                    
                    if ($val === 'helpinghand_ok') {
                        return [
                            'status' => 'success',
                            'message' => 'Cache driver stores records successfully.',
                            'meta' => [
                                'Default Driver' => config('cache.default')
                            ]
                        ];
                    }
                    return [
                        'status' => 'warning',
                        'message' => 'Cache read/write test mismatch.',
                        'meta' => []
                    ];
                } catch (\Throwable $e) {
                    return [
                        'status' => 'error',
                        'message' => 'Cache store failure: ' . $e->getMessage(),
                        'meta' => []
                    ];
                }
            }
        });

        // 4. Background Queue Worker Check
        $this->registerCheck(new class implements CheckInterface {
            public function getName(): string { return 'Background Job Queues'; }
            public function getCategory(): string { return 'Infrastructure'; }
            public function run(): array {
                try {
                    $hasJobsTable = Schema::hasTable('jobs');
                    $hasFailedTable = Schema::hasTable('failed_jobs');
                    
                    if (!$hasJobsTable || !$hasFailedTable) {
                        return [
                            'status' => 'warning',
                            'message' => 'Queue migrations database tables are missing.',
                            'meta' => []
                        ];
                    }
                    
                    $pending = DB::table('jobs')->count();
                    $failed = DB::table('failed_jobs')->count();
                    
                    return [
                        'status' => $failed > 0 ? 'warning' : 'success',
                        'message' => $failed > 0 
                            ? "Queue backlogs check: {$pending} pending jobs and {$failed} failed jobs found."
                            : "Queue status healthy: 0 failed backlog entries.",
                        'meta' => [
                            'Pending Backlog' => $pending,
                            'Failed Jobs' => $failed,
                            'Driver' => config('queue.default')
                        ]
                    ];
                } catch (\Throwable $e) {
                    return [
                        'status' => 'error',
                        'message' => 'Queue status check query error: ' . $e->getMessage(),
                        'meta' => []
                    ];
                }
            }
        });

        // 5. Scheduler Pulse Check
        $this->registerCheck(new class implements CheckInterface {
            public function getName(): string { return 'Task Scheduler Heartbeat'; }
            public function getCategory(): string { return 'Core Services'; }
            public function run(): array {
                $lastPulse = Cache::get('scheduler_heartbeat');
                
                if (!$lastPulse) {
                    return [
                        'status' => 'warning',
                        'message' => 'Task scheduler cron heartbeat not detected. Ensure "php artisan schedule:run" is configured in system crontab.',
                        'meta' => []
                    ];
                }
                
                $diffMinutes = round((time() - $lastPulse) / 60, 1);
                
                if ($diffMinutes > 15) {
                    return [
                        'status' => 'error',
                        'message' => "Task scheduler is running but stale (last pulse updated {$diffMinutes} minutes ago). Check worker cron schedules.",
                        'meta' => [
                            'Last Run Time' => date('Y-m-d H:i:s', $lastPulse)
                        ]
                    ];
                }

                return [
                    'status' => 'success',
                    'message' => "Task scheduler is healthy (last pulse was {$diffMinutes} minutes ago).",
                    'meta' => [
                        'Last Run Time' => date('Y-m-d H:i:s', $lastPulse)
                    ]
                ];
            }
        });
    }

    /**
     * Run all diagnostic checks.
     */
    public function runAll(): array
    {
        $results = [];
        foreach ($this->checks as $check) {
            $results[] = [
                'name' => $check->getName(),
                'category' => $check->getCategory(),
                'result' => $check->run()
            ];
        }
        return $results;
    }
}
