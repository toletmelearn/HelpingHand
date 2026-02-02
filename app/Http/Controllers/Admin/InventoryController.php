<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class InventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display the main inventory dashboard.
     */
    public function index()
    {
        $totalAssets = Asset::count();
        $activeAssets = Asset::where('is_active', true)->count();
        $totalCategories = AssetCategory::count();
        $lowStockAssets = Asset::whereColumn('available_quantity', '<', 'quantity')
                                  ->where('available_quantity', '<=', 5)
                                  ->count();
        
        $assetsByCategory = Asset::join('asset_categories', 'assets.category_id', '=', 'asset_categories.id')
                               ->select('asset_categories.name as category_name', DB::raw('COUNT(*) as count'))
                               ->whereNull('assets.deleted_at')
                               ->groupBy('asset_categories.name')
                               ->get();
        
        $expiringWarranties = Asset::whereNotNull('warranty_expiry_date')
                                ->where('warranty_expiry_date', '<=', now()->addDays(30))
                                ->orderBy('warranty_expiry_date', 'asc')
                                ->limit(10)
                                ->get();
        
        $repairAssets = Asset::where('status', 'under_repair')->count();
        
        return view('admin.inventory.dashboard', compact(
            'totalAssets',
            'activeAssets',
            'totalCategories',
            'lowStockAssets',
            'assetsByCategory',
            'expiringWarranties',
            'repairAssets'
        ));
    }
    
    /**
     * Display asset master page.
     */
    public function assetMaster()
    {
        return redirect()->route('admin.assets.index');
    }
    
    /**
     * Display furniture management page.
     */
    public function furnitureManagement(Request $request)
    {
        $assets = Asset::with(['category'])
                    ->whereHas('category', function($query) {
                        $query->where('type', 'furniture');
                    })
                    ->orderBy('name');

        // Apply filters
        if ($request->filled('location')) {
            $assets->where('location', $request->location);
        }

        if ($request->filled('condition')) {
            $assets->where('condition', $request->condition);
        }

        if ($request->filled('status')) {
            $assets->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $assets->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $assets = $assets->paginate(15);

        return view('admin.inventory.furniture', compact('assets'));
    }
    
    /**
     * Display lab equipment management page.
     */
    public function labEquipmentManagement(Request $request)
    {
        $assets = Asset::with(['category'])
                    ->whereHas('category', function($query) {
                        $query->where('type', 'lab_equipment');
                    })
                    ->orderBy('name');

        // Apply filters
        if ($request->filled('location')) {
            $assets->where('location', $request->location);
        }

        if ($request->filled('condition')) {
            $assets->where('condition', $request->condition);
        }

        if ($request->filled('status')) {
            $assets->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $assets->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $assets = $assets->paginate(15);

        return view('admin.inventory.lab-equipment', compact('assets'));
    }
    
    /**
     * Display electronics management page.
     */
    public function electronicsManagement(Request $request)
    {
        $assets = Asset::with(['category'])
                    ->whereHas('category', function($query) {
                        $query->where('type', 'electronics');
                    })
                    ->orderBy('name');

        // Apply filters
        if ($request->filled('location')) {
            $assets->where('location', $request->location);
        }

        if ($request->filled('condition')) {
            $assets->where('condition', $request->condition);
        }

        if ($request->filled('status')) {
            $assets->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $assets->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $assets = $assets->paginate(15);

        return view('admin.inventory.electronics', compact('assets'));
    }
    
    /**
     * Display inventory reports page.
     */
    public function reports(Request $request)
    {
        $totalAssets = Asset::count();
        $activeAssets = Asset::where('status', 'active')->count();
        $repairAssets = Asset::where('status', 'under_repair')->count();
        $lowStockItems = Asset::whereColumn('available_quantity', '<', 'quantity')
                              ->where('available_quantity', '<=', 5)->count();
        
        $categories = AssetCategory::all();

        return view('admin.inventory.reports', compact(
            'totalAssets',
            'activeAssets',
            'repairAssets',
            'lowStockItems',
            'categories'
        ));
    }
    
    /**
     * Display inventory audit logs page.
     */
    public function auditLogs(Request $request)
    {
        $query = Activity::where('log_name', 'inventory')
                         ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('ip_address', 'LIKE', '%' . $request->search . '%');
            });
        }

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->filled('asset_id')) {
            $query->where('subject_id', $request->asset_id)
                  ->where('subject_type', 'App\\\\Models\\\\Asset');
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(15);
        $users = User::all();
        $assets = Asset::all();

        return view('admin.inventory.audit-logs', compact('logs', 'users', 'assets'));
    }
    
    /**
     * Export audit logs to CSV
     */
    public function exportAuditLogs(Request $request)
    {
        $query = Activity::where('log_name', 'inventory')
                         ->orderBy('created_at', 'desc');

        // Apply same filters as auditLogs
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('ip_address', 'LIKE', '%' . $request->search . '%');
            });
        }

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->filled('asset_id')) {
            $query->where('subject_id', $request->asset_id)
                  ->where('subject_type', 'App\\Models\\Asset');
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->get();
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="inventory_audit_logs_' . now()->format('Y-m-d_H-i-s') . '.csv"',
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Date', 'User', 'Event', 'Description', 'IP Address']);
            
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->causer?->name ?? 'System',
                    $log->event,
                    $log->description,
                    $log->properties['ip'] ?? $log->ip_address ?? 'N/A'
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}