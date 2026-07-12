<?php
 
namespace Tests\Feature\Admin;
 
use Tests\TestCase;
use App\Services\Registry\ErpRegistry;
use App\Contracts\Operations\CheckInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
 
class ArchitectureIntegrityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Controller Integrity: Ensures duplicate controllers are either subclassed / deprecated
     * or retired correctly, and that they follow docblock conventions.
     */
    public function test_controller_integrity()
    {
        $adminExamPaperController = 'App\Http\Controllers\Admin\AdminExamPaperController';
        $examPaperController = 'App\Http\Controllers\Admin\ExamPaperController';
        $rootExamPaperController = 'App\Http\Controllers\ExamPaperController';
 
        $adminHostelController = 'App\Http\Controllers\Admin\AdminHostelController';
        $hostelController = 'App\Http\Controllers\Admin\HostelController';
 
        // Canonical controllers MUST exist
        $this->assertTrue(class_exists($examPaperController), "Canonical ExamPaperController must exist.");
        $this->assertTrue(class_exists($hostelController), "Canonical HostelController must exist.");
 
        // If deprecated controllers still exist, they must extend canonical ones and have @deprecated annotations
        if (class_exists($adminExamPaperController)) {
            $reflector = new \ReflectionClass($adminExamPaperController);
            $this->assertTrue(
                $reflector->isSubclassOf($examPaperController),
                "Deprecated AdminExamPaperController must subclass canonical ExamPaperController."
            );
            $docComment = $reflector->getDocComment();
            $this->assertStringContainsString('@deprecated', $docComment, "AdminExamPaperController must have @deprecated annotation.");
        }
 
        if (class_exists($adminHostelController)) {
            $reflector = new \ReflectionClass($adminHostelController);
            $this->assertTrue(
                $reflector->isSubclassOf($hostelController),
                "Deprecated AdminHostelController must subclass canonical HostelController."
            );
            $docComment = $reflector->getDocComment();
            $this->assertStringContainsString('@deprecated', $docComment, "AdminHostelController must have @deprecated annotation.");
        }
 
        if (class_exists($rootExamPaperController)) {
            $reflector = new \ReflectionClass($rootExamPaperController);
            $this->assertTrue(
                $reflector->isSubclassOf($examPaperController),
                "Deprecated root ExamPaperController must subclass canonical ExamPaperController."
            );
            $docComment = $reflector->getDocComment();
            $this->assertStringContainsString('@deprecated', $docComment, "Root ExamPaperController must have @deprecated annotation.");
        }

        // Draft/duplicate controllers that are deprecated
        $deprecatedDrafts = [
            'App\Http\Controllers\ParentController',
            'App\Http\Controllers\SectionController',
            'App\Http\Controllers\SubjectController',
            'App\Http\Controllers\ClassManagementController',
        ];
        foreach ($deprecatedDrafts as $class) {
            $this->assertTrue(class_exists($class), "Controller {$class} should exist (deprecated placeholder).");
            $reflector = new \ReflectionClass($class);
            $docComment = $reflector->getDocComment();
            $this->assertStringContainsString('@deprecated', $docComment, "Controller {$class} must be annotated with @deprecated.");
        }
    }
 
    /**
     * View Integrity: Ensure canonical Blade templates exist and obsolete view folders are absent.
     */
    public function test_view_integrity()
    {
        $canonicalExamPaperView = resource_path('views/admin/exam-papers/index.blade.php');
        $canonicalHostelView = resource_path('views/admin/hostels/dashboard.blade.php');
        $canonicalImportPreview = resource_path('views/admin/students/import-preview.blade.php');
 
        $this->assertFileExists($canonicalExamPaperView, "Canonical exam papers index template must exist.");
        $this->assertFileExists($canonicalHostelView, "Canonical hostels dashboard template must exist.");
        $this->assertFileExists($canonicalImportPreview, "Canonical student import-preview template must exist.");
 
        // Assert obsolete directories do not exist
        $obsoleteDirs = [
            resource_path('views/admin/exam_papers'),
            resource_path('views/admin/hostel'),
            resource_path('views/exam-papers'),
            resource_path('views/students'),
            resource_path('views/parents')
        ];
        foreach ($obsoleteDirs as $dir) {
            $this->assertFalse(is_dir($dir), "Obsolete Blade directory '{$dir}' still exists. Consolidate templates.");
        }
    }
 
    /**
     * Route Integrity: Assert no duplicate route names exist and all active exam-paper/hostel
     * admin routes point to canonical controllers.
     */
    public function test_route_integrity()
    {
        $routes = Route::getRoutes();
        $names = [];
 
        foreach ($routes as $route) {
            $name = $route->getName();
            if ($name) {
                $this->assertNotContains(
                    $name,
                    $names,
                    "Duplicate route name detected: {$name}"
                );
                $names[] = $name;
            }
 
            // Ensure no active routes point to deprecated controllers
            $action = $route->getActionName();
            if (strpos($action, 'App\Http\Controllers\Admin\AdminExamPaperController') !== false) {
                $this->fail("Route {$name} ({$route->uri()}) must not point to deprecated AdminExamPaperController.");
            }
            if (strpos($action, 'App\Http\Controllers\Admin\AdminHostelController') !== false) {
                $this->fail("Route {$name} ({$route->uri()}) must not point to deprecated AdminHostelController.");
            }
        }
    }
 
    /**
     * Registry Integrity: Ensure all registered modules in ErpRegistry exist.
     */
    public function test_registry_integrity()
    {
        $registry = app(ErpRegistry::class);
        $modules = $registry->getModules();
 
        $this->assertNotEmpty($modules, "ERP Registry should have registered modules.");
        $this->assertArrayHasKey('Hostel', $modules);
        $this->assertArrayHasKey('Finance', $modules);
    }
 
    /**
     * Sidebar Integrity: Verify every dynamic sidebar entry has a valid route.
     */
    public function test_sidebar_integrity()
    {
        $registry = app(ErpRegistry::class);
        $sidebar = $registry->getSidebarEntries();
 
        $this->assertNotEmpty($sidebar, "Sidebar dynamic entries must be registered.");
 
        foreach ($sidebar as $sectionKey => $section) {
            $this->assertNotEmpty($section['title'], "Sidebar section {$sectionKey} must have a title.");
            $this->assertNotEmpty($section['roles'], "Sidebar section {$sectionKey} must define allowed roles.");
 
            foreach ($section['links'] as $link) {
                $url = $link['url'];
                if ($url === '#') continue;
 
                // Validate URL resolves in Laravel routes
                try {
                    $request = Request::create($url, 'GET');
                    $resolvedRoute = Route::getRoutes()->match($request);
                    $this->assertNotNull($resolvedRoute, "Sidebar link '{$link['title']}' URL '{$url}' is unmapped.");
                } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
                    $this->fail("Sidebar link '{$link['title']}' URL '{$url}' is unmapped (NotFoundHttpException).");
                }
            }
        }
    }
 
    /**
     * Service Integrity: Check container resolution for registered services.
     */
    public function test_service_integrity()
    {
        $registry = app(ErpRegistry::class);
        $this->assertNotNull($registry);
        $this->assertInstanceOf(ErpRegistry::class, app(ErpRegistry::class));
    }
 
    /**
     * Import Integrity: Verify registered import definitions class structure.
     */
    public function test_import_integrity()
    {
        $registry = app(ErpRegistry::class);
        foreach ($registry->getImports() as $name => $class) {
            $this->assertTrue(class_exists($class), "Registered import class '{$class}' for '{$name}' does not exist.");
        }
    }
 
    /**
     * Operations & Diagnostic Integrity: Verify all registered diagnostic checks exist, implement CheckInterface, and run successfully.
     */
    public function test_operations_integrity()
    {
        $engine = app(\App\Services\Operations\DiagnosticEngine::class);
        $results = $engine->runAll();
        
        $this->assertNotEmpty($results, "Diagnostic engine must have default checks registered.");
        foreach ($results as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('result', $item);
            $this->assertArrayHasKey('status', $item['result']);
        }
 
        // Use reflection to access the protected checks list
        $reflector = new \ReflectionClass($engine);
        $checksProp = $reflector->getProperty('checks');
        $checksProp->setAccessible(true);
        $checks = $checksProp->getValue($engine);
 
        foreach ($checks as $check) {
            $this->assertInstanceOf(
                CheckInterface::class,
                $check,
                "Registered diagnostic check " . get_class($check) . " must implement CheckInterface."
            );
        }
    }
 
    /**
     * Permission Integrity: Verify sidebar sections have defined permission rules.
     */
    public function test_permission_integrity()
    {
        $registry = app(ErpRegistry::class);
        foreach ($registry->getSidebarEntries() as $sectionKey => $section) {
            $this->assertNotEmpty($section['roles'], "Every sidebar section must have a role restriction.");
            foreach ($section['roles'] as $role) {
                $this->assertIsString($role);
                $this->assertNotEmpty($role, "Role name restriction cannot be empty.");
            }
        }
    }
 
    /**
     * Namespace Standardization Audit: Verify all controllers follow standard folder-based namespace conventions.
     */
    public function test_namespace_standardization_integrity()
    {
        $controllerFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'))
        );
 
        foreach ($controllerFiles as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }
 
            $filepath = $file->getRealPath();
            $content = file_get_contents($filepath);
 
            if (preg_match('/namespace\s+([^;]+);/i', $content, $matches)) {
                $namespace = trim($matches[1]);
                $relativePath = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $file->getPath());
                $expectedNamespace = 'App\\' . str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
                
                // Standardize slashes to avoid OS directory separator mismatches (especially on Windows)
                $expectedNamespace = str_replace(['/', '\\'], '\\', $expectedNamespace);
                $namespace = str_replace(['/', '\\'], '\\', $namespace);
                
                $this->assertEquals(
                    strtolower($expectedNamespace),
                    strtolower($namespace),
                    "Namespace mismatch in file '{$file->getFilename()}'. Expected '{$expectedNamespace}', got '{$namespace}'."
                );
            }
        }
    }
 
    /**
     * Module Completeness Integrity: Ensures every registered ERP module has matching
     * Controller class, Views folder, Test suite, Route configurations, and Registry entries.
     */
    public function test_module_completeness_integrity()
    {
        $registry = app(ErpRegistry::class);
        $modules = $registry->getModules();
 
        $moduleMap = [
            'Students' => [
                'controller' => \App\Http\Controllers\Admin\AdminStudentController::class,
                'view_dir' => resource_path('views/admin/students'),
                'route_prefix' => 'admin/students',
                'test_file' => base_path('tests/Feature/Admin/StudentControllerTest.php'),
            ],
            'Teachers' => [
                'controller' => \App\Http\Controllers\Admin\TeacherClassAssignmentController::class,
                'view_dir' => resource_path('views/admin/teachers'),
                'route_prefix' => 'admin/teachers',
                'test_file' => base_path('tests/Feature/Admin/TeacherRouteAlignmentAndParentLoginTest.php'),
            ],
            'Finance' => [
                'controller' => \App\Http\Controllers\Admin\FeeCollectionController::class,
                'view_dir' => resource_path('views/admin/fees'),
                'route_prefix' => 'admin/fees',
                'test_file' => base_path('tests/Feature/Admin/FeeCollectionRegisterTest.php'),
            ],
            'Transport' => [
                'controller' => \App\Http\Controllers\Admin\AdminTransportController::class,
                'view_dir' => resource_path('views/admin/transport'),
                'route_prefix' => 'admin/transport',
                'test_file' => base_path('tests/Feature/Admin/ErpGapsFeatureTest.php'),
            ],
            'Operations' => [
                'controller' => \App\Http\Controllers\Admin\OperationsController::class,
                'view_dir' => resource_path('views/admin/operations'),
                'route_prefix' => 'operations', // Route prefix is operations, not admin/operations
                'test_file' => base_path('tests/Feature/Admin/OperationsCenterTest.php'),
            ],
            'Timetable' => [
                'controller' => \App\Http\Controllers\Admin\TimetableController::class,
                'view_dir' => resource_path('views/admin/timetable'),
                'route_prefix' => 'admin/timetable',
                'test_file' => base_path('tests/Feature/Admin/TimetableSchedulerTest.php'),
            ],
            'Library' => [
                'controller' => \App\Http\Controllers\Admin\LibraryController::class,
                'view_dir' => resource_path('views/admin/library'),
                'route_prefix' => 'admin/library',
                'test_file' => base_path('tests/Feature/Admin/LibraryOPACTest.php'),
            ],
            'Hostel' => [
                'controller' => \App\Http\Controllers\Admin\HostelController::class,
                'view_dir' => resource_path('views/admin/hostels'),
                'route_prefix' => 'admin/hostels',
                'test_file' => base_path('tests/Feature/Admin/HostelAllocationTest.php'),
            ],
            'Visitor' => [
                'controller' => \App\Http\Controllers\Admin\VisitorController::class,
                'view_dir' => resource_path('views/admin/visitor'),
                'route_prefix' => 'admin/visitor',
                'test_file' => base_path('tests/Feature/Admin/VisitorGateTest.php'),
            ],
        ];
 
        foreach ($moduleMap as $moduleName => $components) {
            $this->assertArrayHasKey($moduleName, $modules, "Module '{$moduleName}' must be registered in ErpRegistry.");
            $this->assertTrue($registry->isModuleEnabled($moduleName), "Module '{$moduleName}' must be enabled.");
            $this->assertTrue(class_exists($components['controller']), "Expected controller '{$components['controller']}' for '{$moduleName}' module does not exist.");
            $this->assertTrue(is_dir($components['view_dir']), "Expected views directory '{$components['view_dir']}' for '{$moduleName}' module does not exist.");
            $this->assertFileExists($components['test_file'], "Expected test suite '{$components['test_file']}' for '{$moduleName}' module does not exist.");
            
            // Check routes
            $routes = Route::getRoutes();
            $routeFound = false;
            foreach ($routes as $route) {
                if (strpos($route->uri(), $components['route_prefix']) === 0) {
                    $routeFound = true;
                    break;
                }
            }
            $this->assertTrue($routeFound, "Expected routes with prefix '{$components['route_prefix']}' for '{$moduleName}' module do not exist.");
        }
    }

    /**
     * CSV Template Download Integrity: verify that the wizard CSV template download endpoint
     * returns a valid stream containing exact column headers matching the ImportDefinition.
     */
    public function test_csv_template_download_integrity()
    {
        $adminRole = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $adminUser = \App\Models\User::factory()->create(['role' => 'admin']);
        $adminUser->roles()->attach($adminRole->id);

        $modules = [
            'students' => ['Name', 'Father Name', 'Mother Name', 'Date of Birth', 'Gender', 'Mobile', 'Class', 'Section', 'Aadhar Number', 'Roll Number', 'Religion', 'Caste', 'Blood Group', 'Address', 'Sibling Admission No'],
            'teachers' => ['Name', 'Email', 'Phone', 'Employee ID', 'Designation', 'Qualification', 'Gender', 'Date of Birth', 'Date of Joining', 'Salary', 'Address'],
            'parents' => ['Name', 'Phone', 'Email', 'Mobile', 'Admission Number'],
            'classes' => ['Name', 'Class Order', 'Description'],
            'sections' => ['Name', 'Capacity', 'Description'],
            'subjects' => ['Name', 'Code', 'Subject Type'],
            'fee-structures' => ['Class Name', 'Academic Year', 'Frequency', 'Status'],
            'routes' => ['Name', 'Start Point', 'End Point', 'Monthly Fare'],
        ];

        foreach ($modules as $module => $expectedHeaders) {
            $response = $this->actingAs($adminUser)->get(route('imports.download-template', ['module' => $module]));
            $response->assertStatus(200);
            $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $response->assertHeader('Content-Disposition', 'attachment; filename="' . $module . '-template.csv"');

            // Verify headers in CSV content
            $content = $response->streamedContent();
            $content = str_replace("\xEF\xBB\xBF", '', $content);
            $headers = str_getcsv(explode("\n", trim($content))[0]);

            $this->assertEquals($expectedHeaders, $headers, "CSV headers mismatch for module: {$module}");
        }
    }

    /**
     * Dead Code Integrity: Verify that unreferenced controllers, policies, and views are deleted.
     */
    public function test_dead_code_integrity()
    {
        $deletedFiles = [
            app_path('Http/Controllers/TeacherExperienceController.php'),
            app_path('Policies/ClassManagementPolicy.php'),
            resource_path('views/teachers/experience.blade.php'),
            resource_path('views/teachers/certificate.blade.php'),
            app_path('Http/Controllers/Admin/ClassAssignmentController.php'),
            app_path('Http/Controllers/Admin/ProfessionalFeeManagementController.php'),
            app_path('Services/ProfessionalFeeManagementService.php'),
            app_path('Models/FeeHead.php'),
            app_path('Models/FeeStructureDetail.php'),
            resource_path('views/admin/fee-management/dashboard.blade.php'),
            base_path('database/seeders/FeeHeadsSeeder.php'),
        ];

        foreach ($deletedFiles as $file) {
            $this->assertFileDoesNotExist($file, "Obsolete file '{$file}' should be deleted from the codebase.");
        }
    }
}
