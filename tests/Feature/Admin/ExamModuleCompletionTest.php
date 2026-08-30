<?php

namespace Tests\Feature\Admin;

use App\Models\CBSEResult;
use App\Models\Exam;
use App\Models\GradingSystem;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exam Module Completion pass. Three fixes, each verified against the
 * REAL live path a teacher/admin actually hits (not just the underlying
 * model method):
 *
 * 1. TeacherMarksController::store() previously validated
 *    'marks.*.student_id' with nothing stronger than exists:students,id --
 *    never that the student is actually enrolled in the exam's own class.
 *    The real form only ever offers legitimately-enrolled students, but a
 *    tampered/replayed POST could attach marks to a student in an
 *    unrelated class. Now rejected server-side.
 *
 * 2. Teacher\TeacherExamPaperController::store() had zero ownership check
 *    at all -- any authenticated teacher could attach an exam-paper record
 *    to any class/exam via a crafted class_id. create() also listed every
 *    exam/class in the school unfiltered. Both now scoped to the teacher's
 *    real TeacherClassSubjectAssignment rows, matching the same pattern
 *    TeacherMarksController/TeacherExamController already used.
 *
 * 3. GradingSystem::gradeFor() was wired into Result::determineGrade() and
 *    TeacherMarksController::calculateGrade() earlier this session, but the
 *    AGGREGATE grade actually shown on the report card a real admin opens
 *    (Admin\ResultController::calculateCBSEGrade(), via
 *    generateFinalResult()/the admin.results.report-card route) still had
 *    its own separate hardcoded ladder, as did the parallel CBSE-track
 *    pipeline (root ResultController::calculateOverallGrade(),
 *    CBSEResult::determineGrade()). All three now consult GradingSystem
 *    first, falling back to their existing default ladder when
 *    unconfigured -- same safe-fallback pattern as the earlier fix.
 *
 * Deliberately NOT touched (per this pass's own audit): academic-year
 * isolation in TeacherMarksController/TeacherExamController -- real DB data
 * showed exam.academic_year ("2025-2026") and
 * teacher_class_subject_assignments.academic_year ("2026-2027", plus a
 * leftover "...-WALKTHROUGH-ARCHIVED" format and a stray "2026-27") are
 * NOT consistently formatted even among legitimate rows; an exact-string
 * year filter would have locked every real teacher out of the only 3 real
 * exams in the system. Flagged as a real but not safely fixable-today
 * limitation rather than "fixed" with a filter that breaks more than it
 * closes.
 */
class ExamModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function student(SchoolClass $class): Student
    {
        return Student::create([
            'name' => 'EM Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "EM Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'em' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    // --- 1. Student eligibility on marks entry ------------------------------

    public function test_teacher_marks_store_rejects_a_student_not_enrolled_in_the_exam_class(): void
    {
        $class = SchoolClass::create(['name' => 'EM Class A', 'class_order' => 990901, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'EM Class B', 'class_order' => 990902, 'is_active' => true]);
        $subject = Subject::create(['name' => 'EM Subject A', 'code' => 'EM-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam A', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $outsideStudent = $this->student($otherClass); // enrolled in a DIFFERENT class
        [$teacher, $login] = $this->teacherLogin('elig');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $outsideStudent->id, 'marks_obtained' => 80],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('results', ['student_id' => $outsideStudent->id, 'exam_id' => $exam->id]);
    }

    public function test_teacher_marks_store_still_accepts_a_student_actually_enrolled(): void
    {
        $class = SchoolClass::create(['name' => 'EM Class C', 'class_order' => 990903, 'is_active' => true]);
        $subject = Subject::create(['name' => 'EM Subject C', 'code' => 'EM-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam C', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('eligok');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 80],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('results', ['student_id' => $student->id, 'exam_id' => $exam->id, 'marks_obtained' => 80]);
    }

    // --- 2. Exam paper upload ownership --------------------------------------

    public function test_teacher_exam_paper_store_rejects_a_class_the_teacher_is_not_assigned_to(): void
    {
        $assignedClass = SchoolClass::create(['name' => 'EM Class D', 'class_order' => 990904, 'is_active' => true]);
        $unassignedClass = SchoolClass::create(['name' => 'EM Class E', 'class_order' => 990905, 'is_active' => true]);
        $subject = Subject::create(['name' => 'EM Subject D', 'code' => 'EM-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam D', 'exam_type' => 'term', 'class_id' => $unassignedClass->id,
            'class_name' => $unassignedClass->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        [$teacher, $login] = $this->teacherLogin('paperauth');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $assignedClass->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.exam-papers.store'), [
            'title' => 'Sneaky Paper', 'exam_id' => $exam->id, 'class_id' => $unassignedClass->id,
            'subject' => $subject->name,
        ])->assertRedirect();

        $this->assertDatabaseMissing('exam_papers', ['title' => 'Sneaky Paper']);
    }

    public function test_teacher_exam_paper_store_accepts_a_class_the_teacher_is_assigned_to(): void
    {
        $class = SchoolClass::create(['name' => 'EM Class F', 'class_order' => 990906, 'is_active' => true]);
        $subject = Subject::create(['name' => 'EM Subject F', 'code' => 'EM-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam F', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        [$teacher, $login] = $this->teacherLogin('paperok');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.exam-papers.store'), [
            'title' => 'Legit Paper', 'exam_id' => $exam->id, 'class_id' => $class->id,
            'subject' => $subject->name,
        ])->assertRedirect(route('teacher.exam-papers.index'));

        $this->assertDatabaseHas('exam_papers', ['title' => 'Legit Paper', 'class_id' => $class->id]);
    }

    public function test_teacher_exam_paper_create_only_lists_assigned_classes(): void
    {
        $assignedClass = SchoolClass::create(['name' => 'EM Class G', 'class_order' => 990907, 'is_active' => true]);
        $unassignedClass = SchoolClass::create(['name' => 'EM Class H', 'class_order' => 990908, 'is_active' => true]);
        $subject = Subject::create(['name' => 'EM Subject G', 'code' => 'EM-' . uniqid(), 'is_active' => true]);
        [$teacher, $login] = $this->teacherLogin('papercreate');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $assignedClass->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.exam-papers.create'));

        $response->assertOk();
        $response->assertSee('EM Class G');
        $response->assertDontSee('EM Class H');
    }

    // --- 3. GradingSystem wired into the aggregate/report-card grade --------

    public function test_report_card_overall_grade_uses_configured_grading_system(): void
    {
        GradingSystem::create([
            'name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 80, 'is_active' => true, 'order' => 1,
        ]);

        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'EM Class I', 'class_order' => 990909, 'is_active' => true]);
        $mathSubject = Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam I', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $mathSubject->id, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        // 85% overall -- the default ladder would call this 'A1'.
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 85, 'total_marks' => 100, 'percentage' => 85, 'grade' => 'A1',
            'academic_year' => '2026-27', 'result_status' => 'pass',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.results.report-card', [$student->id, $exam->id]));

        $response->assertOk();
        $response->assertSee('Distinction');
    }

    public function test_report_card_overall_grade_falls_back_to_default_ladder_when_unconfigured(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'EM Class J', 'class_order' => 990910, 'is_active' => true]);
        $mathSubject = Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'EM Exam J', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject_id' => $mathSubject->id, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 85, 'total_marks' => 100, 'percentage' => 85, 'grade' => 'A1',
            'academic_year' => '2026-27', 'result_status' => 'pass',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.results.report-card', [$student->id, $exam->id]));

        $response->assertOk();
        $response->assertSee('A1');
    }

    public function test_cbse_result_determine_grade_uses_configured_grading_system(): void
    {
        GradingSystem::create([
            'name' => 'Custom', 'grade' => 'Distinction', 'min_percentage' => 80, 'is_active' => true, 'order' => 1,
        ]);

        $cbse = new CBSEResult(['percentage' => 85]);

        $this->assertSame('Distinction', $cbse->determineGrade());
    }
}
