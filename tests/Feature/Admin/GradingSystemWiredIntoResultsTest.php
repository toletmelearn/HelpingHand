<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\GradingSystem;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Academic setup completion pass: GradingSystem previously had a full admin
 * CRUD (grading-systems.*) whose configured bands had zero effect on real
 * results -- Result::determineGrade() and TeacherMarksController's separate
 * hardcoded calculateGrade() both ignored it entirely. Both now consult
 * GradingSystem::gradeFor() first and only fall back to their own default
 * ladder when no active band matches, so a school that never configures one
 * keeps behaving exactly as before.
 */
class GradingSystemWiredIntoResultsTest extends TestCase
{
    use RefreshDatabase;

    private function student(SchoolClass $class): Student
    {
        return Student::create([
            'name' => 'GS Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "GS Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'gs' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    // --- GradingSystem::gradeFor() ------------------------------------------

    public function test_grade_for_returns_null_when_no_active_bands_exist(): void
    {
        $this->assertNull(GradingSystem::gradeFor(85));
    }

    public function test_grade_for_ignores_an_inactive_band(): void
    {
        GradingSystem::create([
            'name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 80,
            'max_percentage' => 100, 'is_active' => false, 'order' => 1,
        ]);

        $this->assertNull(GradingSystem::gradeFor(85));
    }

    public function test_grade_for_returns_the_highest_qualifying_active_band(): void
    {
        GradingSystem::create(['name' => 'Custom', 'grade' => 'Pass', 'min_percentage' => 40, 'is_active' => true, 'order' => 1]);
        GradingSystem::create(['name' => 'Custom', 'grade' => 'Merit', 'min_percentage' => 70, 'is_active' => true, 'order' => 2]);
        GradingSystem::create(['name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 90, 'is_active' => true, 'order' => 3]);

        $this->assertSame('Distinction', GradingSystem::gradeFor(95));
        $this->assertSame('Merit', GradingSystem::gradeFor(75));
        $this->assertSame('Pass', GradingSystem::gradeFor(50));
        $this->assertNull(GradingSystem::gradeFor(20));
    }

    // --- Result::determineGrade() -------------------------------------------

    public function test_result_determine_grade_uses_configured_band_over_default_ladder(): void
    {
        // 85% would be the default ladder's 'A2', not this custom label.
        GradingSystem::create([
            'name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 80, 'is_active' => true, 'order' => 1,
        ]);

        $result = new Result(['marks_obtained' => 85, 'total_marks' => 100]);

        $this->assertSame('Distinction', $result->determineGrade());
    }

    public function test_result_determine_grade_falls_back_to_default_ladder_when_unconfigured(): void
    {
        $result = new Result(['marks_obtained' => 85, 'total_marks' => 100]);

        $this->assertSame('A2', $result->determineGrade());
    }

    // --- TeacherMarksController::store() (the real, live marks-entry path) --

    public function test_teacher_marks_store_uses_configured_grading_system(): void
    {
        GradingSystem::create([
            'name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 80, 'is_active' => true, 'order' => 1,
        ]);

        $class = SchoolClass::create(['name' => 'GS Class A', 'class_order' => 991, 'is_active' => true]);
        $subject = Subject::create(['name' => 'GS Subject A', 'code' => 'GS-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'GS Exam A', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('gradewired');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        // 85% -- the default ladder would call this 'A1', not the configured label.
        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 85],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('results', [
            'student_id' => $student->id, 'exam_id' => $exam->id, 'grade' => 'Distinction',
        ]);
    }

    public function test_teacher_marks_store_falls_back_to_default_ladder_when_unconfigured(): void
    {
        $class = SchoolClass::create(['name' => 'GS Class B', 'class_order' => 992, 'is_active' => true]);
        $subject = Subject::create(['name' => 'GS Subject B', 'code' => 'GS-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'GS Exam B', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('gradedefault');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 92],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('results', [
            'student_id' => $student->id, 'exam_id' => $exam->id, 'grade' => 'A1',
        ]);
    }
}
