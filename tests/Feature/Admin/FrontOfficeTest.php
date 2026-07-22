<?php

namespace Tests\Feature\Admin;

use App\Models\AdmissionEnquiry;
use App\Models\AdmissionEnquiryDocument;
use App\Models\Appointment;
use App\Models\GateEntry;
use App\Models\GatePass;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\FrontOffice\FrontOfficeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontOfficeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $receptionist;
    protected User $teacher;
    protected FrontOfficeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'FrontOfficeSeeder']);

        // Create Admin User
        $adminRole = Role::where('name', 'admin')->first();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach($adminRole);

        // Create Receptionist User
        $receptionistRole = Role::where('name', 'receptionist')->first();
        $this->receptionist = User::factory()->create();
        $this->receptionist->roles()->attach($receptionistRole);

        // Create Teacher User
        $teacherRole = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $this->teacher = User::factory()->create();
        $this->teacher->roles()->attach($teacherRole);

        $this->service = new FrontOfficeService();
    }

    /**
     * Test Front Office access permissions.
     */
    public function test_receptionist_can_access_front_office_dashboard()
    {
        $response = $this->actingAs($this->receptionist)
            ->get(route('admin.front-office.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.front-office.dashboard');
    }

    public function test_unauthenticated_user_cannot_access_front_office_dashboard()
    {
        $response = $this->get(route('admin.front-office.dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_receptionist_is_restricted_from_finance_management()
    {
        $response = $this->actingAs($this->receptionist)
            ->get('/admin/fees');

        $response->assertStatus(403);
    }

    public function test_receptionist_accessing_admin_dashboard_redirects_to_front_office_dashboard()
    {
        $response = $this->actingAs($this->receptionist)
            ->get('/admin/dashboard');

        $response->assertRedirect(route('admin.front-office.dashboard'));
    }

    public function test_receptionist_accessing_home_redirects_to_front_office_dashboard()
    {
        $response = $this->actingAs($this->receptionist)
            ->get('/home');

        $response->assertRedirect(route('admin.front-office.dashboard'));
    }

    /**
     * Test Admission Enquiry duplicate checking.
     */
    public function test_duplicate_enquiry_detection()
    {
        // 1. Create initial enquiry
        AdmissionEnquiry::create([
            'candidate_name' => 'Test Candidate',
            'parent_name' => 'Test Parent',
            'phone' => '9999999999',
            'email' => 'test@candidate.com',
            'status' => 'new',
        ]);

        // 2. Assert service flags duplicate
        $duplicateByPhone = $this->service->checkDuplicateEnquiry([
            'phone' => '9999999999',
            'email' => 'other@candidate.com',
        ]);
        $this->assertNotNull($duplicateByPhone);

        $duplicateByEmail = $this->service->checkDuplicateEnquiry([
            'phone' => '8888888888',
            'email' => 'test@candidate.com',
        ]);
        $this->assertNotNull($duplicateByEmail);

        // 3. Post request should redirect back with warning
        $response = $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.store'), [
                'candidate_name' => 'Duplicate Name',
                'parent_name' => 'Duplicate Parent',
                'phone' => '9999999999',
                'status' => 'new',
            ]);

        $response->assertSessionHas('duplicate_found');
    }

    /**
     * Test admission notifications fire on enquiry creation and on status transitions.
     */
    public function test_enquiry_creation_and_confirmation_log_notifications()
    {
        \App\Models\NotificationSetting::create([
            'event_type' => 'admission_enquiry_received',
            'notification_type' => 'sms',
            'is_enabled' => true,
            'template_body' => 'Thanks for enquiring about {{candidate_name}}.',
            'created_by' => $this->admin->id,
        ]);
        \App\Models\NotificationSetting::create([
            'event_type' => 'admission_confirmed',
            'notification_type' => 'sms',
            'is_enabled' => true,
            'template_body' => '{{candidate_name}} has been confirmed for admission.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.store'), [
                'candidate_name' => 'Notify CRM Kid',
                'parent_name' => 'Notify CRM Parent',
                'phone' => '9998887770',
                'status' => 'new',
            ]);

        $enquiry = AdmissionEnquiry::where('candidate_name', 'Notify CRM Kid')->firstOrFail();
        $this->assertDatabaseHas('notification_logs', [
            'recipient_id' => $enquiry->id,
            'status' => 'sent',
        ]);
        $this->assertEquals(1, \App\Models\NotificationLog::where('recipient_id', $enquiry->id)->count());

        // Transitioning status to 'confirmed' should fire a second, distinct notification.
        $this->actingAs($this->receptionist)
            ->put(route('admin.front-office.enquiries.update', $enquiry->id), [
                'candidate_name' => $enquiry->candidate_name,
                'parent_name' => $enquiry->parent_name,
                'phone' => $enquiry->phone,
                'status' => 'confirmed',
            ]);

        $this->assertEquals(2, \App\Models\NotificationLog::where('recipient_id', $enquiry->id)->count());

        // A no-op update (status unchanged) must not create a third notification.
        $this->actingAs($this->receptionist)
            ->put(route('admin.front-office.enquiries.update', $enquiry->id), [
                'candidate_name' => $enquiry->candidate_name,
                'parent_name' => $enquiry->parent_name,
                'phone' => $enquiry->phone,
                'status' => 'confirmed',
            ]);

        $this->assertEquals(2, \App\Models\NotificationLog::where('recipient_id', $enquiry->id)->count());
    }

    /**
     * Test admission enquiry document upload and verification workflow.
     */
    public function test_receptionist_can_upload_and_verify_enquiry_documents()
    {
        Storage::fake('public');

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Doc Test Kid',
            'parent_name' => 'Doc Test Parent',
            'phone' => '9998887771',
            'status' => 'new',
            'counsellor_id' => $this->receptionist->id,
        ]);

        $file = UploadedFile::fake()->create('birth_certificate.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.documents.upload', $enquiry->id), [
                'documents' => [$file],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $document = AdmissionEnquiryDocument::where('admission_enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($document);
        $this->assertEquals('birth_certificate', $document->document_type);
        $this->assertEquals('birth_certificate.pdf', $document->original_filename);
        $this->assertFalse($document->is_verified);
        Storage::disk('public')->assertExists($document->document_path);

        // Verify the document
        $verifyResponse = $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.documents.verify', $document->id), [
                'is_verified' => 1,
                'verification_notes' => 'Looks good.',
            ]);
        $verifyResponse->assertRedirect();

        $document->refresh();
        $this->assertTrue($document->is_verified);
        $this->assertEquals($this->receptionist->id, $document->verified_by);
        $this->assertEquals('Looks good.', $document->verification_notes);
        $this->assertNotNull($document->verified_at);
    }

    /**
     * A counsellor who doesn't own the enquiry cannot upload/verify its documents.
     */
    public function test_non_owning_counsellor_cannot_manage_enquiry_documents()
    {
        Storage::fake('public');

        $otherCounsellor = User::factory()->create();
        $otherCounsellor->roles()->attach(Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher'])->id);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Guarded Kid',
            'parent_name' => 'Guarded Parent',
            'phone' => '9998887772',
            'status' => 'new',
            'counsellor_id' => $this->teacher->id,
        ]);

        $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

        $response = $this->actingAs($otherCounsellor)
            ->post(route('admin.front-office.enquiries.documents.upload', $enquiry->id), [
                'documents' => [$file],
            ]);
        $response->assertStatus(403);
    }

    /**
     * Test admission fee payment recording: auto-receipt generation, audit log, notification.
     */
    public function test_receptionist_can_record_admission_fee_payment()
    {
        \App\Models\NotificationSetting::create([
            'event_type' => 'admission_fee_received',
            'notification_type' => 'sms',
            'is_enabled' => true,
            'template_body' => 'Received {{amount}} ({{payment_mode}}) for {{candidate_name}}. Receipt: {{receipt_no}}.',
            'created_by' => $this->admin->id,
        ]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Fee Test Kid',
            'parent_name' => 'Fee Test Parent',
            'phone' => '9998887774',
            'status' => 'new',
            'counsellor_id' => $this->receptionist->id,
        ]);

        $response = $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.payments.record', $enquiry->id), [
                'amount' => 500,
                'payment_mode' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $payment = \App\Models\AdmissionEnquiryPayment::where('admission_enquiry_id', $enquiry->id)->first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('ADMFEE-', $payment->receipt_no);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals($this->receptionist->id, $payment->collected_by);
        // Defaults to the seeded "Admission" FeeType when none is specified.
        $this->assertEquals('Admission', $payment->feeType->name ?? null);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => \App\Models\AdmissionEnquiryPayment::class,
            'subject_id' => $payment->id,
            'description' => 'admission_fee_recorded',
        ]);

        $log = \App\Models\NotificationLog::where('recipient_id', $enquiry->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('500.00', $log->message);
        $this->assertStringContainsString($payment->receipt_no, $log->message);
    }

    /**
     * A supplied receipt number is honored instead of auto-generating one.
     */
    public function test_admission_fee_payment_honors_explicit_receipt_number()
    {
        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Explicit Receipt Kid',
            'parent_name' => 'Explicit Receipt Parent',
            'phone' => '9998887775',
            'status' => 'new',
            'counsellor_id' => $this->receptionist->id,
        ]);

        $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.payments.record', $enquiry->id), [
                'amount' => 1000,
                'payment_mode' => 'upi',
                'paid_at' => now()->format('Y-m-d'),
                'receipt_no' => 'MANUAL-0001',
            ]);

        $this->assertDatabaseHas('admission_enquiry_payments', [
            'admission_enquiry_id' => $enquiry->id,
            'receipt_no' => 'MANUAL-0001',
        ]);
    }

    /**
     * Non-owning counsellor cannot record a payment for someone else's enquiry.
     */
    public function test_non_owning_counsellor_cannot_record_payment()
    {
        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Guarded Fee Kid',
            'parent_name' => 'Guarded Fee Parent',
            'phone' => '9998887776',
            'status' => 'new',
            'counsellor_id' => $this->teacher->id,
        ]);

        $otherCounsellor = User::factory()->create();
        $otherCounsellor->roles()->attach(Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher'])->id);

        $response = $this->actingAs($otherCounsellor)
            ->post(route('admin.front-office.enquiries.payments.record', $enquiry->id), [
                'amount' => 500,
                'payment_mode' => 'cash',
                'paid_at' => now()->format('Y-m-d'),
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test that the admission CRM's mutating actions all leave an activity-log trail.
     */
    public function test_enquiry_lifecycle_actions_are_recorded_in_the_activity_log()
    {
        $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.store'), [
                'candidate_name' => 'Audit CRM Kid',
                'parent_name' => 'Audit CRM Parent',
                'phone' => '9998887773',
                'status' => 'new',
            ]);
        $enquiry = AdmissionEnquiry::where('candidate_name', 'Audit CRM Kid')->firstOrFail();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AdmissionEnquiry::class,
            'subject_id' => $enquiry->id,
            'description' => 'admission_enquiry_created',
            'causer_id' => $this->receptionist->id,
        ]);

        // Status change
        $this->actingAs($this->receptionist)
            ->put(route('admin.front-office.enquiries.update', $enquiry->id), [
                'candidate_name' => $enquiry->candidate_name,
                'parent_name' => $enquiry->parent_name,
                'phone' => $enquiry->phone,
                'status' => 'interested',
            ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AdmissionEnquiry::class,
            'subject_id' => $enquiry->id,
            'description' => 'admission_status_changed',
        ]);

        // Counsellor assignment
        $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.enquiries.counsellor', $enquiry->id), [
                'counsellor_id' => $this->teacher->id,
            ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AdmissionEnquiry::class,
            'subject_id' => $enquiry->id,
            'description' => 'admission_counsellor_assigned',
        ]);

        // Deletion
        $this->actingAs($this->receptionist)
            ->delete(route('admin.front-office.enquiries.destroy', $enquiry->id));
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AdmissionEnquiry::class,
            'subject_id' => $enquiry->id,
            'description' => 'admission_enquiry_deleted',
        ]);
    }

    /**
     * Test Appointment overlap conflict prevention.
     */
    public function test_appointment_overlap_prevention()
    {
        $date = Carbon::tomorrow()->toDateString();

        // 1. Create first meeting
        Appointment::create([
            'visitor_name' => 'Guest A',
            'teacher_id' => $this->teacher->id,
            'receptionist_id' => $this->receptionist->id,
            'scheduled_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'purpose' => 'Discussion',
            'status' => 'approved',
        ]);

        // 2. Validate conflict checks
        $hasOverlap = $this->service->checkTeacherOverlaps($this->teacher->id, $date, '10:30', '11:30');
        $this->assertTrue($hasOverlap);

        $noOverlap = $this->service->checkTeacherOverlaps($this->teacher->id, $date, '11:00', '12:00');
        $this->assertFalse($noOverlap);

        // 3. Post overlapping request should fail validation
        $response = $this->actingAs($this->receptionist)
            ->post(route('admin.front-office.appointments.store'), [
                'visitor_name' => 'Guest B',
                'teacher_id' => $this->teacher->id,
                'scheduled_date' => $date,
                'start_time' => '10:15',
                'end_time' => '11:15',
                'purpose' => 'Interviews',
                'status' => 'approved',
            ]);

        $response->assertSessionHasErrors('start_time');
    }

    /**
     * Test Student Gate Pass creation constraints.
     */
    public function test_student_gate_pass_creation_guards()
    {
        $student = Student::create([
            'name' => 'Jane Kid',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2016-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhaar_number' => '111111111111',
            'phone' => '1234567890',
            'address' => 'Street 1',
        ]);

        // 1. Create active gate pass for today
        GatePass::create([
            'pass_type' => 'student',
            'holder_name' => $student->name,
            'student_id' => $student->id,
            'purpose' => 'Doctor appointment',
            'request_date' => Carbon::today(),
            'departure_time' => '12:00',
            'status' => 'active',
        ]);

        // 2. Attempt to create another gate pass for the same student on the same day should fail
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $this->service->createGatePass([
            'pass_type' => 'student',
            'holder_name' => $student->name,
            'student_id' => $student->id,
            'purpose' => 'Parent request',
            'request_date' => Carbon::today(),
            'departure_time' => '14:00',
            'status' => 'pending',
        ]);
    }

    public function test_receptionist_can_view_gate_pass_details()
    {
        $student = Student::create([
            'name' => 'Jane Kid Two',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2016-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhaar_number' => '222222222222',
            'phone' => '1234567890',
            'address' => 'Street 1',
        ]);

        $pass = GatePass::create([
            'pass_type' => 'student',
            'holder_name' => $student->name,
            'student_id' => $student->id,
            'purpose' => 'Doctor appointment',
            'request_date' => Carbon::today(),
            'departure_time' => '12:00',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->receptionist)
            ->get(route('admin.front-office.gate-passes.show', $pass->id));

        $response->assertStatus(200);
        $response->assertViewIs('admin.front-office.gate-passes.show');
        $response->assertSee('Jane Kid Two');
    }

    public function test_receptionist_can_access_create_gate_pass_page_with_old_student_input()
    {
        $student = Student::create([
            'name' => 'Jane Old Kid',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2016-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhaar_number' => '333333333333',
            'phone' => '1234567890',
            'address' => 'Street 1',
        ]);

        // Flash old input
        $this->withSession(['_old_input' => [
            'pass_type' => 'student',
            'student_id' => $student->id,
        ]]);

        $response = $this->actingAs($this->receptionist)
            ->get(route('admin.front-office.gate-passes.create'));

        $response->assertStatus(200);
        $response->assertSee('Jane Old Kid');
    }

    public function test_receptionist_can_access_edit_gate_pass_page()
    {
        $student = Student::create([
            'name' => 'Jane Edit Kid',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2016-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhaar_number' => '444444444444',
            'phone' => '1234567890',
            'address' => 'Street 1',
        ]);

        $pass = GatePass::create([
            'pass_type' => 'student',
            'holder_name' => $student->name,
            'student_id' => $student->id,
            'purpose' => 'Dentist visit',
            'request_date' => Carbon::today(),
            'departure_time' => '12:00',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->receptionist)
            ->get(route('admin.front-office.gate-passes.edit', $pass->id));

        $response->assertStatus(200);
        $response->assertSee('Jane Edit Kid');
    }

    public function test_receptionist_can_access_create_appointment_page_with_old_input()
    {
        $teacher = User::factory()->create();
        $guardian = \App\Models\Guardian::create([
            'name' => 'Guardian One',
            'relationship' => 'Father',
            'phone' => '9876543210',
            'email' => 'guardian@example.com',
            'address' => 'Street 2',
        ]);

        // Flash old input
        $this->withSession(['_old_input' => [
            'guardian_id' => $guardian->id,
            'teacher_id' => $teacher->id,
        ]]);

        $response = $this->actingAs($this->receptionist)
            ->get(route('admin.front-office.appointments.create'));

        $response->assertStatus(200);
        $response->assertSee('Guardian One');
        $response->assertSee(e($teacher->name));
    }
}
