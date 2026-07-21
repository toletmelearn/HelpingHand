<?php

namespace Tests\Feature\Students;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentClassColumnConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private static int $classOrderSeq = 0;

    private function makeSchoolClass(string $name = 'Class 5'): SchoolClass
    {
        return SchoolClass::create([
            'name' => $name,
            'class_order' => ++self::$classOrderSeq,
            'is_active' => true,
        ]);
    }

    private function baseStudentAttributes(): array
    {
        return [
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2015-01-01',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
        ];
    }

    public function test_creating_with_only_school_class_id_derives_matching_class_id(): void
    {
        $schoolClass = $this->makeSchoolClass();

        $student = Student::create(array_merge($this->baseStudentAttributes(), [
            'school_class_id' => $schoolClass->id,
        ]));

        $student->refresh();

        $this->assertSame($schoolClass->id, $student->school_class_id);
        $this->assertSame($schoolClass->id, $student->class_id);
    }

    public function test_creating_with_only_legacy_class_id_still_backfills_school_class_id(): void
    {
        $schoolClass = $this->makeSchoolClass();

        $student = Student::create(array_merge($this->baseStudentAttributes(), [
            'class_id' => $schoolClass->id,
        ]));

        $student->refresh();

        $this->assertSame($schoolClass->id, $student->school_class_id);
        $this->assertSame($schoolClass->id, $student->class_id);
    }

    public function test_school_class_id_wins_when_both_are_set_and_disagree(): void
    {
        $correctClass = $this->makeSchoolClass('Class 6');
        $wrongClass = $this->makeSchoolClass('Class 7');

        $student = Student::create(array_merge($this->baseStudentAttributes(), [
            'class_id' => $wrongClass->id,
            'school_class_id' => $correctClass->id,
        ]));

        $student->refresh();

        $this->assertSame($correctClass->id, $student->school_class_id);
        $this->assertSame($correctClass->id, $student->class_id);
    }

    public function test_admin_student_show_page_renders_class_name_via_school_class_relationship(): void
    {
        $admin = $this->makeAdmin();
        $schoolClass = $this->makeSchoolClass('Class 9');

        $student = Student::create(array_merge($this->baseStudentAttributes(), [
            'school_class_id' => $schoolClass->id,
            'class' => 'IX', // deliberately stale/differently-notated legacy string
        ]));

        $response = $this->actingAs($admin)->get(route('admin.students.show', $student->id));

        $response->assertOk();
        $response->assertSee('Class 9');
    }
}
