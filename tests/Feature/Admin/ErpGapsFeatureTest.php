<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\Subject;
use App\Models\Exam;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\StudentHostelAllocation;
use App\Models\Alumni;
use App\Models\StudentLeave;
use App\Models\GateEntry;
use App\Models\AdmissionEnquiry;
use App\Models\UniformCheck;
use App\Models\SlowLearner;
use App\Models\OnlineQuiz;
use App\Models\QuizSubmission;
use App\Models\PurchaseRequest;
use App\Models\FeeCollection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ErpGapsFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

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
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('teacher_logins', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('class')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('parents', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->timestamps();
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
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_structure_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_id');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        $schema->create('student_fee_assignments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('fee_structure_id');
            $table->string('academic_year');
            $table->timestamps();
        });

        $schema->create('fee_collections', function ($table) {
            $table->bigIncrements('id');
            $table->string('receipt_no')->unique();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('fee_structure_id');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('late_fine', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);
            $table->date('payment_date');
            $table->string('payment_mode');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('student_fee_ledgers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 10, 2)->default(0.00);
            $table->decimal('credit', 10, 2)->default(0.00);
            $table->decimal('running_balance', 10, 2)->default(0.00);
            $table->string('academic_year')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('unpaid_amount', 10, 2)->default(0.00);
            $table->timestamps();
        });

        $schema->create('hostels', function ($table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('type');
            $table->integer('capacity');
            $table->timestamps();
        });

        $schema->create('hostel_rooms', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('hostel_id');
            $table->string('room_no');
            $table->integer('capacity');
            $table->decimal('cost_per_bed', 10, 2);
            $table->timestamps();
        });

        $schema->create('student_hostel_allocations', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('room_id');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $schema->create('alumni', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->integer('graduation_year');
            $table->string('current_occupation')->nullable();
            $table->string('contact_email')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();
        });

        $schema->create('student_leaves', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        $schema->create('gate_entries', function ($table) {
            $table->bigIncrements('id');
            $table->string('visitor_name');
            $table->string('purpose');
            $table->timestamp('check_in');
            $table->timestamp('check_out')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->unsignedBigInteger('host_user_id')->nullable();
            $table->timestamps();
        });

        $schema->create('admission_enquiries', function ($table) {
            $table->bigIncrements('id');
            $table->string('candidate_name');
            $table->string('parent_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('status')->default('enquiry');
            $table->date('interview_date')->nullable();
            $table->decimal('interview_score', 5, 2)->nullable();
            $table->decimal('entrance_score', 5, 2)->nullable();
            $table->unsignedBigInteger('counsellor_id')->nullable();
            $table->timestamps();
        });

        $schema->create('uniform_checks', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('check_date');
            $table->boolean('is_compliant')->default(true);
            $table->string('remarks')->nullable();
            $table->timestamps();
        });

        $schema->create('slow_learners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('subject_id');
            $table->date('diagnostic_date');
            $table->text('remedial_notes')->nullable();
            $table->string('progress_status')->default('stagnant');
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

        $schema->create('quiz_submissions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('quiz_id');
            $table->integer('score');
            $table->text('answers_json')->nullable();
            $table->timestamps();
        });

        $schema->create('purchase_requests', function ($table) {
            $table->bigIncrements('id');
            $table->string('item_name');
            $table->integer('quantity');
            $table->decimal('estimated_cost', 10, 2);
            $table->unsignedBigInteger('requested_by');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamps();
        });

        $schema->create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        $schema->create('role_user', function ($table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        $schema->create('activity_log', function ($table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->uuid('event')->nullable();
            $table->text('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        $schema = Schema::connection('sqlite');
        $schema->dropIfExists('activity_log');
        $schema->dropIfExists('role_user');
        $schema->dropIfExists('roles');
        $schema->dropIfExists('purchase_requests');
        $schema->dropIfExists('quiz_submissions');
        $schema->dropIfExists('quiz_questions');
        $schema->dropIfExists('online_quizzes');
        $schema->dropIfExists('slow_learners');
        $schema->dropIfExists('uniform_checks');
        $schema->dropIfExists('admission_enquiries');
        $schema->dropIfExists('gate_entries');
        $schema->dropIfExists('student_leaves');
        $schema->dropIfExists('alumni');
        $schema->dropIfExists('student_hostel_allocations');
        $schema->dropIfExists('hostel_rooms');
        $schema->dropIfExists('hostels');
        $schema->dropIfExists('fee_collections');
        $schema->dropIfExists('student_fee_assignments');
        $schema->dropIfExists('fee_structure_items');
        $schema->dropIfExists('fee_structures');
        $schema->dropIfExists('exams');
        $schema->dropIfExists('subjects');
        $schema->dropIfExists('parents');
        $schema->dropIfExists('students');
        $schema->dropIfExists('teacher_logins');
        $schema->dropIfExists('teachers');
        $schema->dropIfExists('users');

        parent::tearDown();
    }

    /** @test */
    public function test_parent_can_process_mock_stripe_checkout(): void
    {
        $student = Student::create(['name' => 'Jane Kid', 'class' => '8-A']);
        $parent = ParentModel::create([
            'name' => 'Parent Name',
            'email' => 'parent@example.com',
            'password' => Hash::make('password123'),
            'student_id' => $student->id
        ]);

        $feeStructure = DB::table('fee_structures')->insertGetId(['name' => 'Tuition Fees']);
        DB::table('fee_structure_items')->insert([
            'fee_structure_id' => $feeStructure,
            'amount' => 5000.00
        ]);

        DB::table('student_fee_assignments')->insert([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure,
            'academic_year' => '2026'
        ]);

        $response = $this->actingAs($parent, 'parent')->get('/parent/payments/pay-fees');
        $response->assertStatus(200);

        // Perform Redirect Checkout
        $response = $this->actingAs($parent, 'parent')->post('/parent/payments/stripe-checkout', [
            'fee_structure_id' => $feeStructure,
            'amount' => 5000.00
        ]);

        $response->assertRedirect();

        // Perform Successful Callback
        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.stripe-success', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure,
            'amount' => 5000.00
        ]));

        $response->assertRedirect(route('parent.payments.pay-fees'));
        $this->assertDatabaseHas('fee_collections', [
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure,
            'final_amount' => 5000.00,
            'payment_mode' => 'online',
        ]);
    }

    /** @test */
    public function test_student_can_take_and_submit_mcq_quiz(): void
    {
        $studentUser = User::create([
            'name' => 'Jane Kid',
            'email' => 'kid@example.com',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Jane Kid',
        ]);

        $quiz = OnlineQuiz::create([
            'title' => 'Math Quiz',
            'duration_minutes' => 10,
            'total_questions' => 2,
        ]);

        $q1 = DB::table('quiz_questions')->insertGetId([
            'quiz_id' => $quiz->id,
            'question_text' => '1+1=?',
            'option_a' => '1',
            'option_b' => '2',
            'option_c' => '3',
            'option_d' => '4',
            'correct_option' => 'b',
        ]);

        $response = $this->actingAs($studentUser)->get("/student/quizzes/{$quiz->id}/take");
        $response->assertStatus(200);

        $response = $this->actingAs($studentUser)->post("/student/quizzes/{$quiz->id}/submit", [
            'answers' => [
                $q1 => 'b', // Correct answer
            ]
        ]);

        $response->assertRedirect(route('student.quizzes.index'));
        $this->assertDatabaseHas('quiz_submissions', [
            'student_id' => $student->id,
            'quiz_id' => $quiz->id,
            'score' => 1,
        ]);
    }

    /** @test */
    public function test_admin_can_manage_hostel_occupancy(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $student = Student::create(['name' => 'John Boarder']);

        // 1. Create Hostel
        $response = $this->actingAs($admin)->post('/admin/hostels/hostel', [
            'name' => 'Tagore Boys Hostel',
            'type' => 'boys',
            'capacity' => 100,
        ]);
        $response->assertRedirect(route('admin.hostels.index'));
        $this->assertDatabaseHas('hostels', ['name' => 'Tagore Boys Hostel']);

        $hostel = Hostel::first();

        // 2. Create Room
        $response = $this->actingAs($admin)->post('/admin/hostels/room', [
            'hostel_id' => $hostel->id,
            'room_no' => '101-A',
            'capacity' => 4,
            'cost_per_bed' => 3000.00,
        ]);
        $response->assertRedirect(route('admin.hostels.index'));
        $this->assertDatabaseHas('hostel_rooms', ['room_no' => '101-A']);

        $room = HostelRoom::first();

        // 3. Allocate Student
        $response = $this->actingAs($admin)->post('/admin/hostels/assign', [
            'student_id' => $student->id,
            'room_id' => $room->id,
        ]);
        $response->assertRedirect(route('admin.hostels.index'));
        $this->assertDatabaseHas('student_hostel_allocations', [
            'student_id' => $student->id,
            'room_id' => $room->id,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_admin_can_archive_student_as_alumni(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $student = Student::create(['name' => 'Graduating Student']);

        $response = $this->actingAs($admin)->post('/admin/alumni', [
            'student_id' => $student->id,
            'graduation_year' => 2026,
            'current_occupation' => 'Doctor',
            'contact_email' => 'alumni@example.com',
            'feedback' => 'Great school experience!',
        ]);

        $response->assertRedirect(route('admin.alumni.index'));
        $this->assertDatabaseHas('alumni', [
            'student_id' => $student->id,
            'graduation_year' => 2026,
            'current_occupation' => 'Doctor',
        ]);
    }

    /** @test */
    public function test_admin_can_log_visitor_gate_entries(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $response = $this->actingAs($admin)->post('/admin/gate-entries', [
            'visitor_name' => 'Harry Miller',
            'purpose' => 'Maintenance check',
            'vehicle_no' => 'MH-12-XX-1234',
        ]);

        $response->assertRedirect(route('admin.gate-entries.index'));
        $this->assertDatabaseHas('gate_entries', [
            'visitor_name' => 'Harry Miller',
            'purpose' => 'Maintenance check',
            'check_out' => null,
        ]);

        $entry = GateEntry::first();

        // Check-Out
        $response = $this->actingAs($admin)->post("/admin/gate-entries/{$entry->id}/checkout");
        $response->assertRedirect(route('admin.gate-entries.index'));
        $entry->refresh();
        $this->assertNotNull($entry->check_out);
    }

    /** @test */
    public function test_student_can_submit_leave_and_teacher_approves(): void
    {
        $studentUser = User::create([
            'name' => 'Jane Kid',
            'email' => 'kid@example.com',
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'name' => 'Jane Kid',
        ]);

        $teacher = Teacher::create([
            'name' => 'John Doe',
            'phone' => '1234567890'
        ]);

        $teacherLogin = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '1234567890',
            'password' => Hash::make('password123'),
        ]);

        // Student applies
        $response = $this->actingAs($studentUser)->post('/student/leaves', [
            'start_date' => now()->addDay()->format('Y-m-d'),
            'end_date' => now()->addDays(2)->format('Y-m-d'),
            'reason' => 'Family event',
        ]);
        $response->assertRedirect(route('student.leaves.index'));
        $this->assertDatabaseHas('student_leaves', [
            'student_id' => $student->id,
            'status' => 'pending',
        ]);

        $leave = StudentLeave::first();

        // Teacher approves
        $response = $this->actingAs($teacherLogin, 'teacher')->post("/teacher/student-leaves/{$leave->id}", [
            'status' => 'approved',
        ]);
        $response->assertRedirect(route('teacher.student-leaves.index'));
        $this->assertDatabaseHas('student_leaves', [
            'id' => $leave->id,
            'status' => 'approved',
            'approved_by' => $teacher->id,
        ]);
    }

    /** @test */
    public function test_admin_can_manage_admissions_pipeline(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);
        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'admin', 'display_name' => 'Admin']);
        DB::table('role_user')->insert(['user_id' => $admin->id, 'role_id' => $adminRoleId]);

        // 1. Store Enquiry
        $response = $this->actingAs($admin)->post('/admin/admissions/enquiry', [
            'candidate_name' => 'Alex Prince',
            'parent_name' => 'Peter Prince',
            'phone' => '9876543210',
            'email' => 'alex@example.com',
        ]);
        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertDatabaseHas('admission_enquiries', [
            'candidate_name' => 'Alex Prince',
            'status' => 'enquiry',
        ]);

        $enquiry = AdmissionEnquiry::first();

        // 2. Schedule Interview
        $response = $this->actingAs($admin)->post("/admin/admissions/{$enquiry->id}/schedule", [
            'interview_date' => now()->addDays(5)->format('Y-m-d'),
        ]);
        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertDatabaseHas('admission_enquiries', [
            'id' => $enquiry->id,
            'status' => 'interview',
        ]);

        // 3. Evaluate & Select
        $response = $this->actingAs($admin)->post("/admin/admissions/{$enquiry->id}/evaluate", [
            'interview_score' => 85,
            'entrance_score' => 90,
            'status' => 'selected',
        ]);
        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertDatabaseHas('admission_enquiries', [
            'id' => $enquiry->id,
            'status' => 'selected',
            'interview_score' => 85.00,
            'entrance_score' => 90.00,
        ]);
    }

    /** @test */
    public function test_teacher_can_log_uniform_compliance_check(): void
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

        $student = Student::create(['name' => 'Jane Kid']);

        $response = $this->actingAs($teacherLogin, 'teacher')->post('/teacher/uniform', [
            'student_id' => $student->id,
            'check_date' => now()->format('Y-m-d'),
            'is_compliant' => 0, // violation
            'remarks' => 'Shoes unpolished',
        ]);

        $response->assertRedirect(route('teacher.uniform.index'));
        $this->assertDatabaseHas('uniform_checks', [
            'student_id' => $student->id,
            'is_compliant' => 0,
            'remarks' => 'Shoes unpolished',
        ]);
    }

    /** @test */
    public function test_teacher_can_log_slow_learner_remedial_notes(): void
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

        $student = Student::create(['name' => 'Jane Kid']);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MATH101']);

        $response = $this->actingAs($teacherLogin, 'teacher')->post('/teacher/remedial', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'diagnostic_date' => now()->format('Y-m-d'),
            'remedial_notes' => 'Struggling with division.',
            'progress_status' => 'stagnant',
        ]);

        $response->assertRedirect(route('teacher.remedial.index'));
        $this->assertDatabaseHas('slow_learners', [
            'student_id' => $student->id,
            'subject_id' => $subject->id,
            'progress_status' => 'stagnant',
        ]);
    }

    /** @test */
    public function test_admin_can_manage_inventory_purchase_requisitions(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($admin)->post('/admin/inventory/purchase-requests', [
            'item_name' => 'Projector Screen',
            'quantity' => 2,
            'estimated_cost' => 15000.00,
        ]);

        $response->assertRedirect(route('admin.inventory.purchase-requests.index'));
        $this->assertDatabaseHas('purchase_requests', [
            'item_name' => 'Projector Screen',
            'quantity' => 2,
            'status' => 'pending',
            'requested_by' => $admin->id,
        ]);

        $req = PurchaseRequest::first();

        // Approve
        $response = $this->actingAs($admin)->post("/admin/inventory/purchase-requests/{$req->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertRedirect(route('admin.inventory.purchase-requests.index'));
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $req->id,
            'status' => 'approved',
            'approved_by' => $admin->id,
        ]);
    }
}
