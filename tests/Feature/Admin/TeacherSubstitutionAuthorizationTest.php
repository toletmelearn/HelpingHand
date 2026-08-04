<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
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
 * The legacy substitution CRUD (index/create/store/show/edit/update/
 * destroy/today/absenceOverview/rules) only ever had `auth` middleware --
 * TeacherSubstitutionPolicy already defined matching methods for every one
 * of these (viewAny/create/view/update/delete/viewTodaySubstitutions/
 * viewAbsenceOverview/manageRules), they just weren't called. This
 * confirms an unprivileged authenticated user is now blocked, and admin
 * still works -- i.e. authorize() calls were actually wired in, not just
 * present in the policy.
 */
class TeacherSubstitutionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function unprivileged(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSubstitution(): TeacherSubstitution
    {
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $class = SchoolClass::create(['name' => 'Class S', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher']);

        return TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $absentTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'bell_timing_id' => $timing->id,
            'status' => 'pending',
            'created_by' => 1,
        ]);
    }

    public function test_unprivileged_user_is_blocked_from_every_legacy_action(): void
    {
        $user = $this->unprivileged();
        $substitution = $this->makeSubstitution();

        $this->actingAs($user)->get(route('admin.teacher-substitutions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.create'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.teacher-substitutions.store'), [])->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.show', $substitution))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.edit', $substitution))->assertForbidden();
        $this->actingAs($user)->put(route('admin.teacher-substitutions.update', $substitution), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.teacher-substitutions.destroy', $substitution))->assertForbidden();
        $this->actingAs($user)->post(route('admin.teacher-substitutions.assign', $substitution), [])->assertForbidden();
        $this->actingAs($user)->post(route('admin.teacher-substitutions.approve', $substitution))->assertForbidden();
        $this->actingAs($user)->post(route('admin.teacher-substitutions.cancel', $substitution))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.today'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.absence-overview'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.teacher-substitutions.rules'))->assertForbidden();
    }

    public function test_admin_can_still_use_every_legacy_action(): void
    {
        $admin = $this->admin();
        $substitution = $this->makeSubstitution();

        $this->actingAs($admin)->get(route('admin.teacher-substitutions.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.show', $substitution))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.edit', $substitution))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.today'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.absence-overview'))->assertOk();
        $this->actingAs($admin)->get(route('admin.teacher-substitutions.rules'))->assertOk();
    }

    /** The rules route previously pointed at a nonexistent rules() method -- now resolves to the real substitutionRules() action. */
    public function test_rules_route_resolves_to_the_real_view(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.rules'));

        $response->assertOk();
        $response->assertViewIs('admin.teacher-substitutions.rules');
    }
}
