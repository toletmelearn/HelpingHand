<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\AdmissionEnquiry;
use App\Models\SchoolClass;
use App\Models\ClassManagement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentAdmissionAndParentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin User Setup
        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
    }

    /** @test */
    public function admin_can_convert_selected_admission_enquiry_to_student()
    {
        $this->actingAs($this->admin);

        // Setup class and section
        $class = SchoolClass::create(['name' => 'Grade 1', 'class_order' => 1]);
        $section = ClassManagement::create(['name' => 'Grade 1', 'section' => 'A', 'capacity' => 30]);

        // Selected Enquiry
        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'John Doe Junior',
            'parent_name' => 'John Doe Senior',
            'phone' => '9876543210',
            'email' => 'john.doe@example.com',
            'status' => 'selected',
        ]);

        $response = $this->get(route('admin.admissions.confirm-form', $enquiry->id));
        $response->assertStatus(200);

        // Perform Conversion
        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2018-05-15',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '123456789012',
            'address' => '456 Garden Lane',
            'roll_number' => 10,
        ]);

        $response->assertRedirect(route('admin.admissions.index'));
        $response->assertSessionHas('success');

        // Check Enquiry Updated
        $this->assertEquals('admitted', $enquiry->refresh()->status);

        // Check Student Record Created
        $student = Student::where('name', 'John Doe Junior')->first();
        $this->assertNotNull($student);
        $this->assertEquals($class->id, $student->class_id);
        $this->assertEquals($section->id, $student->section_id);
        $this->assertStringStartsWith('ADM-', $student->admission_no);

        // Check Parent Record Auto-Created and Linked
        $parent = ParentModel::where('phone', '9876543210')->first();
        $this->assertNotNull($parent);
        $this->assertEquals($parent->id, $student->parent_id);
    }

    /** @test */
    public function receptionist_can_convert_confirmed_enquiry_to_student()
    {
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist'], ['display_name' => 'Receptionist']);
        $receptionist = User::factory()->create();
        $receptionist->roles()->attach($receptionistRole->id);
        $this->actingAs($receptionist);

        $class = SchoolClass::create(['name' => 'Grade 2', 'class_order' => 2]);
        $section = ClassManagement::create(['name' => 'Grade 2', 'section' => 'A', 'capacity' => 30]);

        // Front-office CRM flow only ever produces 'confirmed', never 'selected'.
        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Priya Sharma',
            'parent_name' => 'Ravi Sharma',
            'phone' => '9876500001',
            'email' => 'priya@example.com',
            'status' => 'confirmed',
        ]);

        $response = $this->get(route('admin.admissions.confirm-form', $enquiry->id));
        $response->assertStatus(200);

        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2017-03-10',
            'gender' => 'female',
            'category' => 'General',
        ]);

        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertEquals('admitted', $enquiry->refresh()->status);
        $this->assertNotNull(Student::where('name', 'Priya Sharma')->first());
    }

    /** @test */
    public function unauthorized_staff_cannot_confirm_admission()
    {
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant = User::factory()->create();
        $accountant->roles()->attach($accountantRole->id);
        $this->actingAs($accountant);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Unauthorized Test',
            'parent_name' => 'Some Parent',
            'phone' => '9876500002',
            'status' => 'selected',
        ]);

        $response = $this->get(route('admin.admissions.confirm-form', $enquiry->id));
        $response->assertStatus(403);

        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => 1,
            'section_id' => 1,
            'date_of_birth' => '2017-03-10',
            'gender' => 'female',
            'category' => 'General',
        ]);
        $response->assertStatus(403);
    }

    /** @test */
    public function confirm_admission_generates_unique_placeholder_aadhaar_and_random_parent_password()
    {
        $this->actingAs($this->admin);

        $class = SchoolClass::create(['name' => 'Grade 3', 'class_order' => 3]);
        $section = ClassManagement::create(['name' => 'Grade 3', 'section' => 'A', 'capacity' => 30]);

        $generatedAadhaar = [];

        for ($i = 0; $i < 3; $i++) {
            $enquiry = AdmissionEnquiry::create([
                'candidate_name' => "Placeholder Kid {$i}",
                'parent_name' => 'Placeholder Parent',
                'phone' => "98765100{$i}0",
                'status' => 'selected',
            ]);

            $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
                'class_id' => $class->id,
                'section_id' => $section->id,
                'date_of_birth' => '2016-01-01',
                'gender' => 'male',
                'category' => 'General',
                // aadhar_number intentionally omitted to exercise the placeholder generator
            ]);

            $response->assertSessionHas('success');
            $student = Student::where('name', "Placeholder Kid {$i}")->firstOrFail();
            $this->assertStringStartsWith('TBD-', $student->aadhar_number);
            $generatedAadhaar[] = $student->aadhar_number;

            // Parent password must never be the old fixed default, and must require a reset.
            $parent = ParentModel::where('student_id', $student->id)->first();
            $this->assertNotNull($parent);
            $this->assertFalse(\Illuminate\Support\Facades\Hash::check('123456', $parent->password));
            $this->assertTrue($parent->must_reset_password);
        }

        $this->assertCount(3, array_unique($generatedAadhaar), 'Generated placeholder Aadhaar numbers must be unique.');
    }

    /** @test */
    public function parent_with_temporary_password_is_forced_to_reset_before_reaching_dashboard()
    {
        $this->actingAs($this->admin);

        $class = SchoolClass::create(['name' => 'Grade 4', 'class_order' => 4]);
        $section = ClassManagement::create(['name' => 'Grade 4', 'section' => 'A', 'capacity' => 30]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Gate Test Kid',
            'parent_name' => 'Gate Test Parent',
            'phone' => '9876500099',
            'status' => 'selected',
        ]);

        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2015-06-01',
            'gender' => 'male',
            'category' => 'General',
        ]);
        $successMessage = $response->getSession()->get('success');

        // Extract the one-time temporary password from the staff-facing flash message.
        preg_match('/Temporary password: (\S+)/', $successMessage, $matches);
        $tempPassword = $matches[1] ?? null;
        $this->assertNotNull($tempPassword);

        $this->post('/logout'); // clear the admin session before switching to the parent guard

        $loginResponse = $this->post('/parent/login', [
            'login' => '9876500099',
            'password' => $tempPassword,
        ]);
        $loginResponse->assertRedirect('/parent/dashboard');

        // Following the redirect must land on the forced reset screen, not the dashboard.
        $dashboardResponse = $this->get('/parent/dashboard');
        $dashboardResponse->assertRedirect(route('parent.password.reset'));

        $resetResponse = $this->post(route('parent.password.update'), [
            'password' => 'MyNewSecurePassword1',
            'password_confirmation' => 'MyNewSecurePassword1',
        ]);
        $resetResponse->assertRedirect(route('parent.dashboard'));

        // Dashboard is now reachable directly.
        $this->get('/parent/dashboard')->assertStatus(200);

        $parent = ParentModel::where('phone', '9876500099')->first();
        $this->assertFalse($parent->must_reset_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('MyNewSecurePassword1', $parent->password));
    }

    /** @test */
    public function admission_confirmation_logs_a_notification_containing_the_temporary_password()
    {
        $this->actingAs($this->admin);

        $setting = \App\Models\NotificationSetting::create([
            'event_type' => 'admission_admitted',
            'notification_type' => 'both',
            'is_enabled' => true,
            'template_subject' => 'Welcome!',
            'template_body' => 'Dear {{parent_name}}, {{candidate_name}} is admitted. Admission No: {{admission_no}}. Login: {{login}} / Password: {{temp_password}}.',
            'created_by' => $this->admin->id,
        ]);

        $class = SchoolClass::create(['name' => 'Grade 5', 'class_order' => 5]);
        $section = ClassManagement::create(['name' => 'Grade 5', 'section' => 'A', 'capacity' => 30]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Notify Test Kid',
            'parent_name' => 'Notify Test Parent',
            'phone' => '9876500055',
            'email' => 'notify.parent@example.com',
            'status' => 'selected',
        ]);

        $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2016-02-02',
            'gender' => 'male',
            'category' => 'General',
        ]);

        $log = \App\Models\NotificationLog::where('notification_setting_id', $setting->id)
            ->where('recipient_id', $enquiry->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('sent', $log->status);
        $this->assertStringContainsString('Notify Test Kid', $log->message);
        $this->assertStringContainsString('9876500055', $log->message);
        $this->assertMatchesRegularExpression('/Password: \S+/', $log->message);
        $this->assertStringNotContainsString('{{', $log->message, 'Template placeholders must be fully substituted.');
    }

    /** @test */
    public function admission_status_transitions_are_recorded_in_the_activity_log()
    {
        $this->actingAs($this->admin);

        $class = SchoolClass::create(['name' => 'Grade 6', 'class_order' => 6]);
        $section = ClassManagement::create(['name' => 'Grade 6', 'section' => 'A', 'capacity' => 30]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Audit Test Kid',
            'parent_name' => 'Audit Test Parent',
            'phone' => '9876500077',
            'status' => 'selected',
        ]);

        $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2016-03-03',
            'gender' => 'male',
            'category' => 'General',
        ]);

        $activity = \Spatie\Activitylog\Models\Activity::where('subject_type', AdmissionEnquiry::class)
            ->where('subject_id', $enquiry->id)
            ->where('description', 'admission_status_changed')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals($this->admin->id, $activity->causer_id);
        $this->assertEquals('selected', $activity->properties['from']);
        $this->assertEquals('admitted', $activity->properties['to']);
    }

    /** @test */
    public function admission_confirmation_blocks_when_section_is_full_for_non_admin()
    {
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist'], ['display_name' => 'Receptionist']);
        $receptionist = User::factory()->create();
        $receptionist->roles()->attach($receptionistRole->id);
        $this->actingAs($receptionist);

        $class = SchoolClass::create(['name' => 'Grade 7', 'class_order' => 7]);
        $section = ClassManagement::create(['name' => 'Grade 7', 'section' => 'A', 'capacity' => 1]);

        Student::create([
            'name' => 'Existing Student',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '111122223333',
            'phone' => '9000000000',
            'address' => 'Somewhere',
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Overflow Kid',
            'parent_name' => 'Overflow Parent',
            'phone' => '9876500088',
            'status' => 'selected',
            'counsellor_id' => $receptionist->id,
        ]);

        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2016-04-04',
            'gender' => 'male',
            'category' => 'General',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull(Student::where('name', 'Overflow Kid')->first());
        $this->assertEquals('selected', $enquiry->refresh()->status);
    }

    /** @test */
    public function admin_can_override_full_section_capacity_and_it_is_audited()
    {
        $this->actingAs($this->admin);

        $class = SchoolClass::create(['name' => 'Grade 8', 'class_order' => 8]);
        $section = ClassManagement::create(['name' => 'Grade 8', 'section' => 'A', 'capacity' => 1]);

        Student::create([
            'name' => 'Existing Student Two',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '222233334444',
            'phone' => '9000000001',
            'address' => 'Somewhere',
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]);

        $enquiry = AdmissionEnquiry::create([
            'candidate_name' => 'Override Kid',
            'parent_name' => 'Override Parent',
            'phone' => '9876500089',
            'status' => 'selected',
        ]);

        $response = $this->post(route('admin.admissions.confirm-admission', $enquiry->id), [
            'class_id' => $class->id,
            'section_id' => $section->id,
            'date_of_birth' => '2016-05-05',
            'gender' => 'male',
            'category' => 'General',
            'override_capacity' => 1,
        ]);

        $response->assertRedirect(route('admin.admissions.index'));
        $this->assertNotNull(Student::where('name', 'Override Kid')->first());

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => AdmissionEnquiry::class,
            'subject_id' => $enquiry->id,
            'description' => 'admission_capacity_overridden',
        ]);
    }

    /** @test */
    public function disabled_notification_setting_suppresses_admission_notification()
    {
        $this->actingAs($this->admin);

        \App\Models\NotificationSetting::create([
            'event_type' => 'admission_enquiry_received',
            'notification_type' => 'sms',
            'is_enabled' => false,
            'template_subject' => 'Enquiry received',
            'template_body' => 'Thanks for enquiring about {{candidate_name}}.',
            'created_by' => $this->admin->id,
        ]);

        $this->post(route('admin.admissions.store-enquiry'), [
            'candidate_name' => 'Silent Test Kid',
            'parent_name' => 'Silent Test Parent',
            'phone' => '9876500066',
        ]);

        $enquiry = AdmissionEnquiry::where('candidate_name', 'Silent Test Kid')->first();
        $this->assertNotNull($enquiry);
        $this->assertEquals(0, \App\Models\NotificationLog::where('recipient_id', $enquiry->id)->count());
    }

    /** @test */
    public function bulk_import_deduplicates_parents_for_sibling_rows()
    {
        $this->actingAs($this->admin);

        $class = SchoolClass::create(['name' => 'Grade 1', 'class_order' => 1]);
        $section = ClassManagement::create(['name' => 'Grade 1', 'section' => 'A', 'capacity' => 30]);

        // Generate rows and compute correct verification hash
        $rows = [
            [
                'row_number' => 2,
                'original' => [
                    'name' => 'Sibling One',
                    'father_name' => 'Parent Doe',
                    'date_of_birth' => '2016-01-01',
                    'phone' => '9999988888',
                    'mobile' => '9999988888',
                    'gender' => 'male',
                    'category' => 'General',
                    'class_id' => $class->id,
                    'section_id' => $section->id,
                    'roll_number' => 1,
                ],
                'normalized' => [
                    'class_id' => $class->id,
                    'school_class_id' => $class->id,
                    'class' => $class->name,
                    'section_id' => $section->id,
                    'section' => 'A',
                ],
                'is_valid' => true,
                'errors' => [],
                'warnings' => []
            ],
            [
                'row_number' => 3,
                'original' => [
                    'name' => 'Sibling Two',
                    'father_name' => 'Parent Doe',
                    'date_of_birth' => '2017-02-02',
                    'phone' => '9999988888',
                    'mobile' => '9999988888',
                    'gender' => 'female',
                    'category' => 'General',
                    'class_id' => $class->id,
                    'section_id' => $section->id,
                    'roll_number' => 2,
                ],
                'normalized' => [
                    'class_id' => $class->id,
                    'school_class_id' => $class->id,
                    'class' => $class->name,
                    'section_id' => $section->id,
                    'section' => 'A',
                ],
                'is_valid' => true,
                'errors' => [],
                'warnings' => []
            ]
        ];

        $hash = hash('sha256', json_encode($rows));

        $previewData = [
            'preview_id' => 'test-preview-uuid',
            'created_at' => now()->timestamp,
            'hash' => $hash,
            'rows' => $rows,
            'summary' => [
                'total_rows' => 2,
                'valid_rows' => 2,
                'rows_with_errors' => 0,
                'rows_with_warnings' => 0,
            ]
        ];

        session(['student_import_preview' => $previewData]);

        // Post Apply Import
        $response = $this->post(route('students.import.csv.apply'), [
            'preview_id' => 'test-preview-uuid',
            'hash' => $hash
        ]);

        $response->assertRedirect(route('students.index'));
        $response->assertSessionHas('success');

        // Assert Sibling One and Sibling Two created
        $student1 = Student::where('name', 'Sibling One')->first();
        $student2 = Student::where('name', 'Sibling Two')->first();
        $this->assertNotNull($student1);
        $this->assertNotNull($student2);

        // Assert ONLY one ParentModel record created
        $this->assertEquals(1, ParentModel::where('phone', '9999988888')->count());

        $parent = ParentModel::where('phone', '9999988888')->first();
        $this->assertEquals($parent->id, $student1->parent_id);
        $this->assertEquals($parent->id, $student2->parent_id);
    }

    /** @test */
    public function parents_can_switch_sibling_contexts_securely()
    {
        // Setup parent with two linked students
        $parent = ParentModel::create([
            'name' => 'Father Doe',
            'email' => 'father.doe@example.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
        ]);

        $student1 = Student::create([
            'name' => 'Child One',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2016-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '111111111111',
            'phone' => '1234567890',
            'address' => 'Street 1',
            'parent_id' => $parent->id,
        ]);

        $student2 = Student::create([
            'name' => 'Child Two',
            'father_name' => 'Father Doe',
            'mother_name' => 'Mother Doe',
            'date_of_birth' => '2017-02-02',
            'gender' => 'female',
            'category' => 'General',
            'aadhar_number' => '222222222222',
            'phone' => '1234567890',
            'address' => 'Street 1',
            'parent_id' => $parent->id,
        ]);

        $parent->update(['student_id' => $student1->id]);

        $this->actingAs($parent, 'parent');

        // Request dashboard: should show Child One by default
        $response = $this->get(route('parent.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Welcome, Child One');
        $this->assertEquals($student1->id, session('active_student_id'));

        // Post Switch Student to Child Two
        $response = $this->post(route('parent.switch-student', $student2->id));
        $response->assertRedirect();
        $this->assertEquals($student2->id, session('active_student_id'));

        // Request dashboard: should now display Child Two welcome message
        $response = $this->get(route('parent.dashboard'));
        $response->assertSee('Welcome, Child Two');
        $response->assertDontSee('Welcome, Child One');

        // Try to switch to an unauthorized student ID
        $unauthorizedStudent = Student::create([
            'name' => 'Stranger',
            'father_name' => 'Other',
            'mother_name' => 'Other',
            'date_of_birth' => '2018-03-03',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '333333333333',
            'phone' => '0000000000',
            'address' => 'Street 2',
        ]);

        $response = $this->post(route('parent.switch-student', $unauthorizedStudent->id));
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_parents_and_reset_passwords()
    {
        $this->actingAs($this->admin);

        $parent = ParentModel::create([
            'name' => 'Test Parent Profile',
            'email' => 'parent.profile@example.com',
            'phone' => '1112223333',
            'password' => bcrypt('password'),
        ]);

        // View parent details
        $response = $this->get(route('admin.parents.show', $parent->id));
        $response->assertStatus(200);
        $response->assertSee('Test Parent Profile');

        // Update profile
        $response = $this->put(route('admin.parents.update', $parent->id), [
            'name' => 'Updated Parent Name',
            'email' => 'parent.profile@example.com',
            'phone' => '1112223333',
            'mobile' => '4445556666',
            'status' => 'inactive',
        ]);
        $response->assertRedirect(route('admin.parents.show', $parent->id));
        $this->assertEquals('Updated Parent Name', $parent->refresh()->name);
        $this->assertEquals('inactive', $parent->status);

        // Reset password
        $response = $this->post(route('admin.parents.reset-password', $parent->id), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $response->assertRedirect(route('admin.parents.show', $parent->id));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $parent->refresh()->password));
    }
}
