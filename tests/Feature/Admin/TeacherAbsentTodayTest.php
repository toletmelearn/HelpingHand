<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherLeave;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T3 item 3: "Teacher absent today" flow -- pick teacher + date, see
 * their day's slots and ranked suggestions, one-click assign.
 */
class TeacherAbsentTodayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function seedAbsentTeacherWithOneSlot(): array
    {
        // 2026-08-03 is a Monday.
        $date = '2026-08-03';
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $class = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MATH' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher', 'status' => 'active']);

        $slot = TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $absentTeacher->id,
        ]);

        return compact('date', 'timing', 'class', 'section', 'subject', 'absentTeacher', 'slot');
    }

    public function test_page_loads_with_no_teacher_selected(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today'));

        $response->assertOk();
    }

    public function test_selecting_a_teacher_lists_their_days_slots_with_candidates(): void
    {
        $admin = $this->admin();
        $data = $this->seedAbsentTeacherWithOneSlot();
        $freeTeacher = Teacher::create(['name' => 'Free Substitute', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today', [
            'teacher_id' => $data['absentTeacher']->id,
            'date' => $data['date'],
        ]));

        $response->assertOk();
        $response->assertSee($data['class']->name);
        $response->assertSee('Free Substitute');
    }

    public function test_selecting_a_different_day_of_week_shows_no_slots_for_a_teacher_only_teaching_monday(): void
    {
        $admin = $this->admin();
        $data = $this->seedAbsentTeacherWithOneSlot();

        // 2026-08-04 is a Tuesday -- the teacher's only slot is Monday.
        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today', [
            'teacher_id' => $data['absentTeacher']->id,
            'date' => '2026-08-04',
        ]));

        $response->assertOk();
        $response->assertSee('no timetable slots');
    }

    public function test_one_click_assign_records_the_substitution(): void
    {
        $admin = $this->admin();
        $data = $this->seedAbsentTeacherWithOneSlot();
        $substituteTeacher = Teacher::create(['name' => 'Free Substitute', 'status' => 'active']);

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign-from-slot'), [
            'substitution_date' => $data['date'],
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'substitute_teacher_id' => $substituteTeacher->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('teacher_substitutions', [
            'absent_teacher_id' => $data['absentTeacher']->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
        ]);
    }

    public function test_one_click_assign_is_rejected_if_already_recorded(): void
    {
        $admin = $this->admin();
        $data = $this->seedAbsentTeacherWithOneSlot();
        $substituteTeacher = Teacher::create(['name' => 'Free Substitute', 'status' => 'active']);
        $otherSubstitute = Teacher::create(['name' => 'Another Substitute', 'status' => 'active']);

        TeacherSubstitution::create([
            'substitution_date' => $data['date'],
            'absent_teacher_id' => $data['absentTeacher']->id,
            'substitute_teacher_id' => $substituteTeacher->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'created_by' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign-from-slot'), [
            'substitution_date' => $data['date'],
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'substitute_teacher_id' => $otherSubstitute->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(1, TeacherSubstitution::where('bell_timing_id', $data['timing']->id)->count());
    }

    public function test_unauthorized_role_cannot_view_the_page(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('admin.teacher-substitutions.absent-today'));

        $response->assertForbidden();
    }

    public function test_user_with_manage_substitutions_permission_can_assign(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'coordinator'], ['display_name' => 'Coordinator']);
        $user->roles()->attach($role->id);
        $permission = Permission::firstOrCreate(['name' => 'manage-substitutions']);
        $role->grantPermission($permission);

        $data = $this->seedAbsentTeacherWithOneSlot();
        $substituteTeacher = Teacher::create(['name' => 'Free Substitute', 'status' => 'active']);

        $response = $this->actingAs($user)->post(route('admin.teacher-substitutions.assign-from-slot'), [
            'substitution_date' => $data['date'],
            'absent_teacher_id' => $data['absentTeacher']->id,
            'class_id' => $data['class']->id,
            'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'substitute_teacher_id' => $substituteTeacher->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_teacher_on_approved_leave_today_is_shown_as_a_shortcut(): void
    {
        $admin = $this->admin();
        $onLeave = Teacher::create(['name' => 'On Leave Teacher', 'status' => 'active']);
        TeacherLeave::create([
            'teacher_id' => $onLeave->id,
            'leave_type' => 'sick',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'days' => 1,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today', ['date' => '2026-08-03']));

        $response->assertOk();
        // The teacher's name alone also appears in the plain "pick a
        // teacher" dropdown regardless of leave status, so assert on the
        // shortcut link itself (only the leave banner emits this URL).
        $response->assertSee(route('admin.teacher-substitutions.absent-today', ['teacher_id' => $onLeave->id, 'date' => '2026-08-03']));
    }

    public function test_teacher_on_pending_leave_is_not_shown_as_a_shortcut(): void
    {
        $admin = $this->admin();
        $pending = Teacher::create(['name' => 'Pending Leave Teacher', 'status' => 'active']);
        TeacherLeave::create([
            'teacher_id' => $pending->id,
            'leave_type' => 'sick',
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-03',
            'days' => 1,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today', ['date' => '2026-08-03']));

        $response->assertOk();
        $response->assertDontSee(route('admin.teacher-substitutions.absent-today', ['teacher_id' => $pending->id, 'date' => '2026-08-03']));
    }

    public function test_teacher_on_approved_leave_a_different_day_is_not_shown(): void
    {
        $admin = $this->admin();
        $onLeave = Teacher::create(['name' => 'Different Day Leave', 'status' => 'active']);
        TeacherLeave::create([
            'teacher_id' => $onLeave->id,
            'leave_type' => 'sick',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-10',
            'days' => 1,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.teacher-substitutions.absent-today', ['date' => '2026-08-03']));

        $response->assertOk();
        $response->assertDontSee(route('admin.teacher-substitutions.absent-today', ['teacher_id' => $onLeave->id, 'date' => '2026-08-03']));
    }
}
