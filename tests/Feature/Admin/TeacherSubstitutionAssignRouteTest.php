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
 * T3 item 1 (bonus fix): the assign/approve/cancel routes' {substitution}
 * wildcard didn't match the controller's $teacherSubstitution parameter --
 * implicit binding only matches by exact name or its snake_case form, so
 * it silently handed the action an empty, unsaved TeacherSubstitution
 * instead of the real record. Renamed the wildcard to {teacher_substitution}.
 */
class TeacherSubstitutionAssignRouteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
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

    public function test_assign_route_resolves_the_real_substitution_record(): void
    {
        $admin = $this->admin();
        $substitution = $this->makeSubstitution();
        $substituteTeacher = Teacher::create(['name' => 'Substitute Teacher']);

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign', $substitution), [
            'substitute_teacher_id' => $substituteTeacher->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', [
            'id' => $substitution->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'status' => 'assigned',
        ]);
    }

    public function test_approve_route_resolves_the_real_substitution_record(): void
    {
        $admin = $this->admin();
        $substitution = $this->makeSubstitution();

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.approve', $substitution));

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitution->id, 'status' => 'approved']);
    }

    public function test_cancel_route_resolves_the_real_substitution_record(): void
    {
        $admin = $this->admin();
        $substitution = $this->makeSubstitution();

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.cancel', $substitution));

        $response->assertRedirect();
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitution->id, 'status' => 'cancelled']);
    }
}
