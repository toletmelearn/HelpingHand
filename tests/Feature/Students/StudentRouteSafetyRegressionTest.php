<?php

namespace Tests\Feature\Students;

use App\Http\Controllers\Admin\AdminStudentController;
use App\Http\Controllers\StudentImportExportController;
use App\Http\Controllers\StudentController;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StudentRouteSafetyRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
    }

    public function test_canonical_admin_student_show_route_exists(): void
    {
        $route = Route::getRoutes()->getByName('admin.students.show');

        $this->assertNotNull($route);
        $this->assertSame('admin/students/{student}', $route->uri());
        $this->assertSame(AdminStudentController::class . '@show', $route->getActionName());
    }

    public function test_class_teacher_student_records_view_uses_admin_students_show(): void
    {
        $view = file_get_contents(resource_path('views/admin/class-teacher-control/student-records.blade.php'));

        $this->assertStringContainsString("route('admin.students.show', \$student)", $view);
        $this->assertStringNotContainsString("route('students.show', \$student)", $view);
    }

    public function test_legacy_root_student_store_route_is_not_registered_as_web_crud(): void
    {
        $this->assertNoRootStudentControllerRoute('POST', 'students', 'store');
    }

    public function test_legacy_root_student_update_route_is_not_registered_as_web_crud(): void
    {
        $this->assertNoRootStudentControllerRoute('PUT', 'students/{student}', 'update');
        $this->assertNoRootStudentControllerRoute('PATCH', 'students/{student}', 'update');
    }

    public function test_legacy_root_student_destroy_route_is_not_registered_as_web_crud(): void
    {
        $this->assertNoRootStudentControllerRoute('DELETE', 'students/{student}', 'destroy');
    }

    public function test_legacy_root_student_show_route_is_not_registered_as_web_crud(): void
    {
        $this->assertNoRootStudentControllerRoute('GET', 'students/{student}', 'show');
    }

    public function test_students_create_redirect_route_remains_registered(): void
    {
        $route = Route::getRoutes()->getByName('students.create');

        $this->assertNotNull($route);
        $this->assertSame('students/create', $route->uri());
        $this->assertContains('GET', $route->methods());

        $this->get('/students/create')
            ->assertRedirect(route('admin.students.create'));
    }

    public function test_direct_import_route_remains_guarded(): void
    {
        $route = Route::getRoutes()->getByName('students.import.csv');

        $this->assertNotNull($route);
        $this->assertSame('students/import/csv', $route->uri());
        $this->assertContains('POST', $route->methods());
        $this->assertSame(StudentImportExportController::class . '@importCsv', $route->getActionName());

        $this->post(route('students.import.csv'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', "Name\nTest Student\n"),
        ])->assertRedirect(route('students.index'))
            ->assertSessionHas('warning', 'Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.');
    }

    public function test_preview_and_apply_routes_remain_registered(): void
    {
        $preview = Route::getRoutes()->getByName('students.import.csv.preview');
        $apply = Route::getRoutes()->getByName('students.import.csv.apply');

        $this->assertNotNull($preview);
        $this->assertSame('students/import/csv/preview', $preview->uri());
        $this->assertContains('POST', $preview->methods());
        $this->assertSame(StudentImportExportController::class . '@previewImportCsv', $preview->getActionName());

        $this->assertNotNull($apply);
        $this->assertSame('students/import/csv/apply', $apply->uri());
        $this->assertContains('POST', $apply->methods());
        $this->assertSame(StudentImportExportController::class . '@applyImportCsv', $apply->getActionName());
    }

    public function test_csv_export_route_remains_registered(): void
    {
        $route = Route::getRoutes()->getByName('students.export.csv');

        $this->assertNotNull($route);
        $this->assertSame('students/export/csv', $route->uri());
        $this->assertContains('GET', $route->methods());
        $this->assertSame(StudentImportExportController::class . '@exportCsv', $route->getActionName());
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
