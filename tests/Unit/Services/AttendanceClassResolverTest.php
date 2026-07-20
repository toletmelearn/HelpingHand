<?php

namespace Tests\Unit\Services;

use App\Models\Student;
use App\Services\Attendance\AttendanceClassResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceClassResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        Schema::create('school_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('class')->nullable();
            $table->integer('class_id')->nullable();
            $table->integer('school_class_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');

        parent::tearDown();
    }

    public function test_resolver_returns_conflict_when_student_class_ids_conflict(): void
    {
        $classId = $this->createSchoolClass('Class 8');
        $schoolClassId = $this->createSchoolClass('Class 9');
        $student = $this->createStudent([
            'class' => 'Legacy 8',
            'class_id' => $classId,
            'school_class_id' => $schoolClassId,
        ]);

        $result = $this->resolver()->resolveForStudent($student);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['class']);
        $this->assertSame('conflict', $result['source']);
        $this->assertSame(409, $result['status']);
        $this->assertSame(AttendanceClassResolver::CONFLICT_MESSAGE, $result['message']);
    }

    public function test_resolver_returns_canonical_class_name_when_available(): void
    {
        $classId = $this->createSchoolClass('Class 10');
        $student = $this->createStudent([
            'class' => 'Legacy 10',
            'class_id' => $classId,
        ]);

        $result = $this->resolver()->resolveForStudent($student);

        $this->assertTrue($result['ok']);
        $this->assertSame('Class 10', $result['class']);
        $this->assertSame('canonical', $result['source']);
        $this->assertSame(200, $result['status']);
        $this->assertNull($result['message']);
    }

    public function test_resolver_falls_back_to_legacy_class_when_no_canonical_class_exists(): void
    {
        $student = $this->createStudent(['class' => 'Legacy 9']);

        $result = $this->resolver()->resolveForStudent($student);

        $this->assertTrue($result['ok']);
        $this->assertSame('Legacy 9', $result['class']);
        $this->assertSame('legacy', $result['source']);
        $this->assertSame(200, $result['status']);
        $this->assertNull($result['message']);
    }

    public function test_resolver_returns_unresolved_when_no_class_available(): void
    {
        $student = $this->createStudent(['class' => null]);

        $result = $this->resolver()->resolveForStudent($student);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['class']);
        $this->assertSame('unresolved', $result['source']);
        $this->assertSame(422, $result['status']);
        $this->assertSame(AttendanceClassResolver::UNRESOLVED_MESSAGE, $result['message']);
    }

    public function test_resolver_source_is_canonical_or_legacy_or_conflict_or_unresolved(): void
    {
        $canonicalClassId = $this->createSchoolClass('Class 12');
        $conflictClassId = $this->createSchoolClass('Class 7');
        $conflictSchoolClassId = $this->createSchoolClass('Class 8');

        $sources = [
            $this->resolver()->resolveForStudent($this->createStudent(['class_id' => $canonicalClassId]))['source'],
            $this->resolver()->resolveForStudent($this->createStudent(['class' => 'Legacy Only']))['source'],
            $this->resolver()->resolveForStudent($this->createStudent([
                'class_id' => $conflictClassId,
                'school_class_id' => $conflictSchoolClassId,
            ]))['source'],
            $this->resolver()->resolveForStudent($this->createStudent(['class' => null]))['source'],
        ];

        $this->assertSame(['canonical', 'legacy', 'conflict', 'unresolved'], $sources);
    }

    private function resolver(): AttendanceClassResolver
    {
        return new AttendanceClassResolver();
    }

    private function createSchoolClass(string $name): int
    {
        return DB::table('school_classes')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStudent(array $overrides = []): Student
    {
        $id = DB::table('students')->insertGetId(array_merge([
            'name' => 'Student A',
            'class' => null,
            'class_id' => null,
            'school_class_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Student::findOrFail($id);
    }
}
