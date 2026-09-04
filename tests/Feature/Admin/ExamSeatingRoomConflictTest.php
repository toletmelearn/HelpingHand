<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Priority 1.1: a physical room can't seat two different exams at once.
 * Nothing previously checked a seating generate/save against OTHER exams
 * already booked into that same room at an overlapping date/time --
 * ExamTimetableConflictChecker::roomConflictForExam() closes that, wired
 * into ExamArrangementController::generateSeating()/saveSeating() as a
 * hard block (not a warning): the request is rejected and nothing is
 * written.
 */
class ExamSeatingRoomConflictTest extends TestCase
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

    private function makeExamWithStudent(string $name, string $date, string $start, string $end): Exam
    {
        $class = SchoolClass::create(['name' => $name.' Class', 'class_order' => random_int(1000, 999999), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MTH'.uniqid()]);

        $exam = Exam::create([
            'name' => $name, 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name,
            'exam_date' => $date, 'start_time' => $start, 'end_time' => $end,
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        Student::create([
            'name' => $name.' Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class_id' => $class->id,
        ]);

        return $exam;
    }

    private function seatExam(Exam $exam, string $room): void
    {
        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $exam->id),
            ['room_number' => $room, 'start_number' => 1]
        );
        $response->assertSessionHasNoErrors();
    }

    public function test_generate_seating_is_blocked_when_room_already_booked_by_another_exam_same_period(): void
    {
        $examA = $this->makeExamWithStudent('Exam A', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '10:30', '11:30');

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $examB->id),
            ['room_number' => 'Room 5', 'start_number' => 1]
        );

        $response->assertSessionHasErrors('room_number');
        $this->assertDatabaseMissing('exam_seating_arrangements', ['exam_id' => $examB->id]);
    }

    public function test_generate_seating_succeeds_when_room_is_free(): void
    {
        $examA = $this->makeExamWithStudent('Exam A', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '10:30', '11:30');

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $examB->id),
            ['room_number' => 'Room 6', 'start_number' => 1]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_seating_arrangements', ['exam_id' => $examB->id, 'room_number' => 'Room 6']);
    }

    public function test_same_room_at_a_non_overlapping_time_is_not_a_conflict(): void
    {
        $examA = $this->makeExamWithStudent('Exam A', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '13:00', '14:00');

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $examB->id),
            ['room_number' => 'Room 5', 'start_number' => 1]
        );

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('exam_seating_arrangements', ['exam_id' => $examB->id, 'room_number' => 'Room 5']);
    }

    public function test_manual_save_seating_is_also_blocked_by_room_conflict(): void
    {
        $examA = $this->makeExamWithStudent('Exam A', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '10:30', '11:30');
        $studentB = Student::where('school_class_id', $examB->class_id)->firstOrFail();

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.save', $examB->id),
            ['seating' => [[
                'student_id' => $studentB->id, 'room_number' => 'Room 5', 'seat_number' => 'Seat-1',
            ]]]
        );

        $response->assertSessionHasErrors('seating');
        $this->assertDatabaseMissing('exam_seating_arrangements', ['exam_id' => $examB->id]);
    }

    public function test_conflict_error_message_names_the_room_and_the_blocking_exam(): void
    {
        $examA = $this->makeExamWithStudent('Physics Term Exam', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '10:30', '11:30');

        $response = $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $examB->id),
            ['room_number' => 'Room 5', 'start_number' => 1]
        );

        $errors = session('errors')->get('room_number');
        $this->assertStringContainsString('Room 5', $errors[0]);
        $this->assertStringContainsString('Physics Term Exam', $errors[0]);
    }

    public function test_room_conflict_prevention_is_audit_logged(): void
    {
        $examA = $this->makeExamWithStudent('Exam A', '2026-09-07', '10:00', '11:00');
        $this->seatExam($examA, 'Room 5');

        $examB = $this->makeExamWithStudent('Exam B', '2026-09-07', '10:30', '11:30');

        $this->actingAs($this->admin)->post(
            route('admin.exams.arrangements.seating.generate', $examB->id),
            ['room_number' => 'Room 5', 'start_number' => 1]
        );

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => Exam::class,
            'model_id' => $examB->id,
            'action' => 'room_conflict_blocked',
            'field_name' => 'room_number',
            'new_value' => 'Room 5',
        ]);
    }
}
