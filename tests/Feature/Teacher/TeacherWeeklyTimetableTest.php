<?php

namespace Tests\Feature\Teacher;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Timetable pilot-completion pass (Phase 2): the weekly timetable view,
 * reachable from the dashboard and the teacher sidebar. Same security
 * pattern as TeacherDashboardTimetableTest -- no route parameter names a
 * teacher, so there is nothing to tamper with; the view is always scoped
 * to whichever teacher is authenticated.
 */
class TeacherWeeklyTimetableTest extends TestCase
{
    use RefreshDatabase;

    private function login(Teacher $teacher): TeacherLogin
    {
        return TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => 'teacher' . uniqid(),
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_teacher_reaches_the_weekly_timetable_from_the_dashboard(): void
    {
        $teacher = Teacher::create(['name' => 'Nav Teacher', 'status' => 'active']);
        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee(route('teacher.timetable'), false);
    }

    public function test_teacher_sees_their_own_periods_across_the_week(): void
    {
        $class = SchoolClass::create(['name' => 'Weekly Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Weekly Subject', 'code' => 'WK' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Weekly Teacher', 'status' => 'active']);

        $monday = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        $thursday = BellTiming::create([
            'day_of_week' => 'Thursday', 'period_name' => 'P2', 'start_time' => '08:45:00', 'end_time' => '09:30:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 2, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $monday->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
            'room_number' => 'Lab-3A',
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $thursday->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $login = $this->login($teacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Monday');
        $response->assertSee('Thursday');
        $response->assertSee('Weekly Subject');
        $response->assertSee('Weekly Class');
        $response->assertSee('Lab-3A');
    }

    public function test_teacher_sees_periods_where_they_are_the_co_teacher(): void
    {
        $class = SchoolClass::create(['name' => 'Co Weekly Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Co Weekly Subject', 'code' => 'WKC' . uniqid()]);
        $primary = Teacher::create(['name' => 'Primary', 'status' => 'active']);
        $coTeacher = Teacher::create(['name' => 'Co Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Tuesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $primary->id, 'co_teacher_id' => $coTeacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $login = $this->login($coTeacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Co Weekly Subject');
        $response->assertSee($primary->name); // shown as the co-teacher column on the primary's own row -- confirms cross-linking, not just visibility.
    }

    public function test_draft_slots_are_never_shown_on_the_weekly_timetable(): void
    {
        $class = SchoolClass::create(['name' => 'Weekly Draft Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Weekly Draft Subject', 'code' => 'WKD' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Weekly Draft Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Wednesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $login = $this->login($teacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertDontSee('Weekly Draft Subject');
        $response->assertSee('No published timetable periods are assigned to you yet.');
    }

    public function test_teacher_does_not_see_another_teachers_weekly_periods(): void
    {
        $class = SchoolClass::create(['name' => 'Other Weekly Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Other Weekly Subject', 'code' => 'WKO' . uniqid()]);
        $otherTeacher = Teacher::create(['name' => 'Other Weekly Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Friday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $me = Teacher::create(['name' => 'Me Weekly Teacher', 'status' => 'active']);
        $login = $this->login($me);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertDontSee('Other Weekly Subject');
        $response->assertSee('No published timetable periods are assigned to you yet.');
    }

    public function test_arrangement_shows_when_the_teachers_own_period_is_covered(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $class = SchoolClass::create(['name' => 'Sub Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Sub Subject', 'code' => 'WKS' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher', 'status' => 'active']);
        $substituteTeacher = Teacher::create(['name' => 'Substitute Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $absentTeacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $creator = \App\Models\User::factory()->create();
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-03', 'absent_teacher_id' => $absentTeacher->id,
            'substitute_teacher_id' => $substituteTeacher->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'bell_timing_id' => $timing->id, 'period_name' => 'P1',
            'status' => 'confirmed', 'created_by' => $creator->id,
        ]);

        $login = $this->login($absentTeacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Covered by Substitute Teacher');
    }
}
