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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * UAT Step 5, Scenario 15: an absent-and-substituted teacher's own Today
 * card (the dashboard) showed zero acknowledgement of the substitution --
 * still their own subject/period, no substitute name, no "Arrangement"
 * marker. Root cause (option A from the four listed in the fix brief):
 * TeacherDashboardController::todaysPeriodsForTeacher() never queried
 * TeacherSubstitution at all -- the data simply wasn't fetched, so there
 * was nothing for the Blade view to render either way. Mirrors the
 * date+bell_timing_id-keyed "Covered by X" overlay
 * TeacherTimetableController::index() already uses correctly for the
 * weekly view (see TeacherWeeklyTimetableTest::
 * test_arrangement_shows_when_the_teachers_own_period_is_covered),
 * narrowed to today's date only.
 */
class TeacherDashboardSubstitutionArrangementTest extends TestCase
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

    public function test_absent_teachers_own_today_card_shows_the_arrangement(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Sub Dash Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Sub Dash Subject', 'code' => 'SD' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Dash Teacher', 'status' => 'active']);
        $substituteTeacher = Teacher::create(['name' => 'Substitute Dash Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $absentTeacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $creator = User::factory()->create();
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-03', 'absent_teacher_id' => $absentTeacher->id,
            'substitute_teacher_id' => $substituteTeacher->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'bell_timing_id' => $timing->id, 'period_name' => 'P1',
            'status' => 'confirmed', 'created_by' => $creator->id,
        ]);

        $login = $this->login($absentTeacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Sub Dash Subject');
        $response->assertSee('Covered by Substitute Dash Teacher');
    }

    public function test_normal_teachers_today_card_is_unaffected_when_no_substitution_exists(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Plain Dash Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Plain Dash Subject', 'code' => 'PD' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Plain Dash Teacher', 'status' => 'active']);
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
        $response->assertSee('Plain Dash Subject');
        $response->assertDontSee('Covered by');
    }

    public function test_a_substitution_on_a_different_date_does_not_leak_into_today(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'Old Sub Dash Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Old Sub Dash Subject', 'code' => 'OS' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Old Absent Dash Teacher', 'status' => 'active']);
        $substituteTeacher = Teacher::create(['name' => 'Old Substitute Dash Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $absentTeacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $creator = User::factory()->create();
        // A substitution recorded for a DIFFERENT week/date -- must not overlay onto today.
        TeacherSubstitution::create([
            'substitution_date' => '2026-07-27', 'absent_teacher_id' => $absentTeacher->id,
            'substitute_teacher_id' => $substituteTeacher->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'bell_timing_id' => $timing->id, 'period_name' => 'P1',
            'status' => 'confirmed', 'created_by' => $creator->id,
        ]);

        $login = $this->login($absentTeacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertSee('Old Sub Dash Subject');
        $response->assertDontSee('Covered by');
    }
}
