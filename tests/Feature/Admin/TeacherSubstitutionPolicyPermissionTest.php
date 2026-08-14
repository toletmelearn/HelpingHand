<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production-readiness audit follow-up: TimetableSlotPolicyScopeTest and
 * TeacherSubstitutionAuthorizationTest already cover admin-vs-unprivileged
 * for every TeacherSubstitutionPolicy-gated action, but neither test
 * exercises the policy's own hasPermission('manage-substitutions') /
 * hasPermission('view-teachers') branches directly -- a non-admin user
 * granted exactly one of the two permissions (not a role, a permission)
 * was never proven to pass, nor proven to be correctly denied the OTHER
 * permission-gated action it wasn't granted.
 */
class TeacherSubstitutionPolicyPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPermission(string $permissionName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'coordinator-' . uniqid()], ['display_name' => 'Coordinator']);
        $user->roles()->attach($role->id);
        $permission = Permission::firstOrCreate(['name' => $permissionName]);
        $role->grantPermission($permission);

        return $user;
    }

    private function userWithNoRelevantPermission(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'bystander-' . uniqid()], ['display_name' => 'Bystander']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSubstitution(): array
    {
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $class = SchoolClass::create(['name' => 'Perm Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Permission Subject', 'code' => 'PERM' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher ' . uniqid()]);
        $substituteTeacher = Teacher::create(['name' => 'Substitute Teacher ' . uniqid()]);

        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $absentTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'bell_timing_id' => $timing->id,
            'status' => 'pending',
            'created_by' => 1,
        ]);

        return compact('timing', 'class', 'section', 'subject', 'absentTeacher', 'substituteTeacher', 'substitution');
    }

    // --- manage-substitutions: allowed path -------------------------------

    public function test_user_with_manage_substitutions_permission_can_create_a_substitution(): void
    {
        $user = $this->userWithPermission('manage-substitutions');
        $data = $this->makeSubstitution();
        $newTiming = BellTiming::create(['day_of_week' => 'Tuesday', 'period_name' => 'P2', 'start_time' => '09:00', 'end_time' => '09:45', 'is_active' => true, 'is_break' => false, 'order_index' => 2]);

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.store'), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $newTiming->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_substitutions', ['bell_timing_id' => $newTiming->id]);
    }

    public function test_user_with_manage_substitutions_permission_can_update_a_substitution(): void
    {
        $user = $this->userWithPermission('manage-substitutions');
        $data = $this->makeSubstitution();

        $response = $this->actingAs($user)->put(route('admin.teacher-substitutions.update', $data['substitution']), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'substitute_teacher_id' => $data['substituteTeacher']->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', [
            'id' => $data['substitution']->id,
            'status' => 'assigned',
            'substitute_teacher_id' => $data['substituteTeacher']->id,
        ]);
    }

    public function test_user_with_manage_substitutions_permission_can_assign_a_substitute(): void
    {
        $user = $this->userWithPermission('manage-substitutions');
        $data = $this->makeSubstitution();

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.assign', $data['substitution']), [
            'substitute_teacher_id' => $data['substituteTeacher']->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', [
            'id' => $data['substitution']->id,
            'status' => 'assigned',
            'substitute_teacher_id' => $data['substituteTeacher']->id,
        ]);
    }

    public function test_user_with_manage_substitutions_permission_can_approve_a_substitute(): void
    {
        $user = $this->userWithPermission('manage-substitutions');
        $data = $this->makeSubstitution();

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.approve', $data['substitution']));

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $data['substitution']->id, 'status' => 'approved']);
    }

    public function test_user_with_manage_substitutions_permission_can_cancel_a_substitute(): void
    {
        $user = $this->userWithPermission('manage-substitutions');
        $data = $this->makeSubstitution();

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.cancel', $data['substitution']));

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $data['substitution']->id, 'status' => 'cancelled']);
    }

    // --- manage-substitutions: denied path (has a DIFFERENT permission, not this one) ---

    public function test_user_with_only_view_teachers_permission_cannot_create_a_substitution(): void
    {
        $user = $this->userWithPermission('view-teachers');
        $data = $this->makeSubstitution();
        $newTiming = BellTiming::create(['day_of_week' => 'Wednesday', 'period_name' => 'P3', 'start_time' => '10:00', 'end_time' => '10:45', 'is_active' => true, 'is_break' => false, 'order_index' => 3]);

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.store'), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $newTiming->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('teacher_substitutions', ['bell_timing_id' => $newTiming->id]);
    }

    public function test_user_with_no_relevant_permission_cannot_approve_a_substitute(): void
    {
        $user = $this->userWithNoRelevantPermission();
        $data = $this->makeSubstitution();

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.approve', $data['substitution']));

        $response->assertForbidden();
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $data['substitution']->id, 'status' => 'pending']);
    }

    // --- view-teachers: allowed path ---------------------------------------

    public function test_user_with_view_teachers_permission_can_view_the_index(): void
    {
        $user = $this->userWithPermission('view-teachers');

        $this->actingAs($user)->get(route('admin.teacher-substitutions.index'))->assertOk();
    }

    public function test_user_with_view_teachers_permission_can_view_a_single_substitution(): void
    {
        $user = $this->userWithPermission('view-teachers');
        $data = $this->makeSubstitution();

        $this->actingAs($user)->get(route('admin.teacher-substitutions.show', $data['substitution']))->assertOk();
    }

    public function test_user_with_view_teachers_permission_can_view_todays_substitutions(): void
    {
        $user = $this->userWithPermission('view-teachers');

        $this->actingAs($user)->get(route('admin.teacher-substitutions.today'))->assertOk();
    }

    public function test_user_with_view_teachers_permission_can_view_the_absence_overview(): void
    {
        $user = $this->userWithPermission('view-teachers');

        $this->actingAs($user)->get(route('admin.teacher-substitutions.absence-overview'))->assertOk();
    }

    // --- view-teachers: denied path (has a DIFFERENT permission, not this one) ---

    public function test_user_with_only_manage_substitutions_permission_cannot_view_the_index(): void
    {
        $user = $this->userWithPermission('manage-substitutions');

        $this->actingAs($user)->get(route('admin.teacher-substitutions.index'))->assertForbidden();
    }
}
