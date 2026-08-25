<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\ExamPaper;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Exams V1 completion pass: a single realistic end-to-end walkthrough of
 * the admin/teacher/student exam narrative, chained through the REAL
 * routes on isolated fixtures this test builds and owns.
 *
 * Every individual behavior here already has dedicated, narrower coverage
 * elsewhere (ExamDependencyProtectionTest, StudentExamPaperAuthorization-
 * Test) -- this suite's value is proving the full CHAIN holds: admin
 * creates an exam -> the assigned teacher adds a paper for it (the real
 * paper-creation path -- Admin\ExamPaperController has no create/store
 * route at all, papers are created by teachers and reviewed/published by
 * admin) -> a result is recorded -> the exam becomes locked against
 * unsafe modification/deletion -> the assigned teacher can see it, an
 * unrelated teacher cannot -> the paper is visible to the right
 * student's class and not to another class's student.
 */
class ExamV1UatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "UAT Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'uatexam' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    public function test_full_admin_teacher_student_exam_lifecycle(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'ExamV1 UAT Class', 'class_order' => 981, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'ExamV1 UAT Other Class', 'class_order' => 982, 'is_active' => true]);
        $subject = Subject::create(['name' => 'ExamV1 UAT Subject', 'code' => 'EV1-' . uniqid(), 'is_active' => true]);

        [$assignedTeacher, $assignedLogin] = $this->teacherLogin('assigned');
        [$outsideTeacher, $outsideLogin] = $this->teacherLogin('outside');

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $assignedTeacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        // ------------------------------------------------------------
        // 1-6. Admin creates an examination: class/section, subject,
        // schedule, max marks, saved.
        // ------------------------------------------------------------
        $create = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'ExamV1 UAT Mid Term', 'exam_type' => 'term', 'class_id' => $class->id,
            'subject' => $subject->name, 'exam_date' => today()->addDays(10)->toDateString(),
            'start_time' => '09:00', 'end_time' => '11:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ]);
        $create->assertSessionHas('success');
        $exam = Exam::where('name', 'ExamV1 UAT Mid Term')->firstOrFail();
        $this->assertSame($class->id, $exam->class_id);

        // 9. Admin can view it.
        $this->actingAs($admin)->get(route('admin.exams.show', $exam))->assertOk();

        // ------------------------------------------------------------
        // 15. The assigned teacher adds a paper for this exam -- the
        // real "configure subjects/papers" path.
        // ------------------------------------------------------------
        $paperResponse = $this->actingAs($assignedLogin, 'teacher')->post(route('teacher.exam-papers.store'), [
            'title' => 'ExamV1 UAT Question Paper', 'exam_id' => $exam->id, 'class_id' => $class->id,
            'subject' => $subject->name,
        ]);
        $paperResponse->assertRedirect();
        $this->assertDatabaseHas('exam_papers', ['exam_id' => $exam->id, 'title' => 'ExamV1 UAT Question Paper']);

        // ------------------------------------------------------------
        // 15. Authorized (assigned) teacher can view the exam.
        // 16. An unrelated teacher (no assignment, not exam head, did
        // not create it) cannot.
        // ------------------------------------------------------------
        $this->actingAs($assignedLogin, 'teacher')->get(route('teacher.exams.show', $exam))->assertOk();

        $outsideAttempt = $this->actingAs($outsideLogin, 'teacher')->get(route('teacher.exams.show', $exam));
        $outsideAttempt->assertRedirect();
        $outsideAttempt->assertSessionHas('error');

        // ------------------------------------------------------------
        // Edit is still safe before any results exist.
        // ------------------------------------------------------------
        $this->actingAs($admin)->put(route('admin.exams.update', $exam), [
            'name' => $exam->name, 'exam_type' => $exam->exam_type, 'class_id' => $class->id,
            'subject' => $subject->name, 'exam_date' => today()->addDays(12)->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 35,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ])->assertSessionHas('success');
        $this->assertSame(35.0, $exam->fresh()->passing_marks);

        // ------------------------------------------------------------
        // 17. A result gets recorded against this exam (marks/results
        // integration -- Exam::results() correctly ties to Result).
        // ------------------------------------------------------------
        $student = \App\Models\Student::create([
            'name' => 'ExamV1 UAT Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class' => $class->name,
        ]);
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => $subject->name,
            'marks_obtained' => 78, 'total_marks' => 100, 'percentage' => 78, 'grade' => 'B',
            'academic_year' => '2026-27',
        ]);
        $this->assertSame(1, $exam->results()->count());

        // ------------------------------------------------------------
        // 7-8, 11. Attempts at invalid modification / unsafe deletion
        // are now rejected -- the exam is locked once results exist.
        // ------------------------------------------------------------
        $invalidEdit = $this->actingAs($admin)->put(route('admin.exams.update', $exam), [
            'name' => $exam->name, 'exam_type' => $exam->exam_type, 'class_id' => $class->id,
            'subject' => $subject->name, 'exam_date' => $exam->exam_date->toDateString(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 200, 'passing_marks' => 35,
            'academic_year' => '2026-27', 'term' => 'Term 1', 'status' => 'active',
        ]);
        $invalidEdit->assertSessionHasErrors('total_marks');
        $this->assertSame(100.0, $exam->fresh()->total_marks);

        $unsafeDelete = $this->actingAs($admin)->delete(route('admin.exams.destroy', $exam));
        $unsafeDelete->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id));

        // ------------------------------------------------------------
        // 12-13. Student/class relationship: the paper is visible to a
        // student in the exam's own class, not to a student elsewhere.
        // ------------------------------------------------------------
        $ownClassUser = User::factory()->create();
        \App\Models\Student::create([
            'name' => 'ExamV1 Own Class Viewer', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class' => $class->name, 'user_id' => $ownClassUser->id,
        ]);
        $otherClassUser = User::factory()->create();
        \App\Models\Student::create([
            'name' => 'ExamV1 Other Class Viewer', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $otherClass->id, 'class' => $otherClass->name, 'user_id' => $otherClassUser->id,
        ]);

        $paper = ExamPaper::where('exam_id', $exam->id)->firstOrFail();
        $paper->update(['is_published' => true]);

        $this->actingAs($ownClassUser)->get(route('student.exam-papers.show', $paper->id))->assertOk();
        $this->actingAs($otherClassUser)->get(route('student.exam-papers.show', $paper->id))->assertForbidden();

        // ------------------------------------------------------------
        // 14. Historical data remains intact after the whole flow.
        // ------------------------------------------------------------
        $this->assertNotNull(Exam::find($exam->id));
        $this->assertSame(1, Result::where('exam_id', $exam->id)->count());
        $this->assertDatabaseHas('exam_papers', ['id' => $paper->id, 'exam_id' => $exam->id]);
    }
}
