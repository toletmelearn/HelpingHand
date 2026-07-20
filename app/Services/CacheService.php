<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CacheService
{
    // Cache duration constants (in seconds)
    const CACHE_SHORT = 300;      // 5 minutes
    const CACHE_MEDIUM = 1800;    // 30 minutes
    const CACHE_LONG = 3600;      // 1 hour
    const CACHE_DAY = 86400;      // 24 hours
    const CACHE_WEEK = 604800;    // 7 days

    /**
     * Cache student data
     */
    public function cacheStudent($studentId, $data, $duration = self::CACHE_MEDIUM)
    {
        $key = "student:{$studentId}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Get cached student data
     */
    public function getStudent($studentId)
    {
        $key = "student:{$studentId}";
        return Cache::get($key);
    }

    /**
     * Cache teacher data
     */
    public function cacheTeacher($teacherId, $data, $duration = self::CACHE_MEDIUM)
    {
        $key = "teacher:{$teacherId}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Get cached teacher data
     */
    public function getTeacher($teacherId)
    {
        $key = "teacher:{$teacherId}";
        return Cache::get($key);
    }

    /**
     * Cache class data
     */
    public function cacheClass($classId, $data, $duration = self::CACHE_LONG)
    {
        $key = "class:{$classId}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache attendance summary
     */
    public function cacheAttendanceSummary($studentId, $month, $year, $data, $duration = self::CACHE_MEDIUM)
    {
        $key = "attendance:student:{$studentId}:month:{$month}:year:{$year}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache fee summary
     */
    public function cacheFeeSummary($studentId, $data, $duration = self::CACHE_SHORT)
    {
        $key = "fee:student:{$studentId}:summary";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache exam results
     */
    public function cacheExamResults($studentId, $examId, $data, $duration = self::CACHE_DAY)
    {
        $key = "results:student:{$studentId}:exam:{$examId}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache dashboard data
     */
    public function cacheDashboard($userId, $role, $data, $duration = self::CACHE_SHORT)
    {
        $key = "dashboard:{$role}:{$userId}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache report data
     */
    public function cacheReport($reportKey, $data, $duration = self::CACHE_MEDIUM)
    {
        $key = "report:{$reportKey}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Cache analytics data
     */
    public function cacheAnalytics($analyticsKey, $data, $duration = self::CACHE_LONG)
    {
        $key = "analytics:{$analyticsKey}";
        return Cache::put($key, $data, $duration);
    }

    /**
     * Invalidate student cache
     */
    public function invalidateStudent($studentId)
    {
        $keys = [
            "student:{$studentId}",
            "attendance:student:{$studentId}:*",
            "fee:student:{$studentId}:*",
            "results:student:{$studentId}:*"
        ];
        
        foreach ($keys as $key) {
            if (strpos($key, '*') !== false) {
                // Use Redis pattern matching
                $this->forgetByPattern($key);
            } else {
                Cache::forget($key);
            }
        }
    }

    /**
     * Invalidate teacher cache
     */
    public function invalidateTeacher($teacherId)
    {
        Cache::forget("teacher:{$teacherId}");
        Cache::forget("dashboard:teacher:{$teacherId}");
    }

    /**
     * Invalidate class cache
     */
    public function invalidateClass($classId)
    {
        Cache::forget("class:{$classId}");
    }

    /**
     * Invalidate all dashboard caches
     */
    public function invalidateDashboards()
    {
        $this->forgetByPattern("dashboard:*");
    }

    /**
     * Invalidate all report caches
     */
    public function invalidateReports()
    {
        $this->forgetByPattern("report:*");
    }

    /**
     * Invalidate all analytics caches
     */
    public function invalidateAnalytics()
    {
        $this->forgetByPattern("analytics:*");
    }

    /**
     * Remember (get from cache or execute callback)
     */
    public function remember($key, $duration, $callback)
    {
        return Cache::remember($key, $duration, $callback);
    }

    /**
     * Remember forever (until manually invalidated)
     */
    public function rememberForever($key, $callback)
    {
        return Cache::rememberForever($key, $callback);
    }

    /**
     * Clear all cache
     */
    public function clearAll()
    {
        return Cache::flush();
    }

    /**
     * Forget cache by pattern (Redis only)
     */
    private function forgetByPattern($pattern)
    {
        if (config('cache.default') === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys($pattern);
            
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    Cache::forget($key);
                }
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats()
    {
        $driver = config('cache.default');
        
        $stats = [
            'driver' => $driver,
            'prefix' => config('cache.prefix'),
        ];
        
        if ($driver === 'redis') {
            $redis = Cache::getRedis();
            $info = $redis->info();
            
            $stats['redis'] = [
                'version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 'unknown',
                'total_keys' => $redis->dbSize(),
            ];
        }
        
        return $stats;
    }
}
