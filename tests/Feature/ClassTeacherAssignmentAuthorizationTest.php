<?php

namespace Tests\Feature;

use App\Models\ClassTeacherAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeacherAssignmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeAssignment(User $teacher): ClassTeacherAssignment
    {
        return ClassTeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 5',
            'is_active' => true,
        ]);
    }

    public function test_unauthorized_role_gets_403_on_store(): void
    {
        $parent = $this->makeUserWithRole('parent');
        $teacher = $this->makeUserWithRole('teacher');

        $response = $this->actingAs($parent)->post(route('admin.class-teacher-assignments.store'), [
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 5',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('class_teacher_assignments', ['teacher_id' => $teacher->id]);
    }

    public function test_unauthorized_role_gets_403_on_update(): void
    {
        $parent = $this->makeUserWithRole('parent');
        $teacher = $this->makeUserWithRole('teacher');
        $assignment = $this->makeAssignment($teacher);

        $response = $this->actingAs($parent)->put(route('admin.class-teacher-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 6',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('class_teacher_assignments', ['id' => $assignment->id, 'assigned_class' => 'Class 5']);
    }

    public function test_unauthorized_role_gets_403_on_destroy(): void
    {
        $parent = $this->makeUserWithRole('parent');
        $teacher = $this->makeUserWithRole('teacher');
        $assignment = $this->makeAssignment($teacher);

        $response = $this->actingAs($parent)->delete(route('admin.class-teacher-assignments.destroy', $assignment));

        $response->assertForbidden();
        $this->assertDatabaseHas('class_teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_admin_can_create_update_and_delete(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $teacher = $this->makeUserWithRole('teacher');

        $store = $this->actingAs($admin)->post(route('admin.class-teacher-assignments.store'), [
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 5',
        ]);
        $store->assertRedirect(route('admin.class-teacher-assignments.index'));
        $assignment = ClassTeacherAssignment::where('teacher_id', $teacher->id)->firstOrFail();

        $update = $this->actingAs($admin)->put(route('admin.class-teacher-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 6',
        ]);
        $update->assertRedirect(route('admin.class-teacher-assignments.index'));
        $this->assertDatabaseHas('class_teacher_assignments', ['id' => $assignment->id, 'assigned_class' => 'Class 6']);

        $destroy = $this->actingAs($admin)->delete(route('admin.class-teacher-assignments.destroy', $assignment));
        $destroy->assertRedirect(route('admin.class-teacher-assignments.index'));
        $this->assertDatabaseMissing('class_teacher_assignments', ['id' => $assignment->id]);
    }

    public function test_staff_can_create(): void
    {
        $staff = $this->makeUserWithRole('staff');
        $teacher = $this->makeUserWithRole('teacher');

        $response = $this->actingAs($staff)->post(route('admin.class-teacher-assignments.store'), [
            'teacher_id' => $teacher->id,
            'assigned_class' => 'Class 5',
        ]);

        $response->assertRedirect(route('admin.class-teacher-assignments.index'));
        $this->assertDatabaseHas('class_teacher_assignments', ['teacher_id' => $teacher->id]);
    }
}
