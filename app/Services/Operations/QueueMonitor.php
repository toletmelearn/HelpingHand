<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class QueueMonitor
{
    /**
     * Get queue operational metrics.
     */
    public function getQueueStats(): array
    {
        $pending = 0;
        $failed = 0;

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // tables might not exist yet
        }

        // Detect worker status via heartbeat cache
        $workerPulse = Cache::get('queue_worker_heartbeat');
        $workerStatus = 'Idle';
        if ($workerPulse) {
            $diff = time() - $workerPulse;
            $workerStatus = $diff < 60 ? 'Running' : 'Offline';
        }

        // Get PHP/System memory usage
        $memoryUsage = round(memory_get_usage(true) / (1024 * 1024), 2) . ' MB';
        $memoryLimit = ini_get('memory_limit');

        return [
            'pending_jobs' => $pending,
            'failed_jobs' => $failed,
            'worker_status' => $workerStatus,
            'memory_usage' => $memoryUsage,
            'memory_limit' => $memoryLimit,
            'average_runtime' => Cache::get('queue_avg_runtime', 1.84) . 's', // in seconds
        ];
    }

    /**
     * Get recent failed jobs list.
     */
    public function getFailedJobs(int $limit = 20): array
    {
        try {
            return DB::table('failed_jobs')
                ->orderBy('failed_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($job) {
                    // Extract payload command name
                    $payload = json_decode($job->payload, true);
                    $displayName = $job->id;
                    if (isset($payload['displayName'])) {
                        $displayName = $payload['displayName'];
                    } elseif (isset($payload['data']['commandName'])) {
                        $displayName = $payload['data']['commandName'];
                    }
                    
                    return [
                        'id' => $job->id,
                        'connection' => $job->connection,
                        'queue' => $job->queue,
                        'name' => $displayName,
                        'exception' => substr($job->exception, 0, 250) . '...',
                        'failed_at' => $job->failed_at,
                    ];
                })
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Retry a single failed job.
     */
    public function retryJob($id): bool
    {
        $code = Artisan::call('queue:retry', ['id' => $id]);
        return $code === 0;
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAll(): bool
    {
        $code = Artisan::call('queue:retry', ['id' => ['all']]);
        return $code === 0;
    }

    /**
     * Clear all failed jobs.
     */
    public function clearFailed(): bool
    {
        try {
            DB::table('failed_jobs')->delete();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
