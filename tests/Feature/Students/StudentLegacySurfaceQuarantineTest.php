<?php

namespace Tests\Feature\Students;

use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportExportController;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentLegacySurfaceQuarantineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_legacy_student_store_method_aborts_if_called_directly(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Legacy student store is disabled. Use admin student CRUD.');

        (new StudentController())->store(Request::create('/students', 'POST'));
    }

    public function test_legacy_student_update_method_aborts_if_called_directly(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Legacy student update is disabled. Use admin student CRUD.');

        (new StudentController())->update(Request::create('/students/1', 'PUT'), 1);
    }

    public function test_legacy_student_destroy_method_aborts_if_called_directly(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Legacy student delete is disabled. Use admin student CRUD.');

        (new StudentController())->destroy(1);
    }

    public function test_legacy_student_create_view_no_longer_posts_to_students_store(): void
    {
        $view = file_get_contents(resource_path('views/students/create.blade.php'));

        $this->assertStringContainsString('Legacy student create view disabled', $view);
        $this->assertStringContainsString("route('admin.students.create')", $view);
        $this->assertStringNotContainsString("route('students.store')", $view);
        $this->assertStringNotContainsString('<form', $view);
    }

    public function test_legacy_student_edit_view_no_longer_posts_to_students_update(): void
    {
        $view = file_get_contents(resource_path('views/students/edit.blade.php'));

        $this->assertStringContainsString('Legacy student edit view disabled', $view);
        $this->assertStringContainsString("route('admin.students.edit'", $view);
        $this->assertStringNotContainsString("route('students.update'", $view);
        $this->assertStringNotContainsString('<form', $view);
    }

    public function test_quarantined_legacy_view_backups_exist(): void
    {
        $createBackup = base_path('docs/project-autopsy/quarantined-code/students-create.blade.php.txt');
        $editBackup = base_path('docs/project-autopsy/quarantined-code/students-edit.blade.php.txt');

        $this->assertFileExists($createBackup);
        $this->assertFileExists($editBackup);
        $this->assertStringContainsString("route('students.store')", file_get_contents($createBackup));
        $this->assertStringContainsString("route('students.update'", file_get_contents($editBackup));
    }

    public function test_root_crud_routes_still_inactive(): void
    {
        $this->assertNoRootStudentControllerRoute('POST', 'students', 'store');
        $this->assertNoRootStudentControllerRoute('GET', 'students/{student}', 'show');
        $this->assertNoRootStudentControllerRoute('PUT', 'students/{student}', 'update');
        $this->assertNoRootStudentControllerRoute('PATCH', 'students/{student}', 'update');
        $this->assertNoRootStudentControllerRoute('DELETE', 'students/{student}', 'destroy');
    }

    public function test_import_export_routes_still_point_to_student_import_export_controller(): void
    {
        $this->assertRouteAction('students.export.csv', 'students/export/csv', 'GET', 'exportCsv');
        $this->assertRouteAction('students.import.csv.preview', 'students/import/csv/preview', 'POST', 'previewImportCsv');
        $this->assertRouteAction('students.import.csv.apply', 'students/import/csv/apply', 'POST', 'applyImportCsv');
        $this->assertRouteAction('students.import.csv', 'students/import/csv', 'POST', 'importCsv');
    }

    private function assertRouteAction(string $name, string $uri, string $method, string $controllerMethod): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route);
        $this->assertSame($uri, $route->uri());
        $this->assertContains($method, $route->methods());
        $this->assertSame(StudentImportExportController::class . '@' . $controllerMethod, $route->getActionName());
    }

    private function assertNoRootStudentControllerRoute(string $method, string $uri, string $controllerMethod): void
    {
        $matches = collect(Route::getRoutes())->filter(function (RoutingRoute $route) use ($method, $uri, $controllerMethod) {
            return in_array($method, $route->methods(), true)
                && $route->uri() === $uri
                && $route->getActionName() === StudentController::class . '@' . $controllerMethod;
        });

        $this->assertCount(0, $matches, "Unexpected root StudentController@{$controllerMethod} route for {$method} {$uri}.");
    }
}
