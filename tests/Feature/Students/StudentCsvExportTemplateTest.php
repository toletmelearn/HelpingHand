<?php

namespace Tests\Feature\Students;

use App\Services\Students\StudentImportNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentCsvExportTemplateTest extends TestCase
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

    public function test_csv_export_includes_class_id_and_section_id_headers(): void
    {
        $this->createStudent();

        $content = $this->get(route('students.export.csv'))->streamedContent();

        $this->assertStringContainsString('Class ID', $content);
        $this->assertStringContainsString('Section ID', $content);
        $this->assertStringContainsString('Mobile', $content);
        $this->assertStringContainsString('Admission No', $content);
    }

    public function test_csv_export_uses_canonical_class_and_section_display_names(): void
    {
        $this->createStudent([
            'class' => 'Legacy Class String',
            'section' => '1',
        ]);

        $content = $this->get(route('students.export.csv'))->streamedContent();

        $this->assertStringContainsString('Canonical Class 5', $content);
        $this->assertStringContainsString(',A,', $content);
        $this->assertStringNotContainsString('Legacy Class String', $content);
    }

    public function test_csv_export_includes_mobile_and_admission_no(): void
    {
        $this->createStudent([
            'mobile' => '9999999999',
            'admission_no' => 'ADM-100',
        ]);

        $content = $this->get(route('students.export.csv'))->streamedContent();

        $this->assertStringContainsString('9999999999', $content);
        $this->assertStringContainsString('ADM-100', $content);
    }

    public function test_sample_csv_includes_class_id_and_section_id_headers(): void
    {
        $contents = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertStringContainsString('Class ID', $contents);
        $this->assertStringContainsString('Section ID', $contents);
        $this->assertStringContainsString('Mobile', $contents);
        $this->assertStringContainsString('Admission No', $contents);
    }

    public function test_sample_csv_displays_reference_id_warning(): void
    {
        $contents = file_get_contents(resource_path('views/students/index.blade.php'));

        $this->assertStringContainsString('Class ID and Section ID are preferred for import accuracy.', $contents);
        $this->assertStringContainsString('ID is for reference only; import creates new students and does not update existing rows.', $contents);
    }

    public function test_import_normalizer_accepts_new_export_template_headers(): void
    {
        $result = app(StudentImportNormalizer::class)->normalizeRow([
            'Name' => 'Template Student',
            'Class ID' => 8,
            'Class' => 'Ignored Class Fallback',
            'Section ID' => 1,
            'Section' => 'Ignored Section Fallback',
        ], 2);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(8, $result['normalized']['class_id']);
        $this->assertSame(8, $result['normalized']['school_class_id']);
        $this->assertSame('Canonical Class 5', $result['normalized']['class']);
        $this->assertSame(1, $result['normalized']['section_id']);
        $this->assertSame('1', $result['normalized']['section']);
    }

    public function test_import_apply_still_accepts_new_template_format(): void
    {
        $this->post(route('students.import.csv.preview'), [
            'csv_file' => $this->csvFile($this->newTemplateCsv()),
        ])->assertOk();

        $preview = session('student_import_preview');

        $this->assertIsArray($preview);

        $this->post(route('students.import.csv.apply'), [
            'preview_id' => $preview['preview_id'],
            'hash' => $preview['hash'],
        ])->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', [
            'name' => 'Template Student',
            'class_id' => 8,
            'school_class_id' => 8,
            'class' => 'Canonical Class 5',
            'section_id' => 1,
            'section' => '1',
            'mobile' => '9999999999',
            'admission_no' => 'ADM-200',
        ]);
    }

    private function createStudent(array $overrides = []): void
    {
        DB::table('students')->insert(array_merge([
            'name' => 'Export Student',
            'father_name' => 'Export Father',
            'mother_name' => 'Export Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'phone' => '9876543210',
            'mobile' => '9876500000',
            'gender' => 'male',
            'category' => 'General',
            'class' => 'Canonical Class 5',
            'class_id' => 8,
            'school_class_id' => 8,
            'section_id' => 1,
            'section' => '1',
            'roll_number' => 1,
            'religion' => 'Hindu',
            'caste' => 'General',
            'blood_group' => 'A+',
            'address' => 'Export Address',
            'admission_no' => 'ADM-001',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function newTemplateCsv(): string
    {
        return "ID,Name,Father Name,Mother Name,Date of Birth,Aadhaar Number,Phone,Mobile,Gender,Category,Class ID,Class,Section ID,Section,Roll Number,Religion,Caste,Blood Group,Address,Admission No\n"
            . "1,Template Student,Template Father,Template Mother,2010-01-01,555555555555,9876543210,9999999999,male,General,8,Canonical Class 5,1,A,44,Hindu,General,A+,Template Address,ADM-200\n";
    }

    private function csvFile(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('students.csv', $contents);
    }

    private function seedClassAndSectionData(): void
    {
        $now = now();

        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Canonical Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
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
            $table->string('aadhaar_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('gender')->nullable();
            $table->string('category')->nullable();
            $table->string('class')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('section')->nullable();
            $table->integer('roll_number')->nullable()->unique();
            $table->string('religion')->nullable();
            $table->string('caste')->nullable();
            $table->string('blood_group')->nullable();
            $table->text('address')->nullable();
            $table->string('admission_no')->nullable();
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
