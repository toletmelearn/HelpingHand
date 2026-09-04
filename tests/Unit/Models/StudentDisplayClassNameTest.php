<?php

namespace Tests\Unit\Models;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category A1: students.class (free-text legacy string, e.g. "III") vs
 * students.school_class_id (FK) is the confirmed root cause of "Class 1"
 * in one place and "III" in another for the same student -- the model
 * layer already documents school_class_id as authoritative
 * (Student::class's own @deprecated notice on the 'class' column), but
 * ~19 view files across five portals never migrated to prefer it. This is
 * the one accessor every call site should read through instead.
 */
class StudentDisplayClassNameTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Display Class Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
        ], $overrides));
    }

    public function test_returns_school_class_name_when_relation_exists(): void
    {
        $class = SchoolClass::create(['name' => 'Class 3', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        // Deliberately mismatched legacy string -- the FK must win.
        $student = $this->makeStudent(['school_class_id' => $class->id, 'class' => 'III']);

        $this->assertSame('Class 3', $student->display_class_name);
    }

    public function test_falls_back_to_legacy_class_string_when_no_school_class_id(): void
    {
        $student = $this->makeStudent(['school_class_id' => null, 'class' => 'III']);

        $this->assertSame('III', $student->display_class_name);
    }

    public function test_returns_na_when_neither_exists(): void
    {
        $student = $this->makeStudent(['school_class_id' => null, 'class' => null]);

        $this->assertSame('N/A', $student->display_class_name);
    }

    /** class_id (the other legacy FK) is a valid fallback path for schoolClass() too -- must resolve the same way. */
    public function test_resolves_via_legacy_class_id_when_school_class_id_is_null(): void
    {
        $class = SchoolClass::create(['name' => 'Class 5', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $student = $this->makeStudent(['school_class_id' => null, 'class_id' => $class->id, 'class' => 'V']);

        $this->assertSame('Class 5', $student->display_class_name);
    }
}
