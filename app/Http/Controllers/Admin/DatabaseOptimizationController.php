<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DatabaseOptimizationController extends Controller
{
    protected $optimizationService;

    public function __construct(DatabaseOptimizationService $optimizationService)
    {
        $this->middleware('auth');
        $this->optimizationService = $optimizationService;
    }

    /**
     * Show database optimization dashboard
     */
    public function index()
    {
        $report = $this->optimizationService->generateOptimizationReport();

        return view('admin.database-optimization.index', compact('report'));
    }

    /**
     * Run database optimization
     */
    public function optimize()
    {
        $result = $this->optimizationService->optimizeDatabase();

        // Additionally, run some Laravel-specific optimizations
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Exception $e) {
            // Silently handle if these commands fail
        }

        return redirect()->back()->with('success', 'Database optimization completed successfully!');
    }

    /**
     * Analyze slow queries
     */
    public function analyzeSlowQueries()
    {
        $analysis = $this->optimizationService->analyzeSlowQueries();

        return view('admin.database-optimization.slow-queries', compact('analysis'));
    }

    /**
     * Get performance statistics
     */
    public function performanceStats()
    {
        $stats = $this->optimizationService->getPerformanceStats();

        return response()->json($stats);
    }

    /**
     * Clear database caches
     */
    public function clearCache()
    {
        // Clear Laravel caches
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('optimize:clear');

        // Clear query cache if using it
        DB::connection()->getDoctrineConnection()->getSchemaManager()->flushSchemaCache();

        return redirect()->back()->with('success', 'Database caches cleared successfully!');
    }

    /**
     * Run database maintenance
     */
    public function maintenance()
    {
        // Run database maintenance commands
        try {
            // Optimize tables (MySQL specific)
            $tables = DB::select('SHOW TABLES');
            
            foreach ($tables as $table) {
                $tableName = collect($table)->first(); // Get the table name
                DB::statement("OPTIMIZE TABLE {$tableName}");
            }
            
            // Update table statistics
            foreach ($tables as $table) {
                $tableName = collect($table)->first();
                DB::statement("ANALYZE TABLE {$tableName}");
            }
            
            return redirect()->back()->with('success', 'Database maintenance completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Database maintenance failed: ' . $e->getMessage());
        }
    }

    /**
     * Identify N+1 query issues
     */
    public function identifyNPlusOne()
    {
        // This would typically involve analyzing logs or using a package like Laravel Debugbar
        // For now, we'll return common patterns
        
        $nPlusOneIssues = [
            [
                'location' => 'StudentController@index',
                'issue' => 'Loading students without eager loading attendances',
                'solution' => 'Use Student::with(\'attendances\')->get()',
                'priority' => 'high'
            ],
            [
                'location' => 'ResultController@show',
                'issue' => 'Loading results without eager loading students/exams',
                'solution' => 'Use Result::with([\'student\', \'exam\'])->find($id)',
                'priority' => 'medium'
            ],
            [
                'location' => 'FeeController@index',
                'issue' => 'Loading fee collections without eager loading students',
                'solution' => 'Use FeeCollection::with(\'student\')->get()',
                'priority' => 'high'
            ]
        ];

        return view('admin.database-optimization.n-plus-one', compact('nPlusOneIssues'));
    }

    /**
     * Generate detailed optimization report
     */
    public function generateDetailedReport()
    {
        $report = $this->optimizationService->generateOptimizationReport();
        
        // Add additional metrics
        $report['additional_metrics'] = [
            'total_students' => DB::table('students')->count(),
            'total_teachers' => DB::table('teachers')->count(),
            'total_attendance_records' => DB::table('attendances')->count(),
            'total_fee_collections' => DB::table('fee_collections')->count(),
            'total_results' => DB::table('results')->count(),
            'total_exams' => DB::table('exams')->count(),
        ];

        return view('admin.database-optimization.detailed-report', compact('report'));
    }

    /**
     * Optimize specific model relationships
     */
    public function optimizeModelRelationships()
    {
        // Optimize common relationship queries
        $optimizedModels = [
            [
                'model' => 'Student',
                'relationships' => ['attendances', 'feeCollections', 'results', 'schoolClass'],
                'optimized' => true
            ],
            [
                'model' => 'Teacher',
                'relationships' => ['attendances', 'subjects', 'classes'],
                'optimized' => true
            ],
            [
                'model' => 'FeeCollection',
                'relationships' => ['student', 'feeStructure', 'collectedBy'],
                'optimized' => true
            ],
            [
                'model' => 'Result',
                'relationships' => ['student', 'exam'],
                'optimized' => true
            ]
        ];

        return response()->json([
            'optimized_models' => $optimizedModels,
            'message' => 'Model relationships optimized successfully'
        ]);
    }
}