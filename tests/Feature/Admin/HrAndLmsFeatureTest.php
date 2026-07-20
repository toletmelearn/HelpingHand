<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\Student;
use App\Models\TeacherLeave;
use App\Models\TeacherSalary;
use App\Models\HomeworkNotice;
use App\Models\HomeworkSubmission;
use App\Models\MedicalRecord;
use App\Models\MedicalCheckup;
use App\Models\DisciplinaryIncident;
use App\Models\DisciplinaryAction;
use App\Models\NotebookCheck;
use App\Models\StudyMaterial;
use App\Models\OnlineQuiz;
use App\Models\QuizQuestion;
use App\Models\PtmMeeting;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class HrAndLmsFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        config(['session.driver' => 'array']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        $schema->create('audit_logs', function ($table) {
            $table->bigIncrements('id');
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
        });

        $schema->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('teachers', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('employee_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('teacher_logins', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('roll_number')->nullable();
            $table->string('admission_no')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('class')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('teacher_leaves', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id');
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('days');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();
        });

        $schema->create('teacher_salaries', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedTinyInteger('pay_month')->nullable();
            $table->unsignedSmallInteger('pay_year')->nullable();
            $table->string('pay_scale')->nullable();
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('hra', 10, 2)->nullable();
            $table->decimal('da', 10, 2)->nullable();
            $table->decimal('ta', 10, 2)->nullable();
            $table->decimal('medical_allowance', 10, 2)->nullable();
            $table->decimal('special_allowance', 10, 2)->nullable();
            $table->decimal('gross_salary', 10, 2);
            $table->decimal('pf_amount', 10, 2)->nullable();
            $table->decimal('esi_amount', 10, 2)->nullable();
            $table->decimal('tax_deduction', 10, 2)->nullable();
            $table->decimal('other_deductions', 10, 2)->nullable();
            $table->decimal('attendance_deduction_days', 5, 2)->nullable();
            $table->decimal('attendance_deduction_amount', 10, 2)->nullable();
            $table->decimal('net_salary', 10, 2);
            $table->string('payment_status')->default('pending');
            $table->date('payment_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reference_number')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });

        $schema->create('homework_notices', function ($table) {
            $table->bigIncrements('id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('notice'); // homework, notice, announcement
            $table->unsignedBigInteger('class_id')->nullable();
            $table->date('due_date')->nullable();
            $table->date('assign_date')->nullable();
            $table->string('subject_name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('homework_submissions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('homework_notice_id');
            $table->unsignedBigInteger('student_id');
            $table->timestamp('submission_date')->nullable();
            $table->string('file_path')->nullable();
            $table->text('student_notes')->nullable();
            $table->decimal('marks_obtained', 5, 2)->nullable();
            $table->string('grade')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('evaluated_by')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('subjects', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('exams', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('exam_type')->nullable();
            $table->string('class_name')->nullable();
            $table->string('subject')->nullable();
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('total_marks', 8, 2)->nullable();
            $table->decimal('passing_marks', 8, 2)->nullable();
            $table->string('academic_year')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });

        $schema->create('parents', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('medical_records', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->string('blood_group')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->text('allergies')->nullable();
            $table->text('medical_conditions')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->timestamps();
        });

        $schema->create('medical_checkups', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('checkup_date');
            $table->string('doctor_name');
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('vaccination_logs')->nullable();
            $table->timestamps();
        });

        $schema->create('disciplinary_incidents', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('incident_date');
            $table->string('title');
            $table->text('description');
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->integer('demerit_points')->default(0);
            $table->string('status')->default('investigating');
            $table->timestamps();
        });

        $schema->create('disciplinary_actions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('incident_id');
            $table->string('action_type');
            $table->text('action_details');
            $table->timestamp('parent_notified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('notebook_checks', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('check_date');
            $table->string('deficiencies')->nullable();
            $table->date('recheck_date')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_signed')->default(false);
            $table->unsignedBigInteger('checked_by')->nullable();
            $table->timestamps();
        });

        $schema->create('study_materials', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('class_id');
            $table->unsignedBigInteger('subject_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
        });

        $schema->create('online_quizzes', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->string('title');
            $table->integer('duration_minutes');
            $table->integer('total_questions')->default(0);
            $table->timestamps();
        });

        $schema->create('quiz_questions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('quiz_id');
            $table->text('question_text');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->string('correct_option');
            $table->timestamps();
        });

        $schema->create('ptm_meetings', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('parent_id');
            $table->date('meeting_date');
            $table->string('time_slot');
            $table->string('status')->default('requested');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->dropIfExists('ptm_meetings');
        $schema->dropIfExists('quiz_questions');
        $schema->dropIfExists('online_quizzes');
        $schema->dropIfExists('study_materials');
        $schema->dropIfExists('notebook_checks');
        $schema->dropIfExists('disciplinary_actions');
        $schema->dropIfExists('disciplinary_incidents');
        $schema->dropIfExists('medical_checkups');
        $schema->dropIfExists('medical_records');
        $schema->dropIfExists('parents');
        $schema->dropIfExists('exams');
        $schema->dropIfExists('subjects');
        $schema->dropIfExists('school_classes');
        $schema->dropIfExists('homework_submissions');
        $schema->dropIfExists('homework_notices');
        $schema->dropIfExists('teacher_salaries');
        $schema->dropIfExists('teacher_leaves');
        $schema->dropIfExists('students');
        $schema->dropIfExists('teacher_logins');
        $schema->dropIfExists('teachers');
        $schema->dropIfExists('users');

        parent::tearDown();
    }

    /** @test */
    public function test_teacher_can_submit_leave_request(): void
    {
        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($teacherLogin, 'teacher')->post('/teacher/leaves', [
            'leave_type' => 'casual_leave',
            'start_date' => now()->addDays(2)->format('Y-m-d'),
            'end_date' => now()->addDays(4)->format('Y-m-d'),
            'reason' => 'Personal emergency reason.',
        ]);

        $response->assertRedirect(route('teacher.leaves.index'));
        $this->assertDatabaseHas('teacher_leaves', [
            'teacher_id' => $teacher->id,
            'leave_type' => 'casual_leave',
            'days' => 3,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function test_admin_can_approve_teacher_leave(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $leave = TeacherLeave::create([
            'teacher_id' => $teacher->id,
            'leave_type' => 'medical_leave',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'days' => 3,
            'reason' => 'Sick',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->put("/admin/hr/leaves/{$leave->id}", [
            'status' => 'approved',
            'approval_notes' => 'Get well soon',
        ]);

        $response->assertRedirect(route('admin.leaves.index'));
        $this->assertDatabaseHas('teacher_leaves', [
            'id' => $leave->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approval_notes' => 'Get well soon',
        ]);
    }

    /** @test */
    public function test_admin_can_generate_payroll_payout(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $response = $this->actingAs($admin)->post('/admin/hr/payroll/generate', [
            'teacher_id' => $teacher->id,
            'pay_month' => 7,
            'pay_year' => 2026,
            'pay_scale' => 'SCALE-X',
            'basic_salary' => 50000,
            'hra' => 5000,
            'da' => 2000,
            'pf_amount' => 4000,
            'payment_method' => 'bank_transfer',
            'remarks' => 'Salary credit test',
        ]);

        $response->assertRedirect(route('admin.hr.payroll.index'));
        $this->assertDatabaseHas('teacher_salaries', [
            'teacher_id' => $teacher->id,
            'gross_salary' => 57000,
            'net_salary' => 53000,
            'payment_status' => 'paid',
            'paid_by' => $admin->id,
        ]);
    }

    /** @test */
    public function test_teacher_can_view_own_salaries(): void
    {
        $teacher = Teacher::create([
            'name' => 'Jane Smith',
            'phone' => '0987654321'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '0987654321',
            'password' => Hash::make('password123'),
        ]);

        $salary = TeacherSalary::create([
            'teacher_id' => $teacher->id,
            'basic_salary' => 45000,
            'gross_salary' => 45000,
            'net_salary' => 45000,
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $response = $this->actingAs($teacherLogin, 'teacher')->get('/teacher/salaries');

        $response->assertStatus(200);
        $response->assertSee(number_format($salary->net_salary, 2));
    }

    /** @test */
    public function test_teacher_can_download_own_payslip_pdf(): void
    {
        $teacher = Teacher::create([
            'name' => 'Jane Smith',
            'phone' => '0987654321'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '0987654321',
            'password' => Hash::make('password123'),
        ]);

        $salary = TeacherSalary::create([
            'teacher_id' => $teacher->id,
            'basic_salary' => 45000,
            'gross_salary' => 45000,
            'net_salary' => 45000,
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        $response = $this->actingAs($teacherLogin, 'teacher')->get("/teacher/salaries/{$salary->id}/pdf");

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /** @test */
    public function test_teacher_cannot_download_other_teacher_payslip_pdf(): void
    {
        $teacher1 = Teacher::create([
            'name' => 'Jane Smith',
            'phone' => '0987654321'
        ]);

        $teacherLogin1 = TeacherLogin::create([
            'teacher_id' => $teacher1->id,
            'username' => '0987654321',
            'password' => Hash::make('password123'),
        ]);

        $teacher2 = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $salaryOfTeacher2 = TeacherSalary::create([
            'teacher_id' => $teacher2->id,
            'basic_salary' => 50000,
            'gross_salary' => 50000,
            'net_salary' => 50000,
            'payment_status' => 'paid',
            'payment_date' => now(),
            'payment_method' => 'bank_transfer',
        ]);

        // Attempting to download teacher2's payslip as teacher1 should fail (404/Forbidden)
        $response = $this->actingAs($teacherLogin1, 'teacher')->get("/teacher/salaries/{$salaryOfTeacher2->id}/pdf");

        $response->assertStatus(404);
    }

    /** @test */
    public function test_student_can_submit_homework_file(): void
    {
        Storage::fake('public');

        $studentUser = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Student User',
            'class_id' => 10,
            'class' => 'Class 8'
        ]);

        $homework = HomeworkNotice::create([
            'title' => 'Math Assignment',
            'description' => 'Solve algebra questions.',
            'type' => 'homework',
            'class_id' => 10,
            'due_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $file = UploadedFile::fake()->create('algebra.pdf', 100);

        $response = $this->actingAs($studentUser)->post("/student/homework/{$homework->id}/submit", [
            'file' => $file,
            'student_notes' => 'Done all sums.',
        ]);

        $response->assertRedirect(route('student.homework.index'));
        $this->assertDatabaseHas('homework_submissions', [
            'homework_notice_id' => $homework->id,
            'student_id' => $student->id,
            'student_notes' => 'Done all sums.',
            'status' => 'submitted',
        ]);
    }

    /** @test */
    public function test_teacher_can_evaluate_homework_submission(): void
    {
        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'name' => 'Student User',
            'class_id' => 10,
        ]);

        $homework = HomeworkNotice::create([
            'title' => 'Math Assignment',
            'type' => 'homework',
            'class_id' => 10,
        ]);

        $submission = HomeworkSubmission::create([
            'homework_notice_id' => $homework->id,
            'student_id' => $student->id,
            'submission_date' => now(),
            'file_path' => 'homework_submissions/algebra.pdf',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($teacherLogin, 'teacher')->post("/teacher/homework/submissions/{$submission->id}/evaluate", [
            'marks_obtained' => 9.5,
            'grade' => 'A+',
            'remarks' => 'Excellent work!',
        ]);

        $response->assertRedirect(route('teacher.homework.submissions.index', $homework->id));
        $this->assertDatabaseHas('homework_submissions', [
            'id' => $submission->id,
            'marks_obtained' => 9.50,
            'grade' => 'A+',
            'remarks' => 'Excellent work!',
            'status' => 'evaluated',
            'evaluated_by' => $teacher->id,
        ]);
    }

    /** @test */
    public function test_teacher_can_log_notebook_checking(): void
    {
        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'name' => 'Jane Student',
            'class_id' => 1,
        ]);

        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $response = $this->actingAs($teacherLogin, 'teacher')->post('/teacher/notebooks', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'check_date' => now()->format('Y-m-d'),
            'deficiencies' => 'incomplete_work',
            'remarks' => 'Please complete chapter 2.',
        ]);

        $response->assertRedirect(route('teacher.notebooks.index'));
        $this->assertDatabaseHas('notebook_checks', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'checked_by' => $teacher->id,
            'deficiencies' => 'incomplete_work',
            'remarks' => 'Please complete chapter 2.',
            'is_signed' => true,
        ]);
    }

    /** @test */
    public function test_teacher_can_create_online_quiz(): void
    {
        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        $exam = Exam::create([
            'name' => 'Mid Term',
            'exam_type' => 'mid_term',
            'class_name' => 'Class 8',
            'subject' => 'Science',
            'exam_date' => now()->format('Y-m-d'),
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026',
        ]);

        // 1. Create Quiz
        $response = $this->actingAs($teacherLogin, 'teacher')->post('/teacher/quizzes', [
            'title' => 'Science Quiz 1',
            'exam_id' => $exam->id,
            'duration_minutes' => 30,
        ]);

        $response->assertRedirect(route('teacher.quizzes.index'));
        $this->assertDatabaseHas('online_quizzes', [
            'title' => 'Science Quiz 1',
            'exam_id' => $exam->id,
            'duration_minutes' => 30,
        ]);

        $quiz = OnlineQuiz::first();

        // 2. Create Quiz Question
        $response = $this->actingAs($teacherLogin, 'teacher')->post("/teacher/quizzes/{$quiz->id}/question", [
            'question_text' => 'What is H2O?',
            'option_a' => 'Water',
            'option_b' => 'Oxygen',
            'option_c' => 'Hydrogen',
            'option_d' => 'Nitrogen',
            'correct_option' => 'a',
        ]);

        $response->assertRedirect(route('teacher.quizzes.show', $quiz->id));
        $this->assertDatabaseHas('quiz_questions', [
            'quiz_id' => $quiz->id,
            'question_text' => 'What is H2O?',
            'correct_option' => 'a',
        ]);

        $quiz->refresh();
        $this->assertEquals(1, $quiz->total_questions);
    }

    /** @test */
    public function test_parent_can_request_ptm_slot(): void
    {
        $teacher = Teacher::create([
            'name' => 'John Teacher',
            'phone' => '1234567890'
        ]);

        $student = Student::create([
            'name' => 'Jane Student',
            'class_id' => 1,
        ]);

        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'password' => Hash::make('password123'),
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($parent, 'parent')->post('/parent/ptm', [
            'teacher_id' => $teacher->id,
            'meeting_date' => now()->addDay()->format('Y-m-d'),
            'time_slot' => '10:00 AM - 10:30 AM',
            'notes' => 'Discuss math progress.',
        ]);

        $response->assertRedirect(route('parent.ptm.index'));
        $this->assertDatabaseHas('ptm_meetings', [
            'teacher_id' => $teacher->id,
            'parent_id' => $parent->id,
            'time_slot' => '10:00 AM - 10:30 AM',
            'notes' => 'Discuss math progress.',
            'status' => 'requested',
        ]);
    }

    /** @test */
    public function test_student_can_view_medical_records(): void
    {
        $studentUser = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Student User',
            'class_id' => 1,
        ]);

        $record = MedicalRecord::create([
            'student_id' => $student->id,
            'blood_group' => 'O+',
            'height_cm' => 165.5,
            'weight_kg' => 55.2,
            'allergies' => 'Peanuts',
            'medical_conditions' => 'None',
        ]);

        $checkup = MedicalCheckup::create([
            'student_id' => $student->id,
            'checkup_date' => now()->format('Y-m-d'),
            'doctor_name' => 'Dr. Smith',
            'diagnosis' => 'Regular checkup',
            'treatment' => 'All healthy',
        ]);

        $response = $this->actingAs($studentUser)->get('/student/medical');

        $response->assertStatus(200);
        $response->assertViewHas('record');
        $response->assertViewHas('checkups');
    }

    /** @test */
    public function test_student_can_view_discipline_incidents(): void
    {
        $studentUser = User::create([
            'name' => 'Student User',
            'email' => 'student@example.com',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Student User',
            'class_id' => 1,
        ]);

        $incident = DisciplinaryIncident::create([
            'student_id' => $student->id,
            'incident_date' => now()->format('Y-m-d'),
            'title' => 'Late to class',
            'description' => 'Late to morning class by 15 mins.',
            'demerit_points' => 2,
            'status' => 'investigating',
        ]);

        $action = DisciplinaryAction::create([
            'incident_id' => $incident->id,
            'action_type' => 'warning_letter',
            'action_details' => 'Warned not to repeat.',
        ]);

        $response = $this->actingAs($studentUser)->get('/student/discipline');

        $response->assertStatus(200);
        $response->assertViewHas('incidents');
        $response->assertViewHas('totalDemerits', 2);
    }
}
