<?php

namespace Tests\Feature\Students;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportDirectRouteGuardTest extends TestCase
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

    public function test_direct_import_route_remains_registered_but_does_not_import(): void
    {
        $this->assertTrue(Route::has('students.import.csv'));

        $response = $this->post(route('students.import.csv'), [
            'csv_file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('students.index'));
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_direct_import_returns_controlled_warning_or_redirect(): void
    {
        $response = $this->post(route('students.import.csv'), [
            'csv_file' => $this->csvFile(),
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas(
            'warning',
            'Direct CSV import is temporarily disabled. Please use Preview first. Safe import apply flow is not enabled yet.'
        );
    }

    public function test_direct_import_does_not_call_legacy_write_path(): void
    {
        $this->post(route('students.import.csv'), [
            'csv_file' => $this->csvFile(),
        ]);

        $this->assertDatabaseMissing('students', [
            'name' => 'Test Student',
            'class' => 'Class 5',
            'section' => 'A',
        ]);
    }

    public function test_preview_route_still_renders_preview(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile(),
        ]);

        $response->assertOk();
        $response->assertSee('Preview only');
        $response->assertSee('Class 5');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_visible_import_form_still_points_to_preview_route(): void
    {
        $contents = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertStringContainsString("route('students.import.csv.preview')", $contents);
        $this->assertStringContainsString('Preview only; no students are imported from this form.', $contents);
    }

    private function csvFile(): UploadedFile
    {
        $contents = "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n"
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Class 5,A,1,Hindu,General,A+,Address\n";

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
            $table->string('aadhar_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->integer('roll_number')->nullable();
            $table->string('class')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
    }
}
