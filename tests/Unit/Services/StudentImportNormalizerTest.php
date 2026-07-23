<?php

namespace Tests\Unit\Services;

use App\Services\Students\StudentImportNormalizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentImportNormalizerTest extends TestCase
{
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

    public function test_normalizer_resolves_class_by_class_id(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'class_id' => 8,
            'Section' => 'A',
        ], 2);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(8, $result['normalized']['class_id']);
        $this->assertSame('Class 5', $result['normalized']['class']);
        $this->assertSame(2, $result['row_number']);
    }

    public function test_normalizer_resolves_class_by_class_name(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 8',
            'Section' => 'A',
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(11, $result['normalized']['class_id']);
        $this->assertSame('Class 8', $result['normalized']['class']);
    }

    public function test_normalizer_sets_school_class_id_equal_to_class_id(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'school_class_id' => 11,
            'Section' => 'A',
        ]);

        $this->assertSame(11, $result['normalized']['class_id']);
        $this->assertSame(11, $result['normalized']['school_class_id']);
    }

    public function test_normalizer_resolves_section_by_section_id(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 5',
            'section_id' => 2,
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(2, $result['normalized']['section_id']);
    }

    public function test_normalizer_resolves_section_by_section_name(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 5',
            'Section' => 'B',
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertSame(2, $result['normalized']['section_id']);
    }

    public function test_normalizer_sets_legacy_section_to_section_id_string(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 5',
            'Section' => 'B',
        ]);

        $this->assertSame('2', $result['normalized']['section']);
    }

    public function test_normalizer_reports_error_for_unresolved_class(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Legacy Class Z',
            'Section' => 'A',
        ]);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Class could not be resolved.', $result['errors']);
    }

    public function test_normalizer_reports_error_for_unresolved_section(): void
    {
        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 5',
            'Section' => 'Legacy Z',
        ]);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Section could not be resolved.', $result['errors']);
    }

    public function test_normalizer_detects_duplicate_aadhaar_number_if_present(): void
    {
        DB::table('students')->insert([
            'name' => 'Existing Student',
            'aadhaar_number' => '123456789012',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Aadhaar Number' => '123456789012',
            'Class' => 'Class 5',
            'Section' => 'A',
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertContains('Duplicate aadhaar_number found.', $result['warnings']);
    }

    public function test_normalizer_does_not_modify_database(): void
    {
        $before = DB::table('students')->count();

        $this->normalizer()->normalizeRow([
            'Name' => 'Test Student',
            'Class' => 'Class 5',
            'Section' => 'A',
            'Aadhaar Number' => '123456789012',
        ]);

        $this->assertSame($before, DB::table('students')->count());
    }

    private function normalizer(): StudentImportNormalizer
    {
        return new StudentImportNormalizer();
    }

    private function seedClassAndSectionData(): void
    {
        $now = now();

        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'class_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 11, 'name' => 'Class 8', 'class_order' => 11, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('sections')->insert([
            ['id' => 1, 'name' => 'A', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'B', 'created_at' => $now, 'updated_at' => $now],
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
            $table->string('aadhaar_number')->nullable();
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
