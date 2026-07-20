<?php

namespace Tests\Feature\Students;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportApplyTest extends TestCase
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

    public function test_clean_preview_shows_apply_button(): void
    {
        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Apply Import');
        $response->assertSessionHas('student_import_preview');
    }

    public function test_preview_with_errors_does_not_show_apply_button(): void
    {
        $csv = $this->csvHeader()
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Unknown,A,1,Hindu,General,A+,Address\n";

        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($csv),
        ]);

        $response->assertOk();
        $response->assertDontSee('Apply Import');
        $response->assertSessionMissing('student_import_preview');
    }

    public function test_preview_with_duplicate_warnings_does_not_show_apply_button(): void
    {
        DB::table('students')->insert([
            'name' => 'Existing Student',
            'aadhar_number' => '123456789012',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertOk();
        $response->assertSee('Duplicate aadhar_number found.');
        $response->assertDontSee('Apply Import');
        $response->assertSessionMissing('student_import_preview');
    }

    public function test_apply_rejects_missing_preview_session(): void
    {
        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => 'missing',
            'hash' => 'missing',
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_apply_rejects_mismatched_preview_hash(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => 'bad-hash',
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_apply_imports_clean_preview_with_normalized_class_section_fields(): void
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

    public function test_apply_clears_preview_session_after_success(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $this->assertFalse(session()->has('student_import_preview'));
    }

    public function test_apply_prevents_repeated_apply(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $second = $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $second->assertRedirect(route('students.index'));
        $second->assertSessionHas('error');
        $this->assertSame(1, DB::table('students')->where('name', 'Test Student')->count());
    }

    public function test_apply_rechecks_duplicates_before_writing(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        DB::table('students')->insert([
            'name' => 'Race Winner',
            'aadhar_number' => '123456789012',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('import_errors');
        $this->assertSame(1, DB::table('students')->count());
    }

    public function test_apply_is_transactional_and_rolls_back_on_failure(): void
    {
        $rows = [
            $this->previewRow('First Student', '111111111111', '1'),
            $this->previewRow('Second Student', '222222222222', '1'),
        ];

        $preview = $this->putPreviewInSession($rows);

        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('error');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_apply_does_not_create_users_or_passwords(): void
    {
        $preview = $this->storeCleanPreviewInSession();

        $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ]);

        $this->assertSame(0, DB::table('users')->count());
        $this->assertFalse(Schema::hasColumn('students', 'password'));
    }

    public function test_direct_import_route_remains_guarded(): void
    {
        $response = $this->post(route('students.import.csv'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('warning');
        $this->assertSame(0, DB::table('students')->count());
    }

    public function test_apply_route_exists_and_is_post_only(): void
    {
        $this->assertTrue(Route::has('students.import.csv.apply'));

        $route = Route::getRoutes()->getByName('students.import.csv.apply');

        $this->assertNotNull($route);
        $this->assertContains('POST', $route->methods());
        $this->assertNotContains('GET', $route->methods());
    }

    private function storeCleanPreviewInSession(): array
    {
        $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->validCsv()),
        ]);

        return session('student_import_preview');
    }

    private function putPreviewInSession(array $rows): array
    {
        $preview = [
            'preview_id' => 'test-preview',
            'created_at' => now()->timestamp,
            'hash' => hash('sha256', json_encode($rows)),
            'rows' => $rows,
            'summary' => [
                'total_rows' => count($rows),
                'valid_rows' => count($rows),
                'rows_with_errors' => 0,
                'rows_with_warnings' => 0,
            ],
        ];

        session()->put('student_import_preview', $preview);

        return $preview;
    }

    private function previewRow(string $name, string $aadharNumber, string $rollNumber): array
    {
        return [
            'row_number' => 2,
            'original' => [
                1 => $name,
                2 => 'Test Father',
                3 => 'Test Mother',
                4 => '2010-01-01',
                5 => $aadharNumber,
                6 => '9876543210',
                7 => 'male',
                8 => 'General',
                9 => 'Class 5',
                10 => 'A',
                11 => $rollNumber,
                12 => 'Hindu',
                13 => 'General',
                14 => 'A+',
                15 => 'Address',
            ],
            'normalized' => [
                'class_id' => 8,
                'school_class_id' => 8,
                'class' => 'Class 5',
                'section_id' => 1,
                'section' => '1',
            ],
            'errors' => [],
            'warnings' => [],
            'is_valid' => true,
        ];
    }

    private function validCsv(): string
    {
        return $this->csvHeader()
            . "1,Test Student,Test Father,Test Mother,2010-01-01,123456789012,9876543210,male,General,Class 5,A,1,Hindu,General,A+,Address\n";
    }

    private function csvHeader(): string
    {
        return "ID,Name,Father Name,Mother Name,Date of Birth,Aadhar Number,Phone,Gender,Category,Class,Section,Roll Number,Religion,Caste,Blood Group,Address\n";
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

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

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
        Schema::dropIfExists('users');
    }
}
