<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CacheManagementController extends Controller
{
    protected $cacheService;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Show cache management dashboard
     */
    public function index()
    {
        $stats = $this->cacheService->getStats();
        
        return view('admin.cache.index', compact('stats'));
    }

    /**
     * Clear all cache
     */
    public function clearAll()
    {
        $this->cacheService->clearAll();
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        
        return back()->with('success', 'All caches cleared successfully');
    }

    /**
     * Clear specific cache
     */
    public function clearSpecific(Request $request)
    {
        $type = $request->input('type');
        
        switch ($type) {
            case 'config':
                Artisan::call('config:clear');
                break;
            case 'route':
                Artisan::call('route:clear');
                break;
            case 'view':
                Artisan::call('view:clear');
                break;
            case 'dashboards':
                $this->cacheService->invalidateDashboards();
                break;
            case 'reports':
                $this->cacheService->invalidateReports();
                break;
            case 'analytics':
                $this->cacheService->invalidateAnalytics();
                break;
            default:
                return back()->with('error', 'Invalid cache type');
        }
        
        return back()->with('success', ucfirst($type) . ' cache cleared successfully');
    }

    /**
     * Optimize application (cache config, routes)
     */
    public function optimize()
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        
        return back()->with('success', 'Application optimized successfully');
    }
}
