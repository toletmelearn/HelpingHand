<?php

namespace Tests\Feature\Teacher;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\TimetableSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hardening pass item 4 (Teacher dashboard "today" card): mirrors
 * ParentTimetableTodayTest's exact shape -- published-only, today's
 * day_of_week, scoped to this teacher as either the primary or co-teacher.
 */
class TeacherDashboardTimetableTest extends TestCase
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

    public function test_teacher_sees_their_own_periods_today(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Dash Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Dash Subject', 'code' => 'TD' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Dash Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Dash Subject');
        $response->assertSee('Dash Class');
    }

    public function test_teacher_sees_periods_where_they_are_the_co_teacher_too(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Co Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Co Subject', 'code' => 'TC' . uniqid()]);
        $primary = Teacher::create(['name' => 'Primary Teacher', 'status' => 'active']);
        $coTeacher = Teacher::create(['name' => 'Co Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $primary->id, 'co_teacher_id' => $coTeacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $login = $this->login($coTeacher);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Co Subject');
    }

    public function test_draft_slots_are_never_shown_on_the_teacher_dashboard(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Draft Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Draft Subject', 'code' => 'TX' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Draft Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        $login = $this->login($teacher);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Draft Subject');
        $response->assertSee('No periods scheduled for you today');
    }

    public function test_teacher_does_not_see_another_teachers_periods(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Other Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Other Subject', 'code' => 'TO' . uniqid()]);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $me = Teacher::create(['name' => 'Me Teacher', 'status' => 'active']);
        $login = $this->login($me);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Other Subject');
    }
}
