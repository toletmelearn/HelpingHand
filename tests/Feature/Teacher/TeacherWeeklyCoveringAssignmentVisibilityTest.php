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
 * UAT Step 5, Scenario 16: a substitute-only teacher (covering duty this
 * week, but no regular timetable slots of their own) hit
 * count($days) === 0 and the weekly view rendered the "No published
 * timetable periods are assigned to you yet" empty state -- which,
 * before this fix, completely hid the "Additional Covering Assignments"
 * card, even though TeacherTimetableController::index() already computed
 * $coveringAssignments correctly. Root cause was Blade control flow only
 * (resources/views/teacher/timetable/index.blade.php): the covering-
 * assignments block was nested inside the @else branch that only runs
 * when the teacher has regular days.
 */
class TeacherWeeklyCoveringAssignmentVisibilityTest extends TestCase
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

    public function test_substitute_only_teacher_sees_covering_assignment_instead_of_only_the_empty_state(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday

        $class = SchoolClass::create(['name' => 'Cover Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Cover Subject', 'code' => 'CV' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Cover Teacher', 'status' => 'active']);
        // The substitute has ZERO regular TimetableSlot rows of their own.
        $substituteTeacher = Teacher::create(['name' => 'Substitute Only Teacher', 'status' => 'active']);

        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
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

        $login = $this->login($substituteTeacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Additional Covering Assignments This Week');
        $response->assertSee('Cover Class');
        $response->assertSee('Cover Subject');
        $response->assertSee('Absent Cover Teacher'); // "Covering For" column
    }

    public function test_normal_teacher_with_regular_timetable_still_works(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');

        $class = SchoolClass::create(['name' => 'Normal Cover Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Normal Cover Subject', 'code' => 'NC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Normal Cover Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $login = $this->login($teacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Normal Cover Subject');
        $response->assertDontSee('Additional Covering Assignments This Week');
    }

    public function test_teacher_with_both_a_regular_timetable_and_a_covering_assignment_sees_both(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday

        $ownClass = SchoolClass::create(['name' => 'Own Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $ownSubject = Subject::create(['name' => 'Own Subject', 'code' => 'OW' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Busy Teacher', 'status' => 'active']);
        $ownTiming = BellTiming::create([
            'day_of_week' => 'Tuesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        TimetableSlot::create([
            'school_class_id' => $ownClass->id, 'bell_timing_id' => $ownTiming->id,
            'subject_id' => $ownSubject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $coverClass = SchoolClass::create(['name' => 'Extra Cover Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $coverSubject = Subject::create(['name' => 'Extra Cover Subject', 'code' => 'EC' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Extra Absent Teacher', 'status' => 'active']);
        $coverTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ]);
        $section = Section::create(['name' => 'A', 'class_id' => $coverClass->id]);
        $creator = User::factory()->create();
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-03', 'absent_teacher_id' => $absentTeacher->id,
            'substitute_teacher_id' => $teacher->id, 'class_id' => $coverClass->id, 'section_id' => $section->id,
            'subject_id' => $coverSubject->id, 'bell_timing_id' => $coverTiming->id, 'period_name' => 'P1',
            'status' => 'confirmed', 'created_by' => $creator->id,
        ]);

        $login = $this->login($teacher);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertSee('Own Subject');
        $response->assertSee('Additional Covering Assignments This Week');
        $response->assertSee('Extra Cover Subject');
    }

    public function test_teacher_does_not_see_another_teachers_covering_assignment(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');

        $class = SchoolClass::create(['name' => 'Leak Cover Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Leak Cover Subject', 'code' => 'LC' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Leak Absent Teacher', 'status' => 'active']);
        $realSubstitute = Teacher::create(['name' => 'Real Substitute', 'status' => 'active']);
        $bystander = Teacher::create(['name' => 'Bystander Teacher', 'status' => 'active']);

        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
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
            'substitute_teacher_id' => $realSubstitute->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'bell_timing_id' => $timing->id, 'period_name' => 'P1',
            'status' => 'confirmed', 'created_by' => $creator->id,
        ]);

        $login = $this->login($bystander);
        $response = $this->actingAs($login, 'teacher')->get(route('teacher.timetable'));

        $response->assertOk();
        $response->assertDontSee('Additional Covering Assignments This Week');
        $response->assertDontSee('Leak Cover Subject');
    }
}
