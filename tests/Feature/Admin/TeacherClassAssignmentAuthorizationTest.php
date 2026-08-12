<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\TeacherClassAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherClassAssignmentAuthorizationTest extends TestCase
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

    private function makeAssignment(): TeacherClassAssignment
    {
        return TeacherClassAssignment::create([
            'teacher_id' => $this->makeTeacher()->id,
            'class_id' => $this->makeSchoolClass()->id,
            'role' => 'subject_teacher',
            'assigned_at' => now(),
        ]);
    }

    public function test_unauthorized_role_gets_403_on_store(): void
    {
        $staff = $this->makeUserWithRole('staff');
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();

        $response = $this->actingAs($staff)->post(route('admin.teacher-class-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'role' => 'subject_teacher',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teacher_class_assignments', ['teacher_id' => $teacher->id]);
    }

    public function test_unauthorized_role_gets_403_on_update(): void
    {
        $staff = $this->makeUserWithRole('staff');
        $assignment = $this->makeAssignment();

        $response = $this->actingAs($staff)->put(route('admin.teacher-class-assignments.update', $assignment), [
            'teacher_id' => $assignment->teacher_id,
            'class_id' => $assignment->class_id,
            'role' => 'class_teacher',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $assignment->id, 'role' => 'subject_teacher']);
    }

    public function test_unauthorized_role_gets_403_on_destroy(): void
    {
        $staff = $this->makeUserWithRole('staff');
        $assignment = $this->makeAssignment();

        $response = $this->actingAs($staff)->delete(route('admin.teacher-class-assignments.destroy', $assignment));

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $assignment->id]);
    }

    public function test_staff_can_view_but_not_write(): void
    {
        $staff = $this->makeUserWithRole('staff');
        $this->makeAssignment();

        $response = $this->actingAs($staff)->get(route('admin.teacher-class-assignments.index'));

        $response->assertOk();
    }

    /**
     * Pilot-hardening (Class Teacher / Section): this screen has no section
     * field and is never read by the Timetable module -- an admin trying
     * to assign a section-specific class teacher (e.g. Class 3, Section B)
     * needs to be redirected in intent to Teacher-Subject Assignment,
     * which is the screen the generator actually reads. Confirms the
     * clarifying banner and cross-link are really on the page, not just
     * documented.
     */
    public function test_index_clarifies_this_screen_has_no_section_and_links_to_teacher_subject_assignment(): void
    {
        $staff = $this->makeUserWithRole('staff');

        $response = $this->actingAs($staff)->get(route('admin.teacher-class-assignments.index'));

        $response->assertOk();
        $response->assertSee('not', false);
        $response->assertSee('Timetable module', false);
        $response->assertSee(route('admin.teacher-subject-assignments.index'), false);
    }

    /**
     * Pilot-hardening (Navigation): the sidebar labels this screen "Class
     * Teacher Assignment (Whole Class)", but the page's own title/header
     * previously said "Teacher-Class Assignments" -- a different phrase
     * that didn't reinforce the sidebar's disambiguation once an admin
     * actually landed here. Confirms the in-page header now matches the
     * sidebar wording exactly, with a breadcrumb for orientation.
     */
    public function test_index_header_matches_the_sidebars_disambiguated_label(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.teacher-class-assignments.index'));

        $response->assertOk();
        $response->assertSee('Class Teacher Assignment (Whole Class)');
        $response->assertSee('breadcrumb', false);
    }

    public function test_admin_can_create_update_and_delete(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $teacher = $this->makeTeacher();
        $class = $this->makeSchoolClass();

        $store = $this->actingAs($admin)->post(route('admin.teacher-class-assignments.store'), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'role' => 'subject_teacher',
        ]);
        $store->assertRedirect(route('admin.teacher-class-assignments.index'));
        $assignment = TeacherClassAssignment::where('teacher_id', $teacher->id)->firstOrFail();

        $update = $this->actingAs($admin)->put(route('admin.teacher-class-assignments.update', $assignment), [
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'role' => 'class_teacher',
        ]);
        $update->assertRedirect(route('admin.teacher-class-assignments.index'));
        $this->assertDatabaseHas('teacher_class_assignments', ['id' => $assignment->id, 'role' => 'class_teacher']);

        $destroy = $this->actingAs($admin)->delete(route('admin.teacher-class-assignments.destroy', $assignment));
        $destroy->assertRedirect(route('admin.teacher-class-assignments.index'));
        $this->assertDatabaseMissing('teacher_class_assignments', ['id' => $assignment->id]);
    }
}
