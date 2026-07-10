<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
    }

    private function makeStudent(string $name): Student
    {
        return Student::create([
            'name' => $name,
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
            'category' => 'General',
            'phone' => '9998887771',
            'address' => 'Somewhere',
        ]);
    }

    /** @test */
    public function admin_can_delete_multiple_students_in_one_request()
    {
        $s1 = $this->makeStudent('Student One');
        $s2 = $this->makeStudent('Student Two');
        $s3 = $this->makeStudent('Student Three');

        $this->actingAs($this->admin)
            ->post(route('admin.students.bulk-destroy'), [
                'student_ids' => [$s1->id, $s2->id],
            ])
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted('students', ['id' => $s1->id]);
        $this->assertSoftDeleted('students', ['id' => $s2->id]);
        $this->assertDatabaseHas('students', ['id' => $s3->id, 'deleted_at' => null]);
    }

    /** @test */
    public function bulk_delete_requires_at_least_one_student_id()
    {
        $this->actingAs($this->admin)
            ->post(route('admin.students.bulk-destroy'), ['student_ids' => []])
            ->assertSessionHasErrors('student_ids');
    }

    /** @test */
    public function user_without_delete_permission_cannot_bulk_delete_students()
    {
        $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $limitedUser = User::factory()->create();
        $limitedUser->roles()->attach($teacherRole->id);

        $student = $this->makeStudent('Protected Student');

        $this->actingAs($limitedUser)
            ->post(route('admin.students.bulk-destroy'), ['student_ids' => [$student->id]])
            ->assertRedirect(route('admin.students.index'));

        $this->assertDatabaseHas('students', ['id' => $student->id, 'deleted_at' => null]);
    }

    /** @test */
    public function individual_student_delete_route_still_works_unchanged()
    {
        $student = $this->makeStudent('Single Delete Student');

        $this->actingAs($this->admin)
            ->delete(route('admin.students.destroy', $student->id))
            ->assertRedirect(route('admin.students.index'));

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }
}
