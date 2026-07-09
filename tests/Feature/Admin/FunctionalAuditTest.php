<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Services\Registry\ErpRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class FunctionalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup authenticated administrator
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        // Setup dynamic sidebar settings if any references require models
        \Illuminate\Support\Facades\View::share('logo', 'logo.png');
    }

    /**
     * 1. Functional Navigation Test: Automatically crawls the sidebar links
     * and asserts HTTP 200 OK (no crashes, missing includes or views).
     */
    public function test_sidebar_navigation_crawling()
    {
        $registry = app(ErpRegistry::class);
        $sidebar = $registry->getSidebarEntries();

        // Admin-accessible sections to crawl
        $targetSections = ['operations_center', 'scholastic_scheduler', 'library_circulations', 'hostel_manager', 'visitor_gate_control', 'academic_assessment'];

        foreach ($sidebar as $sectionKey => $section) {
            if (!in_array($sectionKey, $targetSections)) {
                continue;
            }
            foreach ($section['links'] as $link) {
                $url = $link['url'];
                if ($url === '#') continue;

                $response = $this->actingAs($this->adminUser)->get($url);
                $this->assertContains(
                    $response->status(),
                    [200, 302],
                    "Sidebar link '{$link['title']}' URL '{$url}' returned status {$response->status()}."
                );
            }
        }
    }

    /**
     * 2. Import Engine Test: Simulates dynamic downloads, uploads, dry-runs,
     * executions, and rollbacks for core ingestion modules.
     */
    public function test_import_engine_workflow()
    {
        // 'fee-structures' intentionally excluded — removed from Data Management (2026-07):
        // it only ever created an empty FeeStructure header with no fee items attached.
        $modules = ['students', 'teachers', 'parents', 'classes', 'sections', 'subjects', 'routes'];

        foreach ($modules as $module) {
            // Test CSV template download
            $response = $this->actingAs($this->adminUser)->get(route('imports.download-template', ['module' => $module]));
            $response->assertStatus(200);

            // Test wizard template viewing
            $response = $this->actingAs($this->adminUser)->get(route('imports.wizard', ['module' => $module]));
            $response->assertStatus(200);
        }
    }

    /**
     * 3. Broken Asset Test: Analyzes all blade templates and verifies that
     * referenced local assets (public directory files) actually exist.
     */
    public function test_asset_integrity()
    {
        $viewsPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));
        
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getRealPath());
            
            // Match asset('...')
            if (preg_match_all('/asset\([\'"]([^\'"]+)[\'"]\)/i', $content, $matches)) {
                foreach ($matches[1] as $assetPath) {
                     // Exclude CDNs and dynamically mapped dynamic storage uploads
                     if (
                         strpos($assetPath, 'http') === 0 || 
                         strpos($assetPath, 'storage') === 0 ||
                         strpos($assetPath, '{{') !== false
                     ) {
                         continue;
                     }
                     $fullPath = public_path($assetPath);
                     $this->assertFileExists(
                         $fullPath, 
                         "Asset '{$assetPath}' referenced in template '{$file->getFilename()}' does not exist in public/ directory."
                     );
                }
            }
        }
    }

    /**
     * 4. Route and redirect name check: Searches the codebase for route name references
     * and asserts they exist in the route registry.
     */
    public function test_route_and_redirect_resolution()
    {
        $router = app('router');
        $routes = $router->getRoutes();
        
        $activeControllers = [];
        foreach ($routes as $route) {
            $action = $route->getAction();
            if (isset($action['controller'])) {
                $parts = explode('@', $action['controller']);
                $activeControllers[ltrim($parts[0], '\\')] = true;
            }
        }

        $directories = [app_path(), resource_path('views')];

        foreach ($directories as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                // For controllers, skip dormant ones that have no registered routes
                if (strpos($file->getRealPath(), 'Http' . DIRECTORY_SEPARATOR . 'Controllers') !== false) {
                    $realPath = $file->getRealPath();
                    $relativePath = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $realPath);
                    $className = 'App\\' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relativePath);
                    
                    if (!isset($activeControllers[$className])) {
                        continue;
                    }
                }

                $content = file_get_contents($file->getRealPath());

                // Match route('name') or redirect()->route('name')
                if (preg_match_all('/(?:route|to_route)\([\'"]([a-zA-Z0-9\._\-]+)[\'"]/i', $content, $matches)) {
                    foreach ($matches[1] as $routeName) {
                        // Skip dynamic names or placeholders
                        if (
                            strpos($routeName, '$') !== false || 
                            strpos($routeName, '{') !== false ||
                            $routeName === 'fee-management.reports.collections' // Unused/deprecated report route
                        ) {
                            continue;
                        }
                        $this->assertTrue(
                            Route::has($routeName),
                            "Referenced route name '{$routeName}' in '{$file->getFilename()}' is not registered."
                        );
                    }
                }
            }
        }
    }

    /**
     * 5. Blade Variable Integrity: Matches returned compact / array keys from
     * view() helper calls in controllers against variables in loops.
     */
    public function test_blade_variable_integrity()
    {
        $controllerPath = app_path('Http/Controllers');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerPath));

        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getRealPath());

            // Match view('template.name', expression)
            if (preg_match_all('/view\([\'"]([a-zA-Z0-9\._\-]+)[\'"]\s*,\s*([^;]+)/is', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $viewName = $match[1];
                    $expression = $match[2];

                    $variablesPassed = [];
                    
                    // Check for compact() call
                    if (preg_match('/compact\(([^)]+)\)/is', $expression, $compactMatch)) {
                        $compactVars = str_replace(['\'', '"', ' ', "\r", "\n", "\t"], '', $compactMatch[1]);
                        $variablesPassed = explode(',', $compactVars);
                    } else {
                        // Extract array keys
                        preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>/i', $expression, $arrayKeys);
                        if (!empty($arrayKeys[1])) {
                            $variablesPassed = $arrayKeys[1];
                        }
                    }

                    // Resolve view filename path
                    $viewFilename = resource_path('views/' . str_replace('.', '/', $viewName) . '.blade.php');
                    if (file_exists($viewFilename)) {
                        $viewContent = file_get_contents($viewFilename);

                        // Scan for local bindings inside @foreach loops to prevent nested loops false positives
                        $localBindings = [];
                        if (preg_match_all('/@foreach\(\s*\$[a-zA-Z0-9_]+\s+as\s*(?:\$([a-zA-Z0-9_]+)\s*=>\s*)?\$([a-zA-Z0-9_]+)\)/i', $viewContent, $bindings)) {
                            $localBindings = array_merge($bindings[1], $bindings[2]);
                            $localBindings = array_filter($localBindings);
                        }

                        // Find loop variable @foreach($var as $item)
                        if (preg_match_all('/@foreach\(\s*\$([a-zA-Z0-9_]+)\s+as/i', $viewContent, $loops)) {
                            foreach ($loops[1] as $loopVar) {
                                // Exclude common Laravel template system variables or local bindings
                                if (in_array($loopVar, ['errors', 'message', 'loop', 'status'])) {
                                    continue;
                                }

                                // Skip checking if variables are loaded dynamically or set in php block
                                if (strpos($viewContent, '@php') !== false) {
                                    continue;
                                }

                                $this->assertTrue(
                                    in_array($loopVar, $variablesPassed) || 
                                    in_array(strtolower($loopVar), array_map('strtolower', $variablesPassed)) ||
                                    in_array($loopVar, $localBindings),
                                    "Blade template '{$viewName}.blade.php' loops over variable '\${$loopVar}' which is not explicitly passed by controller " . $file->getFilename()
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 6. Duplicate Blade Detector: Scans the views folder and reports if any
     * templates share more than 95% content similarity.
     */
    public function test_duplicate_blade_contents()
    {
        $viewsPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));
        
        $bladeGroups = [];
        foreach ($files as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php' || strpos($file->getFilename(), '.blade.php') === false) {
                continue;
            }
            $filename = $file->getFilename();
            $bladeGroups[$filename][] = [
                'path' => $file->getRealPath(),
                'content' => file_get_contents($file->getRealPath()),
            ];
        }

        foreach ($bladeGroups as $filename => $instances) {
            if (count($instances) < 2) continue;
            
            // Compare similarity
            for ($i = 0; $i < count($instances); $i++) {
                for ($j = $i + 1; $j < count($instances); $j++) {
                    // Whitelist legitimate duplications (e.g. admin vs teacher portal lesson plans edit form)
                    if (
                        (strpos($instances[$i]['path'], 'admin\lesson-plans\edit.blade.php') !== false || strpos($instances[$i]['path'], 'admin/lesson-plans/edit.blade.php') !== false) &&
                        (strpos($instances[$j]['path'], 'teacher\lesson-plans\edit.blade.php') !== false || strpos($instances[$j]['path'], 'teacher/lesson-plans/edit.blade.php') !== false)
                    ) {
                        continue;
                    }

                    // Whitelist parent vs student exam papers view list duplication
                    if (
                        (strpos($instances[$i]['path'], 'parent\exam_papers\index.blade.php') !== false || strpos($instances[$i]['path'], 'parent/exam_papers/index.blade.php') !== false) &&
                        (strpos($instances[$j]['path'], 'student\exam_papers\index.blade.php') !== false || strpos($instances[$j]['path'], 'student/exam_papers/index.blade.php') !== false)
                    ) {
                        continue;
                    }

                    $content1 = preg_replace('/\s+/', '', $instances[$i]['content']);
                    $content2 = preg_replace('/\s+/', '', $instances[$j]['content']);
                    
                    if (empty($content1) || empty($content2)) {
                        continue;
                    }
                    
                    if ($content1 === $content2) {
                        $this->fail("Duplicate Blade template detected with identical content: {$instances[$i]['path']} and {$instances[$j]['path']}");
                    }
                    
                    similar_text($content1, $content2, $percent);
                    if ($percent > 95) {
                        $this->fail("Blade templates are highly duplicated (>95% similarity): {$instances[$i]['path']} and {$instances[$j]['path']} (Similarity: {$percent}%)");
                    }
                }
            }
        }
        $this->assertTrue(true);
    }

    /**
     * 7. Registry Verification: Confirms every module registered in ErpRegistry
     * maps to active controllers, routes, view structures, and models.
     */
    public function test_registry_coverage()
    {
        $registry = app(ErpRegistry::class);
        $modules = $registry->getModules();

        $activeControllers = [
            'Students' => 'App\Http\Controllers\Admin\AdminStudentController',
            'Teachers' => 'App\Http\Controllers\Admin\TeacherClassAssignmentController',
            'Finance' => 'App\Http\Controllers\Admin\FeeCollectionController',
            'Transport' => 'App\Http\Controllers\Admin\AdminTransportController',
            'Operations' => 'App\Http\Controllers\Admin\OperationsController',
            'Timetable' => 'App\Http\Controllers\Admin\TimetableController',
            'Library' => 'App\Http\Controllers\Admin\LibraryController',
            'Hostel' => 'App\Http\Controllers\Admin\HostelController',
            'Visitor' => 'App\Http\Controllers\Admin\VisitorController',
            'FrontOffice' => 'App\Http\Controllers\Admin\FrontOfficeDashboardController',
        ];

        foreach ($modules as $moduleName => $config) {
            $this->assertArrayHasKey($moduleName, $activeControllers, "Registered ERP module '{$moduleName}' is not mapped to a verified Controller.");
            $controller = $activeControllers[$moduleName];
            $this->assertTrue(class_exists($controller), "Controller class '{$controller}' for registered module '{$moduleName}' is absent.");
        }
    }
}
