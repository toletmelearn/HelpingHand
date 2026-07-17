<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentFeeLedger;
use App\Models\DefaulterStage;
use App\Models\DefaulterLog;
use App\Models\Result;
use App\Models\Exam;
use App\Models\Certificate;
use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\ClassManagement;
use App\Models\Teacher;
use App\Notifications\DefaulterActionNotification;
use App\Services\DefaulterService;
use App\Services\ExamRestrictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class DefaulterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;
    protected $schoolClass;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'John Doe',
            'admission_no' => 'ADM-2026-9999',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);

        $this->subject = \App\Models\Subject::create([
            'name' => 'Science',
            'code' => 'SCI-10',
            'subject_type' => 'scholastic',
            'is_active' => true
        ]);
    }

    /** @test */
    public function defaulter_service_syncs_students_with_outstanding_ledger_balances()
    {
        // 1. Post a debit entry in the student fee ledger -> makes the student a defaulter
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-07-01',
            'description' => 'Tution Fee charge',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 5000.00,
            'credit' => 0.00,
            'running_balance' => 5000.00,
            'class_id' => $this->schoolClass->id,
            'unpaid_amount' => 5000.00
        ]);

        $service = new DefaulterService();
        $service->syncDefaulters();

        // Check student is in DefaulterStage table under stage "Reminder"
        $this->assertDatabaseHas('defaulter_stages', [
            'student_id' => $this->student->id,
            'stage' => 'Reminder',
            'outstanding_amount' => 5000.00
        ]);

        // 2. Post a credit transaction clearing the outstanding balance
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-07-02',
            'description' => 'Fee Payment credit',
            'reference_type' => 'fee_collection',
            'reference_id' => 1,
            'debit' => 0.00,
            'credit' => 5000.00,
            'running_balance' => 0.00,
            'class_id' => $this->schoolClass->id,
            'unpaid_amount' => 0.00
        ]);

        $service->syncDefaulters();

        // Defaulter stage must transition to Cleared
        $this->assertDatabaseHas('defaulter_stages', [
            'student_id' => $this->student->id,
            'stage' => 'Cleared',
            'outstanding_amount' => 0.00
        ]);
    }

    /** @test */
    public function executing_stage_actions_sends_notifications_logs_activities_and_advances_stage()
    {
        // Setup outstanding dues
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-07-01',
            'description' => 'Tuition Fee charge',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1200.00,
            'credit' => 0.00,
            'running_balance' => 1200.00,
            'unpaid_amount' => 1200.00
        ]);

        // Real SMS delivery goes through Twilio, which isn't configured in
        // tests -- inject a mocked NotificationService so this test verifies
        // DefaulterService's own logging/stage logic, not Twilio reachability.
        $mockNotificationService = $this->createMock(\App\Services\NotificationService::class);
        $mockNotificationService->method('sendSms')->willReturn(true);
        $service = new DefaulterService($mockNotificationService);
        $service->syncDefaulters();

        // Initial stage is Reminder. Send SMS.
        $success = $service->dispatchCommunication($this->student->id, 'sms', 'Reminder', $this->accountantUser->id);
        $this->assertTrue($success);

        $this->assertDatabaseHas('defaulter_logs', [
            'student_id' => $this->student->id,
            'stage' => 'Reminder',
            'action_type' => 'Sms',
            'status' => 'Sent'
        ]);

        // Promote student's stage to next level (Phone Call)
        $newStage = $service->promoteStage($this->student->id, $this->accountantUser->id);
        $this->assertEquals('Phone Call', $newStage);

        $this->assertDatabaseHas('defaulter_stages', [
            'student_id' => $this->student->id,
            'stage' => 'Phone Call'
        ]);
    }

    /** @test */
    public function academic_restrictions_block_exam_result_and_tc_operations()
    {
        // Create student's user account for student result auth
        $studentUser = User::factory()->create(['role' => 'student']);
        $this->student->update(['user_id' => $studentUser->id]);

        // Put student at "TC Hold" stage directly
        DefaulterStage::create([
            'student_id' => $this->student->id,
            'stage' => 'TC Hold',
            'outstanding_amount' => 1000.00,
            'last_action_date' => now()
        ]);

        // 1. Result hold check: attempting to view result page returns HTTP 403
        $exam = Exam::create([
            'name' => 'Midterm',
            'exam_type' => 'mid_term',
            'class_name' => 'Class 10',
            'subject' => 'Science',
            'exam_date' => '2026-07-01',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026',
            'academic_session_id' => 1,
            'subject_id' => $this->subject->id,
            'max_marks' => 100
        ]);

        $result = Result::create([
            'student_id' => $this->student->id,
            'exam_id' => $exam->id,
            'subject_id' => $this->subject->id,
            'subject' => 'Science',
            'marks_obtained' => 75.00,
            'total_marks' => 100.00,
            'percentage' => 75.00,
            'grade' => 'B',
            'result_status' => 'pass',
            'academic_session' => '2026-27',
            'academic_year' => '2026-27'
        ]);

        $response = $this->actingAs($studentUser)->get(route('student.results.show', $result->id));
        $response->assertStatus(403);

        // 2. TC hold check: Certificate creation fails validation
        $responseTC = $this->actingAs($this->accountantUser)->post(route('admin.certificates.store'), [
            'certificate_type' => 'tc',
            'recipient_type' => 'App\\Models\\Student',
            'recipient_id' => $this->student->id,
            'content_data' => ['Reason' => 'Transfer']
        ]);

        $responseTC->assertSessionHasErrors('recipient_id');
    }

    /** @test */
    public function reaching_exam_restriction_auto_revokes_the_admit_card_and_an_override_restores_it()
    {
        $exam = Exam::create([
            'name' => 'Midterm', 'exam_type' => 'mid_term', 'class_name' => 'Class 10',
            'subject' => 'Science', 'exam_date' => '2026-07-01', 'start_time' => '09:00:00',
            'end_time' => '12:00:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026', 'academic_session_id' => 1,
            'subject_id' => $this->subject->id, 'max_marks' => 100,
        ]);
        $format = AdmitCardFormat::create(['name' => 'Standard', 'is_active' => true]);
        $admitCard = AdmitCard::create([
            'student_id' => $this->student->id, 'exam_id' => $exam->id,
            'admit_card_format_id' => $format->id, 'academic_session' => '2026',
            'status' => 'published', 'published_at' => now(), 'published_by' => $this->adminUser->id,
        ]);

        StudentFeeLedger::create([
            'student_id' => $this->student->id, 'date' => '2026-07-01',
            'description' => 'Tuition Fee charge', 'reference_type' => 'fee_structure_item',
            'reference_id' => 1, 'debit' => 1200.00, 'credit' => 0.00,
            'running_balance' => 1200.00, 'unpaid_amount' => 1200.00,
        ]);

        $service = new DefaulterService($this->createMock(\App\Services\NotificationService::class));
        $service->syncDefaulters();
        $service->overrideStage($this->student->id, 'Exam Restriction', null, $this->accountantUser->id);

        $this->assertEquals('revoked', $admitCard->fresh()->status);

        // Admin grants an exception -- the card is restored immediately.
        ExamRestrictionService::grantOverride($this->student->id, $this->adminUser->id, 'Parent committed to pay by Friday');
        $this->assertEquals('published', $admitCard->fresh()->status);
        $this->assertDatabaseHas('defaulter_exam_overrides', [
            'student_id' => $this->student->id,
            'granted_by' => $this->adminUser->id,
            'revoked_at' => null,
        ]);

        // Revoking the override re-revokes the card, since the student is still at Exam Restriction.
        ExamRestrictionService::revokeOverride($this->student->id, $this->adminUser->id);
        $this->assertEquals('revoked', $admitCard->fresh()->status);
    }

    /** @test */
    public function class_teacher_only_sees_and_can_act_on_their_own_class_defaulters_and_notifies_admin()
    {
        Notification::fake();

        // A second student in a DIFFERENT class the teacher does not own.
        $otherClass = SchoolClass::create(['name' => 'Class 11', 'class_order' => 11]);
        $otherStudent = Student::create([
            'name' => 'Outside Class Kid', 'admission_no' => 'ADM-2026-8888',
            'father_name' => 'Father', 'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01',
            'aadhar_number' => '999999999999', 'address' => 'Test Address', 'phone' => '9998887777',
            'class_id' => $otherClass->id,
        ]);

        StudentFeeLedger::create([
            'student_id' => $this->student->id, 'date' => '2026-07-01',
            'description' => 'Tuition Fee charge', 'reference_type' => 'fee_structure_item',
            'reference_id' => 1, 'debit' => 1200.00, 'credit' => 0.00,
            'running_balance' => 1200.00, 'unpaid_amount' => 1200.00,
        ]);
        StudentFeeLedger::create([
            'student_id' => $otherStudent->id, 'date' => '2026-07-01',
            'description' => 'Tuition Fee charge', 'reference_type' => 'fee_structure_item',
            'reference_id' => 1, 'debit' => 1200.00, 'credit' => 0.00,
            'running_balance' => 1200.00, 'unpaid_amount' => 1200.00,
        ]);

        $classTeacherRole = Role::firstOrCreate(['name' => 'class-teacher'], ['display_name' => 'Class Teacher']);
        $classTeacherUser = User::factory()->create(['role' => 'class-teacher']);
        $classTeacherUser->roles()->attach($classTeacherRole->id);

        $teacherRecord = Teacher::create([
            'name' => 'Ms. Sharma', 'email' => 'sharma@example.com', 'phone' => '9990001111',
            'designation' => 'Class Teacher', 'user_id' => $classTeacherUser->id,
        ]);
        $classManagement = ClassManagement::create(['name' => 'Class 10', 'is_active' => true]);
        $teacherRecord->classes()->attach($classManagement->id);

        // Index only shows their own class's defaulter, not the other class's.
        $mockNotificationService = $this->createMock(\App\Services\NotificationService::class);
        $mockNotificationService->method('sendSms')->willReturn(true);
        app()->instance(\App\Services\NotificationService::class, $mockNotificationService);

        $service = new DefaulterService($mockNotificationService);
        $service->syncDefaulters();

        $response = $this->actingAs($classTeacherUser)->get(route('admin.fees.defaulters.index'));
        $response->assertOk();
        $response->assertSee($this->student->name);
        $response->assertDontSee($otherStudent->name);

        // Can act on their own student.
        $ownActionResponse = $this->actingAs($classTeacherUser)->post(
            route('admin.fees.defaulters.action', $this->student->id),
            ['channel' => 'sms', 'stage' => 'Reminder']
        );
        $ownActionResponse->assertSessionHas('success');

        // Cannot act on a student outside their class, even via direct id.
        $outsideActionResponse = $this->actingAs($classTeacherUser)->post(
            route('admin.fees.defaulters.action', $otherStudent->id),
            ['channel' => 'sms', 'stage' => 'Reminder']
        );
        $outsideActionResponse->assertStatus(403);

        // Admin gets notified about the class teacher's action on their own student.
        Notification::assertSentTo($this->adminUser, DefaulterActionNotification::class);
    }

    /** @test */
    public function month_and_quarter_filters_only_return_defaulters_with_unpaid_dues_in_that_period()
    {
        $julyDefaulter = Student::create([
            'name' => 'July Debtor', 'admission_no' => 'ADM-2026-7001', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01', 'aadhar_number' => '111111111111',
            'address' => 'Test', 'phone' => '9111111111', 'class_id' => $this->schoolClass->id,
        ]);
        StudentFeeLedger::create([
            'student_id' => $julyDefaulter->id, 'date' => '2026-07-05', 'description' => 'Tuition',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 1000.00,
            'credit' => 0.00, 'running_balance' => 1000.00, 'unpaid_amount' => 1000.00,
        ]);

        $decemberDefaulter = Student::create([
            'name' => 'December Debtor', 'admission_no' => 'ADM-2026-7002', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01', 'aadhar_number' => '222222222222',
            'address' => 'Test', 'phone' => '9222222222', 'class_id' => $this->schoolClass->id,
        ]);
        StudentFeeLedger::create([
            'student_id' => $decemberDefaulter->id, 'date' => '2026-12-10', 'description' => 'Tuition',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 1000.00,
            'credit' => 0.00, 'running_balance' => 1000.00, 'unpaid_amount' => 1000.00,
        ]);

        // The ad-hoc admin role created in setUp() has no permissions
        // granted (PermissionSeeder is a seeder, not a migration, so it
        // doesn't run under RefreshDatabase) -- grant what this route needs.
        $viewDefaultersPermission = \App\Models\Permission::firstOrCreate(['name' => 'view-defaulters']);
        Role::where('name', 'admin')->first()->grantPermission($viewDefaultersPermission->name);

        // Month filter: month=7 (July) should return only the July debtor.
        $monthResponse = $this->actingAs($this->adminUser)->get(route('admin.fees.defaulters.index', ['month' => 7]));
        $monthResponse->assertOk();
        $monthResponse->assertSee('July Debtor');
        $monthResponse->assertDontSee('December Debtor');

        // Quarter filter: Q2 = Jul-Sep should include July, Q3 = Oct-Dec should include December.
        $q2Response = $this->actingAs($this->adminUser)->get(route('admin.fees.defaulters.index', ['quarter' => 'Q2']));
        $q2Response->assertSee('July Debtor');
        $q2Response->assertDontSee('December Debtor');

        $q3Response = $this->actingAs($this->adminUser)->get(route('admin.fees.defaulters.index', ['quarter' => 'Q3']));
        $q3Response->assertSee('December Debtor');
        $q3Response->assertDontSee('July Debtor');
    }

    /** @test */
    public function ageing_bucket_filter_matches_the_oldest_unpaid_due_per_student()
    {
        // Regression test: now()->diffInDays($pastDate) returns a NEGATIVE
        // number in this Carbon version (diff is not absolute by default),
        // which silently bucketed every student into "1-30 days" regardless
        // of actual age until this was caught via manual browser testing.
        $recentDebtor = Student::create([
            'name' => 'Recent Debtor', 'admission_no' => 'ADM-2026-8001', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01', 'aadhar_number' => '311111111111',
            'address' => 'Test', 'phone' => '9311111111', 'class_id' => $this->schoolClass->id,
        ]);
        StudentFeeLedger::create([
            'student_id' => $recentDebtor->id, 'date' => now()->subDays(10)->toDateString(), 'description' => 'Tuition',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 1000.00,
            'credit' => 0.00, 'running_balance' => 1000.00, 'unpaid_amount' => 1000.00,
        ]);

        $oldDebtor = Student::create([
            'name' => 'Old Debtor', 'admission_no' => 'ADM-2026-8002', 'father_name' => 'Father',
            'mother_name' => 'Mother', 'date_of_birth' => '2010-01-01', 'aadhar_number' => '322222222222',
            'address' => 'Test', 'phone' => '9322222222', 'class_id' => $this->schoolClass->id,
        ]);
        StudentFeeLedger::create([
            'student_id' => $oldDebtor->id, 'date' => now()->subDays(120)->toDateString(), 'description' => 'Tuition',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 1000.00,
            'credit' => 0.00, 'running_balance' => 1000.00, 'unpaid_amount' => 1000.00,
        ]);

        $viewDefaultersPermission = \App\Models\Permission::firstOrCreate(['name' => 'view-defaulters']);
        Role::where('name', 'admin')->first()->grantPermission($viewDefaultersPermission->name);

        $recentBucketResponse = $this->actingAs($this->adminUser)->get(route('admin.fees.defaulters.index', ['ageing' => '1_30']));
        $recentBucketResponse->assertSee('Recent Debtor');
        $recentBucketResponse->assertDontSee('Old Debtor');

        $oldBucketResponse = $this->actingAs($this->adminUser)->get(route('admin.fees.defaulters.index', ['ageing' => '90_plus']));
        $oldBucketResponse->assertSee('Old Debtor');
        $oldBucketResponse->assertDontSee('Recent Debtor');
    }
}
