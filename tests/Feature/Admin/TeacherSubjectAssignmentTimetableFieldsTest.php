<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2a item 4: periods_per_week / require_consecutive inputs on the
 * teacher-subject-assignment create/update screens.
 */
class TeacherSubjectAssignmentTimetableFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::create(['name' => 'Test Teacher ' . uniqid()]);
    }

    private function makeSchoolClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Class 5', 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create(['name' => 'Mathematics', 'code' => 'MATH' . uniqid(), 'is_active' => true]);
    }

    public function test_store_accepts_valid_periods_per_week_and_require_consecutive(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 6,
            'require_consecutive' => '1',
        ]);

        $response->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $teacher->id,
            'periods_per_week' => 6,
            'require_consecutive' => true,
        ]);
    }

    public function test_store_defaults_periods_per_week_null_and_require_consecutive_false_when_omitted(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'teacher_id' => $teacher->id,
            'periods_per_week' => null,
            'require_consecutive' => false,
        ]);
    }

    public function test_store_rejects_periods_per_week_below_minimum(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 0,
        ]);

        $response->assertSessionHasErrors('periods_per_week');
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['teacher_id' => $teacher->id]);
    }

    public function test_store_rejects_periods_per_week_above_maximum(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 13,
        ]);

        $response->assertSessionHasErrors('periods_per_week');
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['teacher_id' => $teacher->id]);
    }

    public function test_update_persists_periods_per_week_and_require_consecutive(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $assignment = TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.teacher-subject-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 12,
            'require_consecutive' => '1',
        ]);

        $response->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', [
            'id' => $assignment->id,
            'periods_per_week' => 12,
            'require_consecutive' => true,
        ]);
    }

    public function test_update_rejects_out_of_range_periods_per_week(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $assignment = TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
            'periods_per_week' => 5,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.teacher-subject-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 13,
        ]);

        $response->assertSessionHasErrors('periods_per_week');
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id, 'periods_per_week' => 5]);
    }

    public function test_update_unchecking_require_consecutive_persists_false(): void
    {
        $admin = $this->admin();
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $assignment = TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
            'require_consecutive' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.teacher-subject-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id, 'require_consecutive' => false]);
    }
}
