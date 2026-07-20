<?php

namespace Tests\Feature\Students;

use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportExportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportExportControllerExtractionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->resetMinimalSchema();
        $this->seedClassAndSectionData();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_import_export_routes_point_to_student_import_export_controller(): void
    {
        $this->assertRouteAction('students.export.csv', 'students/export/csv', 'GET', 'exportCsv');
        $this->assertRouteAction('students.import.csv.preview', 'students/import/csv/preview', 'POST', 'previewImportCsv');
        $this->assertRouteAction('students.import.csv.apply', 'students/import/csv/apply', 'POST', 'applyImportCsv');
        $this->assertRouteAction('students.import.csv', 'students/import/csv', 'POST', 'importCsv');
    }

    public function test_direct_import_route_remains_guarded_after_extraction(): void
    {
        $response = $this->post(route('students.import.csv'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('warning', 'Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_preview_route_still_works_after_extraction(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Student Import Preview');
        $response->assertSessionHas('student_import_preview');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_apply_route_still_imports_clean_preview_after_extraction(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('students', [
            'name' => 'Test Student',
            'class_id' => 8,
            'school_class_id' => 8,
            'class' => 'Class 5',
            'section_id' => 1,
            'section' => '1',
        ]);
    }

    public function test_csv_export_route_still_exports_normalized_headers(): void
    {
        DB::table('students')->insert([
            'name' => 'Export Student',
            'father_name' => 'Export Father',
            'mother_name' => 'Export Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'phone' => '9876543210',
            'mobile' => '9876500000',
            'gender' => 'male',
            'category' => 'General',
            'class_id' => 8,
            'school_class_id' => 8,
            'class' => 'Legacy Class',
            'section_id' => 1,
            'section' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $content = $this->get(route('students.export.csv'))->streamedContent();

        $this->assertStringContainsString('Class ID', $content);
        $this->assertStringContainsString('Section ID', $content);
        $this->assertStringContainsString('Mobile', $content);
        $this->assertStringContainsString('Admission No', $content);
        $this->assertStringContainsString('Class 5', $content);
        $this->assertStringContainsString(',A,', $content);
    }

    public function test_root_student_controller_crud_routes_remain_inactive(): void
    {
        $this->assertNoRootStudentControllerRoute('POST', 'students', 'store');
        $this->assertNoRootStudentControllerRoute('GET', 'students/{student}', 'show');
        $this->assertNoRootStudentControllerRoute('PUT', 'students/{student}', 'update');
        $this->assertNoRootStudentControllerRoute('PATCH', 'students/{student}', 'update');
        $this->assertNoRootStudentControllerRoute('DELETE', 'students/{student}', 'destroy');
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

    private function storeCleanPreviewInSession(): array
    {
        $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        return session('student_import_preview');
    }

    private function validCsv(): string
    {
        return "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n"
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Class 5,A,1,Hindu,General,A+,Address\n";
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', $contents);
    }

    private function seedClassAndSectionData(): void
    {
        $now = now();

        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('sections')->insert([
            ['id' => 1, 'name' => 'A', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('class_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('aadhar_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('gender')->nullable();
            $table->string('category')->nullable();
            $table->string('class')->nullable();
            $table->string('section')->nullable();
            $table->integer('roll_number')->nullable()->unique();
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->string('blood_group')->nullable();
            $table->text('address')->nullable();
            $table->string('admission_no')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
    }
}
