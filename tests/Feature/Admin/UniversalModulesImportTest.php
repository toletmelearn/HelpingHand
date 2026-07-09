<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Route;
use App\Models\ImportSession;
use App\Services\Imports\ImportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class UniversalModulesImportTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $importEngine;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);

        $this->actingAs($this->admin);

        $this->importEngine = app(ImportEngine::class);
    }

    public function test_teacher_import_lifecycle()
    {
        $csvContent = "Name,Email,Phone,Employee ID,Designation,Qualification,Gender,DOB,DOJ,Salary,Address\n" .
                      "T1,t1@school.com,9876543210,EMP101,Tutor,PhD,male,1985-05-12,2020-08-01,50000,Campus Address\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csvContent);
        $session = $this->importEngine->initializeSession('teachers', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'employee_id' => 'Employee ID',
            'designation' => 'Designation',
            'qualification' => 'Qualification',
            'gender' => 'Gender',
            'date_of_birth' => 'DOB',
            'date_of_joining' => 'DOJ',
            'salary' => 'Salary',
            'address' => 'Address'
        ];

        // 1. Dry run
        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);
        $this->assertEquals(0, Teacher::count()); // Rolled back

        // 2. Execute write
        $executeRes = $this->importEngine->execute($session->uuid, 'skip');
        $this->assertEquals(1, Teacher::count());
        $this->assertDatabaseHas('teachers', ['employee_id' => 'EMP101', 'email' => 't1@school.com']);

        // Imported teachers must not get a hardcoded default password — Teacher::password
        // isn't even used for authentication (the 'teacher' guard is backed by TeacherLogin),
        // so this used to just be a confusing, inert value ('123456') left on the record.
        $importedTeacher = Teacher::where('employee_id', 'EMP101')->first();
        $this->assertNull($importedTeacher->password);

        // 3. Rollback
        $this->importEngine->rollback($session->uuid);
        $this->assertEquals(0, Teacher::count());
    }

    public function test_parent_import_and_student_linking()
    {
        $student = Student::create([
            'name' => 'Child Student',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-05-12',
            'gender' => 'male',
            'mobile' => '9876543210',
            'admission_no' => 'ADM999',
            'class_id' => 1,
            'section_id' => 1,
            'address' => 'Student Home Address',
            'aadhar_number' => '123456789012',
            'roll_number' => 1
        ]);

        $csvContent = "Name,Phone,Email,Mobile,Admission No\n" .
                      "Parent Name,9876543210,parent@email.com,9876543210,ADM999\n";

        $file = UploadedFile::fake()->createWithContent('parents.csv', $csvContent);
        $session = $this->importEngine->initializeSession('parents', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name',
            'phone' => 'Phone',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'admission_number' => 'Admission No'
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'overwrite');

        $this->assertEquals(1, ParentModel::count());
        $parent = ParentModel::first();
        $this->assertEquals('Parent Name', $parent->name);

        // Bulk-imported parents must not get a hardcoded, publicly-known password —
        // ParentModel::password is the real Parent Portal login, unlike Teacher's.
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('123456', $parent->password));
        $this->assertTrue($parent->must_reset_password);

        // Verify relationship link on Student
        $student->refresh();
        $this->assertEquals($parent->id, $student->parent_id);

        // Rollback
        $this->importEngine->rollback($session->uuid);
        $this->assertEquals(1, ParentModel::count());
        $student->refresh();
        $this->assertEquals($parent->id, $student->parent_id);
    }

    public function test_class_and_section_import()
    {
        // Class Ingestion
        $classCsv = "Name,Class Order,Description\n" .
                    "Class 10,10,Secondary Class\n";

        $classFile = UploadedFile::fake()->createWithContent('classes.csv', $classCsv);
        $classSession = $this->importEngine->initializeSession('classes', $classFile, $this->admin->id);
        $classMappings = ['name' => 'Name', 'class_order' => 'Class Order', 'description' => 'Description'];

        $this->importEngine->dryRun($classSession->uuid, $classMappings);
        $this->importEngine->execute($classSession->uuid, 'skip');

        $this->assertEquals(1, SchoolClass::count());
        $this->assertDatabaseHas('school_classes', ['name' => 'Class 10', 'class_order' => 10]);

        // Section Ingestion
        $sectionCsv = "Name,Capacity,Description\n" .
                      "X,45,Tenth Section\n";

        $sectionFile = UploadedFile::fake()->createWithContent('sections.csv', $sectionCsv);
        $sectionSession = $this->importEngine->initializeSession('sections', $sectionFile, $this->admin->id);
        $sectionMappings = ['name' => 'Name', 'capacity' => 'Capacity', 'description' => 'Description'];

        $this->importEngine->dryRun($sectionSession->uuid, $sectionMappings);
        $this->importEngine->execute($sectionSession->uuid, 'skip');

        $this->assertEquals(1, Section::count());
        $this->assertDatabaseHas('sections', ['name' => 'X', 'capacity' => 45]);

        // Rollback sections
        $this->importEngine->rollback($sectionSession->uuid);
        $this->assertEquals(0, Section::count());

        // Rollback classes
        $this->importEngine->rollback($classSession->uuid);
        $this->assertEquals(0, SchoolClass::count());
    }

    public function test_subject_and_route_import()
    {
        // Subject Ingestion
        $subjectCsv = "Name,Code,Type\n" .
                      "Mathematics,MATH101,scholastic\n";

        $subjectFile = UploadedFile::fake()->createWithContent('subjects.csv', $subjectCsv);
        $subjectSession = $this->importEngine->initializeSession('subjects', $subjectFile, $this->admin->id);
        $subjectMappings = ['name' => 'Name', 'code' => 'Code', 'subject_type' => 'Type'];

        $this->importEngine->dryRun($subjectSession->uuid, $subjectMappings);
        $this->importEngine->execute($subjectSession->uuid, 'skip');

        $this->assertEquals(1, Subject::count());
        $this->assertDatabaseHas('subjects', ['code' => 'MATH101', 'subject_type' => 'scholastic']);

        // Route Ingestion
        $routeCsv = "Name,Start,End,Fare\n" .
                    "Route 109,Main Station,South Campus,1500\n";

        $routeFile = UploadedFile::fake()->createWithContent('routes.csv', $routeCsv);
        $routeSession = $this->importEngine->initializeSession('routes', $routeFile, $this->admin->id);
        $routeMappings = ['name' => 'Name', 'start_point' => 'Start', 'end_point' => 'End', 'monthly_fare' => 'Fare'];

        $this->importEngine->dryRun($routeSession->uuid, $routeMappings);
        $this->importEngine->execute($routeSession->uuid, 'skip');

        $this->assertEquals(1, Route::count());
        $this->assertDatabaseHas('routes', ['name' => 'Route 109', 'monthly_fare' => 1500]);

        // Rollback all
        $this->importEngine->rollback($routeSession->uuid);
        $this->importEngine->rollback($subjectSession->uuid);

        $this->assertEquals(0, Route::count());
        $this->assertEquals(0, Subject::count());
    }

    /**
     * Fee Structure Import was removed (2026-07): it only ever created an empty
     * FeeStructure header with no fee items/amounts attached, so it was pulled
     * from Data Management rather than left as a misleadingly "working" no-op.
     */
    public function test_fee_structure_import_is_not_registered()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->importEngine->getDefinition('fee-structures');
    }

    /**
     * Bulk-imported teachers must cover the same ground the old dedicated CSV
     * uploader did (Aadhaar, wing, teacher type, employment type, bank details,
     * experience) -- not just the handful of fields the universal engine's
     * Teacher Import originally shipped with.
     */
    public function test_teacher_import_covers_full_hr_field_set()
    {
        $csvContent = "Name,Email,Phone,Aadhar Number,Employee ID,Designation,Qualification,Subject Specialization,Gender,Date of Birth,Date of Joining,Salary,Address,Status,Employment Type,Wing,Teacher Type,Bank Account Number,IFSC Code,Experience Details\n" .
            "Priya Rao,priya.rao@school.com,9876543211,123456789012,EMP202,PGT,M.Sc,Physics,female,1988-04-10,2021-06-01,60000,Campus Housing,active,permanent,senior,teaching,123456789012,SBIN0002345,6 years\n";

        $file = UploadedFile::fake()->createWithContent('teachers_full.csv', $csvContent);
        $session = $this->importEngine->initializeSession('teachers', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
            'aadhar_number' => 'Aadhar Number', 'employee_id' => 'Employee ID', 'designation' => 'Designation',
            'qualification' => 'Qualification', 'subject_specialization' => 'Subject Specialization',
            'gender' => 'Gender', 'date_of_birth' => 'Date of Birth', 'date_of_joining' => 'Date of Joining',
            'salary' => 'Salary', 'address' => 'Address', 'status' => 'Status',
            'employment_type' => 'Employment Type', 'wing' => 'Wing', 'teacher_type' => 'Teacher Type',
            'bank_account_number' => 'Bank Account Number', 'ifsc_code' => 'IFSC Code',
            'experience_details' => 'Experience Details',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertDatabaseHas('teachers', [
            'employee_id' => 'EMP202',
            'aadhar_number' => '123456789012',
            'wing' => 'senior',
            'teacher_type' => 'teaching',
            'employment_type' => 'permanent',
            'bank_account_number' => '123456789012',
            'ifsc_code' => 'SBIN0002345',
        ]);
    }

    /**
     * Teacher Import must also cover the school's HR master-data spreadsheet fields:
     * PAN, emergency contact, relative name, correspondence/permanent address,
     * educational qualification detail, and teaching-load/responsibility notes.
     */
    public function test_teacher_import_covers_hr_master_data_fields()
    {
        $csvContent = "Name,Email,Phone,Employee ID,Designation,Gender,PAN Number,Emergency Contact,Relative Name,Permanent Address,Educational Qualification,Classes Taught,Periods,Class Section,Responsibilities\n" .
            "Anil Kumar,anil.kumar@school.com,9876500011,EMP303,TGT,male,ABCDE1234F,9998887770,Sunita Kumar,\"123 Village Road, District\",B.Ed in Mathematics,\"Class 6-8: Maths\",24,\"Class 7-B\",Exam Coordinator\n";

        $file = UploadedFile::fake()->createWithContent('teachers_hr.csv', $csvContent);
        $session = $this->importEngine->initializeSession('teachers', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone', 'employee_id' => 'Employee ID', 'designation' => 'Designation',
            'gender' => 'Gender', 'pan_number' => 'PAN Number', 'emergency_contact' => 'Emergency Contact',
            'relative_name' => 'Relative Name', 'permanent_address' => 'Permanent Address',
            'educational_qualification' => 'Educational Qualification', 'classes_taught' => 'Classes Taught',
            'no_of_periods' => 'Periods', 'class_section' => 'Class Section', 'responsibilities' => 'Responsibilities',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertDatabaseHas('teachers', [
            'employee_id' => 'EMP303',
            'pan_number' => 'ABCDE1234F',
            'emergency_contact' => '9998887770',
            'relative_name' => 'Sunita Kumar',
            'permanent_address' => '123 Village Road, District',
            'educational_qualification' => 'B.Ed in Mathematics',
            'classes_taught' => 'Class 6-8: Maths',
            'no_of_periods' => 24,
            'class_section' => 'Class 7-B',
            'responsibilities' => 'Exam Coordinator',
        ]);
    }
}
