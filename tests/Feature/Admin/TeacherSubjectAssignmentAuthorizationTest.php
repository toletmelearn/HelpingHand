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

class TeacherSubjectAssignmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::create(['name' => 'Test Teacher ' . uniqid()]);
    }

    private function makeSchoolClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Class 5', 'class_order' => 5, 'is_active' => true]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create(['name' => 'Mathematics', 'code' => 'MATH' . uniqid(), 'is_active' => true]);
    }

    private function makeAssignment(): TeacherClassSubjectAssignment
    {
        return TeacherClassSubjectAssignment::create([
            'teacher_id' => $this->makeTeacher()->id,
            'class_id' => $this->makeSchoolClass()->id,
            'subject_id' => $this->makeSubject()->id,
            'academic_year' => '2026-2027',
        ]);
    }

    public function test_unauthorized_role_gets_403_on_store(): void
    {
        $teacher_role_user = $this->makeUserWithRole('teacher');
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($teacher_role_user)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['teacher_id' => $teacher->id]);
    }

    public function test_unauthorized_role_gets_403_on_update(): void
    {
        $teacher_role_user = $this->makeUserWithRole('teacher');
        $assignment = $this->makeAssignment();

        $response = $this->actingAs($teacher_role_user)->put(route('admin.teacher-subject-assignments.update', $assignment), [
            'teacher_id' => $assignment->teacher_id,
            'class_id' => $assignment->class_id,
            'subject_id' => $assignment->subject_id,
            'is_class_teacher' => '1',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id, 'is_class_teacher' => false]);
    }

    public function test_unauthorized_role_gets_403_on_destroy(): void
    {
        $teacher_role_user = $this->makeUserWithRole('teacher');
        $assignment = $this->makeAssignment();

        $response = $this->actingAs($teacher_role_user)->delete(route('admin.teacher-subject-assignments.destroy', $assignment));

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id]);
    }

    public function test_admin_can_create_update_and_delete(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $store = $this->actingAs($admin)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);
        $store->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $assignment = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->firstOrFail();

        $update = $this->actingAs($admin)->put(route('admin.teacher-subject-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'is_class_teacher' => '1',
        ]);
        $update->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['id' => $assignment->id, 'is_class_teacher' => true]);

        $destroy = $this->actingAs($admin)->delete(route('admin.teacher-subject-assignments.destroy', $assignment));
        $destroy->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseMissing('teacher_class_subject_assignments', ['id' => $assignment->id]);
    }

    public function test_user_with_manage_permission_can_create(): void
    {
        $user = $this->makeUserWithRole('clerk');
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'manage-teacher-subject-assignment']);
        $user->roles->first()->grantPermission($permission);

        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();
        $subject = $this->makeSubject();

        $response = $this->actingAs($user)->post(route('admin.teacher-subject-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);

        $response->assertRedirect(route('admin.teacher-subject-assignments.index'));
        $this->assertDatabaseHas('teacher_class_subject_assignments', ['teacher_id' => $teacher->id]);
    }
}
