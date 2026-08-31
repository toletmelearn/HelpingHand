<?php

namespace Tests\Feature\Student;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolHoliday;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sync-audit / Phase 2.3: a student-facing timetable view didn't exist at
 * all -- mirrors Parent\TimetableController's already-tested pattern
 * (server-resolved own class/section, published slots only), adapted to
 * the student's own web-guard account (Auth::user()->student).
 */
class StudentTimetableViewTest extends TestCase
{
    use RefreshDatabase;

    private function studentUser(SchoolClass $class, ?Section $section = null): User
    {
        $student = Student::create([
            'name' => 'STV Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section?->id,
        ]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $user->student()->save($student);

        return $user;
    }

    public function test_authenticated_student_can_view_own_timetable(): void
    {
        $class = SchoolClass::create(['name' => 'STV Class A', 'class_order' => 992001, 'is_active' => true]);
        $section = Section::create(['name' => 'STV-A']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'STV Subject', 'code' => 'STV-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'STV Teacher']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $user = $this->studentUser($class, $section);

        $response = $this->actingAs($user)->get(route('student.timetable.weekly'));

        $response->assertOk();
        $response->assertSee('Monday');
        $response->assertSee('STV Subject');
    }

    public function test_student_cannot_see_a_different_class_timetable(): void
    {
        $ownClass = SchoolClass::create(['name' => 'STV Class B', 'class_order' => 992002, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'STV Class C', 'class_order' => 992003, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Other Subject', 'code' => 'STV-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Other Teacher']);
        $timing = BellTiming::create([
            'day_of_week' => 'Tuesday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $user = $this->studentUser($ownClass);

        $response = $this->actingAs($user)->get(route('student.timetable.weekly'));

        $response->assertOk();
        $response->assertDontSee('Other Subject');
    }

    public function test_unauthenticated_user_cannot_view_timetable(): void
    {
        $this->get(route('student.timetable.today'))->assertRedirect(route('login'));
        $this->get(route('student.timetable.weekly'))->assertRedirect(route('login'));
    }

    public function test_today_view_shows_correct_days_schedule(): void
    {
        $class = SchoolClass::create(['name' => 'STV Class D', 'class_order' => 992004, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Today Subject', 'code' => 'STV-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Today Teacher']);
        $todayName = now()->format('l');
        $timing = BellTiming::create([
            'day_of_week' => $todayName, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $user = $this->studentUser($class);

        $response = $this->actingAs($user)->get(route('student.timetable.today'));

        $response->assertOk();
        $response->assertSee('Today Subject');
    }

    public function test_holiday_is_marked_and_no_periods_shown_today(): void
    {
        $class = SchoolClass::create(['name' => 'STV Class E', 'class_order' => 992005, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Holiday Subject', 'code' => 'STV-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Holiday Teacher']);
        $todayName = now()->format('l');
        $timing = BellTiming::create([
            'day_of_week' => $todayName, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        SchoolHoliday::create([
            'academic_year' => '2026-27', 'holiday_name' => 'Test Holiday',
            'start_date' => now()->toDateString(), 'end_date' => now()->toDateString(), 'holiday_type' => 'special',
        ]);

        $user = $this->studentUser($class);

        $response = $this->actingAs($user)->get(route('student.timetable.today'));

        $response->assertOk();
        $response->assertSee('school holiday');
        $response->assertDontSee('Holiday Subject');
    }

    public function test_teacher_substitution_is_shown_as_an_arrangement(): void
    {
        $class = SchoolClass::create(['name' => 'STV Class F', 'class_order' => 992006, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Arr Subject', 'code' => 'STV-' . uniqid(), 'is_active' => true]);
        $original = Teacher::create(['name' => 'Original Teacher']);
        $substitute = Teacher::create(['name' => 'Substitute Teacher']);
        $todayName = now()->format('l');
        $timing = BellTiming::create([
            'day_of_week' => $todayName, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 1,
        ]);
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $original->id, 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        \App\Models\TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'), 'absent_teacher_id' => $original->id,
            'substitute_teacher_id' => $substitute->id, 'class_id' => $class->id,
            'section_id' => \App\Models\Section::firstOrCreate(['name' => 'STV-F-sec'])->id,
            'subject_id' => $subject->id, 'bell_timing_id' => $timing->id, 'status' => 'assigned', 'created_by' => 1,
        ]);

        $user = $this->studentUser($class);

        $response = $this->actingAs($user)->get(route('student.timetable.today'));

        $response->assertOk();
        $response->assertSee('Substitute Teacher');
        $response->assertSee('Arrangement');
    }
}
