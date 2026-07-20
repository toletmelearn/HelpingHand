<?php

namespace Tests\Feature\Students;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportPreviewTest extends TestCase
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

    public function test_preview_route_exists_and_is_post_only(): void
    {
        $this->assertTrue(Route::has('students.import.csv.preview'));

        $route = Route::getRoutes()->getByName('students.import.csv.preview');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    public function test_preview_upload_parses_rows_and_returns_summary(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Total Rows');
        $response->assertSee('Valid Rows');
        $response->assertSee('1');
    }

    public function test_preview_uses_normalizer_and_shows_normalized_class_section(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Class 5');
        $response->assertSee('class="h3 text-success">1', false);
        $response->assertSee('<td>8</td>', false);
        $response->assertSee('<td>1</td>', false);
    }

    public function test_preview_reports_row_errors_without_importing_students(): void
    {
        $csv = "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n"
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Unknown Class,A,1,Hindu,General,A+,Address\n";

        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertSee('Class could not be resolved.');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_preview_reports_warnings_without_importing_students(): void
    {
        DB::table('students')->insert([
            'name' => 'Existing Student',
            'aadhar_number' => '123456789012',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $before = DB::table('students')->count();

        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Duplicate aadhar_number found.');
        $this->assertSame($before, DB::table('students')->count());
    }

    public function test_clean_preview_page_shows_apply_button_without_import_now_controls(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Apply Import');
        $response->assertSee(route('students.import.csv.apply'), false);
        $response->assertDontSee('Import Now');
        $response->assertDontSee('Confirm Import');
    }

    public function test_preview_with_errors_does_not_show_apply_button(): void
    {
        $csv = "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n"
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Unknown Class,A,1,Hindu,General,A+,Address\n";

        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertDontSee('Apply Import');
    }

    public function test_visible_import_form_points_to_preview_route(): void
    {
        $contents = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertStringContainsString("route('students.import.csv.preview')", $contents);
        $this->assertStringContainsString('Preview only; no students are imported from this form.', $contents);
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
