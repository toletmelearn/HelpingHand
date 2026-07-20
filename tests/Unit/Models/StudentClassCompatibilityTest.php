<?php

namespace Tests\Unit\Models;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentClassCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetMinimalSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMinimalSchema();

        parent::tearDown();
    }

    public function test_canonical_class_id_prefers_class_id_when_both_exist(): void
    {
        $student = $this->student([
            'class_id' => 11,
            'school_class_id' => 8,
        ]);

        $this->assertSame(11, $student->canonicalClassId());
    }

    public function test_canonical_class_id_falls_back_to_school_class_id_when_class_id_missing(): void
    {
        $student = $this->student([
            'class_id' => null,
            'school_class_id' => 8,
        ]);

        $this->assertSame(8, $student->canonicalClassId());
    }

    public function test_class_id_conflict_is_detected_when_ids_differ(): void
    {
        $student = $this->student([
            'class_id' => 11,
            'school_class_id' => 8,
        ]);

        $this->assertTrue($student->hasClassIdConflict());
    }

    public function test_class_id_conflict_is_false_when_ids_match(): void
    {
        $student = $this->student([
            'class_id' => 11,
            'school_class_id' => 11,
        ]);

        $this->assertFalse($student->hasClassIdConflict());
    }

    public function test_class_compatibility_status_reports_source_and_conflict(): void
    {
        $student = $this->student([
            'class' => 'Class 8',
            'class_id' => 11,
            'school_class_id' => 8,
        ]);

        $this->assertSame([
            'canonical_class_id' => 11,
            'class_id' => 11,
            'school_class_id' => 8,
            'string_class' => 'Class 8',
            'has_conflict' => true,
            'source' => 'class_id',
        ], $student->classCompatibilityStatus());
    }

    public function test_canonical_school_class_resolves_using_preferred_class_id(): void
    {
        DB::table('school_classes')->insert([
            ['id' => 8, 'name' => 'Class 5', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'name' => 'Class 8', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $student = $this->student([
            'class' => 'Class 8',
            'class_id' => 11,
            'school_class_id' => 8,
        ]);

        $schoolClass = $student->resolveCanonicalSchoolClass();

        $this->assertInstanceOf(SchoolClass::class, $schoolClass);
        $this->assertSame(11, $schoolClass->id);
        $this->assertSame('Class 8', $schoolClass->name);
    }

    private function student(array $attributes): Student
    {
        return (new Student())->forceFill($attributes);
    }

    private function resetMinimalSchema(): void
    {
        $this->dropMinimalSchema();

        Schema::create('school_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->string('section')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->timestamps();
        });
    }

    private function dropMinimalSchema(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
    }
}
