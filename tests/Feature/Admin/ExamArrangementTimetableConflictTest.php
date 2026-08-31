<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sync-audit loophole L-03: nothing previously checked whether an
 * invigilator/relieving-duty teacher was already scheduled to teach a
 * different class per the live timetable at the exam's own date/time.
 * Proves ExamTimetableConflictChecker actually blocks that, for both
 * saveInvigilators() and saveRelieving(), and still allows a genuinely
 * free teacher through.
 */
class ExamArrangementTimetableConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);
    }

    private function makeExamOnMonday(): Exam
    {
        $class = SchoolClass::create(['name' => 'Exam Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH' . uniqid()]);

        // 2026-08-31 is a Monday.
        return Exam::create([
            'name' => 'Term Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => '2026-08-31', 'start_time' => '10:00', 'end_time' => '11:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);
    }

    private function busyTeacherOnMonday(): Teacher
    {
        $busyClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Busy Teacher']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P3', 'start_time' => '09:45:00', 'end_time' => '10:45:00',
            'is_active' => true, 'is_break' => false, 'period_type' => 'teaching', 'order_index' => 3,
        ]);
        TimetableSlot::create([
            'school_class_id' => $busyClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        return $teacher;
    }

    public function test_invigilator_teaching_a_class_at_the_exam_time_cannot_be_assigned(): void
    {
        $exam = $this->makeExamOnMonday();
        $teacher = $this->busyTeacherOnMonday();

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.invigilators.save', $exam->id),
            ['duties' => [['room_number' => 'Room 1', 'teacher_id' => $teacher->id, 'role' => 'Invigilator']]]
        );

        $response->assertSessionHasErrors('duties');
        $this->assertDatabaseMissing('exam_invigilator_duties', ['exam_id' => $exam->id, 'teacher_id' => $teacher->id]);
    }

    public function test_invigilator_free_at_the_exam_time_can_be_assigned(): void
    {
        $exam = $this->makeExamOnMonday();
        $freeTeacher = Teacher::create(['name' => 'Free Teacher']);

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.invigilators.save', $exam->id),
            ['duties' => [['room_number' => 'Room 1', 'teacher_id' => $freeTeacher->id, 'role' => 'Invigilator']]]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_invigilator_duties', ['exam_id' => $exam->id, 'teacher_id' => $freeTeacher->id]);
    }

    public function test_relieving_teacher_teaching_a_class_at_the_exam_time_cannot_be_assigned(): void
    {
        $exam = $this->makeExamOnMonday();
        $teacher = $this->busyTeacherOnMonday();

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.relieving.save', $exam->id),
            ['duties' => [['teacher_id' => $teacher->id, 'time_slot' => '10:00-11:00', 'room_number' => 'Room 1']]]
        );

        $response->assertSessionHasErrors('duties');
        $this->assertDatabaseMissing('exam_relieving_duties', ['exam_id' => $exam->id, 'teacher_id' => $teacher->id]);
    }

    public function test_relieving_duty_rejects_the_same_teacher_listed_twice_for_one_time_slot(): void
    {
        $exam = $this->makeExamOnMonday();
        $teacher = Teacher::create(['name' => 'Double Booked']);

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.relieving.save', $exam->id),
            ['duties' => [
                ['teacher_id' => $teacher->id, 'time_slot' => '10:00-11:00', 'room_number' => 'Room 1'],
                ['teacher_id' => $teacher->id, 'time_slot' => '10:00-11:00', 'room_number' => 'Room 2'],
            ]]
        );

        $response->assertSessionHasErrors('duties');
        $this->assertDatabaseMissing('exam_relieving_duties', ['exam_id' => $exam->id]);
    }
}
