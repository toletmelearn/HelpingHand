<?php

namespace Tests\Unit\Exports;

use App\Exports\StudentsExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentsExportTest extends TestCase
{
    private const HEADINGS = [
        'ID',
        'Name',
        'Father Name',
        'Mother Name',
        'Date of Birth',
        'Aadhar Number',
        'Phone',
        'Mobile',
        'Gender',
        'Category',
        'Class ID',
        'Class',
        'Section ID',
        'Section',
        'Roll Number',
        'Religion',
        'Caste',
        'Blood Group',
        'Address',
        'Admission No',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
        $this->seedClassAndSectionData();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_students_export_has_normalized_headings(): void
    {
        $this->assertSame(self::HEADINGS, (new StudentsExport())->headings());
    }

    public function test_students_export_maps_class_id_and_section_id(): void
    {
        $row = $this->mappedCanonicalStudent();

        $this->assertSame(8, $row[10]);
        $this->assertSame(1, $row[12]);
    }

    public function test_students_export_uses_canonical_class_and_section_display_names(): void
    {
        $row = $this->mappedCanonicalStudent([
            'class' => 'Legacy Class String',
            'section' => '1',
        ]);

        $this->assertSame('Canonical Class 5', $row[11]);
        $this->assertSame('A', $row[13]);
    }

    public function test_students_export_includes_mobile_and_admission_no(): void
    {
        $row = $this->mappedCanonicalStudent([
            'mobile' => '9999999999',
            'admission_no' => 'ADM-100',
        ]);

        $this->assertSame('9999999999', $row[7]);
        $this->assertSame('ADM-100', $row[19]);
    }

    public function test_students_export_does_not_return_raw_student_columns_only(): void
    {
        $this->createStudent();

        $export = new StudentsExport();
        $student = $export->collection()->first();
        $row = $export->map($student);

        $this->assertCount(20, $export->headings());
        $this->assertCount(20, $row);
        $this->assertNotContains('created_at', $export->headings());
        $this->assertNotContains('deleted_at', $export->headings());
    }

    public function test_students_export_handles_legacy_class_section_fallbacks(): void
    {
        $row = $this->mappedCanonicalStudent([
            'class_id' => null,
            'school_class_id' => null,
            'class' => 'Legacy Class Only',
            'section_id' => null,
            'section' => 'Legacy Section Only',
        ]);

        $this->assertNull($row[10]);
        $this->assertSame('Legacy Class Only', $row[11]);
        $this->assertNull($row[12]);
        $this->assertSame('Legacy Section Only', $row[13]);
    }

    public function test_no_active_route_uses_students_export_directly_yet(): void
    {
        $this->assertTrue(Route::has('students.export.csv'));

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            $this->assertStringNotContainsString(StudentsExport::class, $action);
        }
    }

    private function mappedCanonicalStudent(array $overrides = []): array
    {
        $this->createStudent($overrides);

        $export = new StudentsExport();
        $student = $export->collection()->first();

        return $export->map($student);
    }

    private function createStudent(array $overrides = []): void
    {
        DB::table('students')->insert(array_merge([
            'name' => 'Export Student',
            'father_name' => 'Export Father',
            'mother_name' => 'Export Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
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

    private function seedClassAndSectionData(): void
    {
        $now = now();

        DB::table('school_classes')->insert([
            'id' => 8,
            'name' => 'Canonical Class 5',
            'class_order' => 8,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('sections')->insert([
            'id' => 1,
            'name' => 'A',
            'created_at' => $now,
            'updated_at' => $now,
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
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('school_classes');
    }
}
