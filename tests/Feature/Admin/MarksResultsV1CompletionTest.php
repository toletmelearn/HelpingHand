<?php

namespace Tests\Feature\Admin;

use App\Models\CBSEResult;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Marks + Results V1 completion pass.
 *
 * Root cause behind most of these: Result::$fillable was missing
 * is_verified/verified_by/verified_at/verification_comments/class_id/
 * subject_id/locked_at/approved_by/approved_at -- mass-assignment silently
 * dropped every one of them, so the entire verify/lock protection chain
 * (ResultEntryController, ResultVerificationController::bulkVerify, the
 * report-card "is_verified" filter) was a no-op: clicking "verify" reported
 * success but never actually set is_verified, so verified marks could still
 * be edited and no report card could ever find a verified result.
 *
 * Also covers: TeacherMarksController::store() overwriting locked marks
 * with no check, its hardcoded academic_year, missing marks<=total_marks
 * validation on every mark-entry path, the hardcoded 33% pass threshold on
 * both Result::updateResultStatus() and CBSEResult::autoCalculate() (ignoring
 * exam.passing_marks), the missing ResultPolicy::verify ability, and a
 * sweep of Marks/Results controllers (AdminMarksController,
 * ResultMonitorController, MarksModerationController, EnhancedResultController)
 * that had zero authorization at all -- matching the same gap class already
 * fixed for six other back-office controllers this session.
 *
 * Also discovered: a second, entirely separate, fully-routed result system
 * (CBSEResult / root ResultController) exists in parallel to the Result
 * system investigated above. Not merged -- that would be a redesign.
 */
class MarksResultsV1CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function clerk(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function student(SchoolClass $class): Student
    {
        return Student::create([
            'name' => 'MR Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id,
        ]);
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "MR Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'mr' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    // --- Fillable / verification chain -----------------------------------

    public function test_bulk_verify_actually_persists_is_verified(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'MR Class A', 'class_order' => 971, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam A', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $result = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A1',
            'academic_year' => '2026-27', 'result_status' => 'pass',
        ]);

        $this->actingAs($admin)->post(route('results.verification.bulk-verify'), [
            'result_ids' => [$result->id],
        ])->assertRedirect();

        $result->refresh();
        $this->assertTrue((bool) $result->is_verified);
        $this->assertEquals($admin->id, $result->verified_by);
        $this->assertNotNull($result->verified_at);
    }

    public function test_verified_result_cannot_be_edited_via_entry_controller(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'MR Class B', 'class_order' => 972, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam B', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MR-' . uniqid(), 'is_active' => true]);
        $result = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A1',
            'academic_year' => '2026-27', 'result_status' => 'pass',
            'is_verified' => true, 'verified_by' => $admin->id, 'verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('results.entry.update', $result), [
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject_id' => $subject->id,
            'marks_obtained' => 95, 'total_marks' => 100, 'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);
        $response->assertRedirect();

        $result->refresh();
        $this->assertEquals(80, $result->marks_obtained); // unchanged
    }

    // --- max marks validation ----------------------------------------------

    public function test_entry_controller_rejects_marks_obtained_above_total_marks(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'MR Class C', 'class_order' => 973, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam C', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MR-' . uniqid(), 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('results.entry.store'), [
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject_id' => $subject->id,
            'marks_obtained' => 150, 'total_marks' => 100, 'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);

        $response->assertSessionHasErrors('marks_obtained');
        $this->assertDatabaseMissing('results', ['student_id' => $student->id, 'exam_id' => $exam->id]);
    }

    // --- Result::updateResultStatus() honors exam.passing_marks ------------

    public function test_update_result_status_uses_exam_passing_marks_not_hardcoded_33_percent(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class D', 'class_order' => 974, 'is_active' => true]);
        // Passing threshold is 50%, well above the old hardcoded 33%.
        $exam = Exam::create([
            'name' => 'MR Exam D', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 50,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $result = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 40, 'total_marks' => 100, 'percentage' => 40, 'grade' => 'D',
            'academic_year' => '2026-27',
        ]);

        $result->updateResultStatus();

        // 40% would have passed under the old hardcoded 33% rule -- must fail now.
        $this->assertEquals('fail', $result->fresh()->result_status);
    }

    public function test_cbse_result_auto_calculate_uses_exam_passing_marks(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class E', 'class_order' => 975, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam E', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 50,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $subject = Subject::create(['name' => 'Math CBSE', 'code' => 'MR-' . uniqid(), 'is_active' => true]);

        $cbse = new CBSEResult([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject_id' => $subject->id,
            'pt_marks' => 10, 'notebook_marks' => 2, 'sea_marks' => 2, 'exam_marks' => 26, // total 40%
            'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);
        $cbse->autoCalculate();

        $this->assertEquals('fail', $cbse->result_status);
    }

    // --- TeacherMarksController fixes ---------------------------------------

    public function test_teacher_cannot_overwrite_locked_marks(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class F', 'class_order' => 976, 'is_active' => true]);
        $subject = Subject::create(['name' => 'MR Teacher Subject', 'code' => 'MR-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam F', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('lock');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $existing = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => $subject->name,
            'marks_obtained' => 70, 'total_marks' => 100, 'percentage' => 70, 'grade' => 'B1',
            'academic_year' => '2026-27', 'is_locked' => true,
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 10],
            ],
        ])->assertRedirect();

        $this->assertEquals(70, $existing->fresh()->marks_obtained);
    }

    public function test_teacher_marks_store_rejects_marks_above_total(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class G', 'class_order' => 977, 'is_active' => true]);
        $subject = Subject::create(['name' => 'MR Teacher Subject G', 'code' => 'MR-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam G', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 50, 'passing_marks' => 17,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('overmax');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 999],
            ],
        ])->assertRedirect();

        $this->assertDatabaseMissing('results', ['student_id' => $student->id, 'exam_id' => $exam->id]);
    }

    public function test_teacher_marks_store_uses_exam_academic_year(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class H', 'class_order' => 978, 'is_active' => true]);
        $subject = Subject::create(['name' => 'MR Teacher Subject H', 'code' => 'MR-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam H', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2019-2020', 'status' => 'completed', // deliberately not the current year
        ]);
        $student = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('year');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2019-2020',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $student->id, 'marks_obtained' => 60],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('results', [
            'student_id' => $student->id, 'exam_id' => $exam->id, 'academic_year' => '2019-2020',
        ]);
    }

    /**
     * V1 integration pass finding: results.result_status defaults to
     * 'pass' at the schema level, and TeacherMarksController::store()'s
     * updateOrCreate() data array never set it -- every mark entered
     * through the live teacher-facing route was silently recorded as a
     * pass regardless of the actual score.
     */
    public function test_teacher_marks_store_correctly_computes_pass_and_fail(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class M', 'class_order' => 983, 'is_active' => true]);
        $subject = Subject::create(['name' => 'MR Teacher Subject M', 'code' => 'MR-' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam M', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 40,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $passingStudent = $this->student($class);
        $failingStudent = $this->student($class);
        [$teacher, $login] = $this->teacherLogin('passfail');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($login, 'teacher')->post(route('teacher.marks.store'), [
            'exam_id' => $exam->id,
            'marks' => [
                ['student_id' => $passingStudent->id, 'marks_obtained' => 85],
                ['student_id' => $failingStudent->id, 'marks_obtained' => 20],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('results', [
            'student_id' => $passingStudent->id, 'exam_id' => $exam->id, 'result_status' => 'pass',
        ]);
        $this->assertDatabaseHas('results', [
            'student_id' => $failingStudent->id, 'exam_id' => $exam->id, 'result_status' => 'fail',
        ]);
    }

    // --- ResultPolicy::verify ability ---------------------------------------

    public function test_non_admin_without_permission_cannot_verify_a_result(): void
    {
        $clerk = $this->clerk();
        $class = SchoolClass::create(['name' => 'MR Class I', 'class_order' => 979, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam I', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $result = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A1',
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($clerk)->post(route('results.entry.verify', $result))->assertForbidden();
        $this->assertFalse((bool) $result->fresh()->is_verified);
    }

    // --- Authorization sweep: previously-unguarded controllers -------------

    public function test_admin_marks_controller_requires_authorization(): void
    {
        $this->actingAs($this->clerk())->get(route('admin.exams.marks.index'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.exams.marks.index'))->assertOk();
    }

    public function test_result_monitor_controller_requires_authorization(): void
    {
        $this->actingAs($this->clerk())->get(route('admin.result.monitor'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('admin.result.monitor'))->assertOk();
    }

    public function test_marks_moderation_write_actions_require_authorization(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class J', 'class_order' => 980, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam J', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);

        $this->actingAs($this->clerk())->post(route('admin.exams.moderation.moderate'), [
            'exam_id' => $exam->id, 'subject' => 'Math', 'adjustment_percentage' => 10,
        ])->assertForbidden();
    }

    public function test_enhanced_result_controller_requires_authorization(): void
    {
        $this->actingAs($this->clerk())->get(route('admin.enhanced-results.index'))->assertForbidden();

        // Every view this controller renders (admin.results.enhanced-index and
        // its siblings) is missing from resources/views -- a pre-existing,
        // separate defect (the feature has never been renderable for anyone,
        // admin included) that's out of scope for a minimum-safe-fix pass, so
        // we don't assert 200 here. What matters for this fix is that an admin
        // is no longer blocked by the (newly added) authorization check.
        $adminResponse = $this->actingAs($this->admin())->get(route('admin.enhanced-results.index'));
        $this->assertNotEquals(403, $adminResponse->getStatusCode());
    }

    // --- Admin\ResultController::generateReportCard() no longer a dead stub -

    public function test_admin_report_card_route_renders_instead_of_crashing(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'MR Class K', 'class_order' => 981, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam K', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A1',
            'academic_year' => '2026-27',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.results.report-card', [$student->id, $exam->id]))
            ->assertOk();
    }

    // --- CBSEResult bulk upload no longer lies about success ----------------

    public function test_cbse_bulk_upload_reports_honest_error_not_false_success(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => 'MR Class L', 'class_order' => 982, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam L', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('marks.csv', 10);

        $response = $this->actingAs($admin)->post(route('results.bulk-upload'), [
            'file' => $file, 'exam_id' => $exam->id, 'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');
    }

    /**
     * V1 integration pass finding: resources/views/student/results/show.blade.php
     * and .../pdf.blade.php did not exist -- StudentResultController::show()/
     * generatePDF() (correctly ownership-checked) 500'd for every student,
     * even though student.results.index already links to both.
     */
    public function test_student_can_view_and_print_their_own_result(): void
    {
        $class = SchoolClass::create(['name' => 'MR Class N', 'class_order' => 984, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'MR Exam N', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->student($class);
        $studentUser = User::factory()->create(['role' => 'student']);
        $studentUser->roles()->attach(Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student'])->id);
        $studentUser->student()->save($student);
        $result = Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => 'Math',
            'marks_obtained' => 75, 'total_marks' => 100, 'percentage' => 75, 'grade' => 'A2',
            'academic_year' => '2026-27', 'result_status' => 'pass',
        ]);

        $this->actingAs($studentUser)->get(route('student.results.show', $result))->assertOk();
        $this->actingAs($studentUser)->get(route('student.results.generate-pdf', $result))->assertOk();
    }
}
