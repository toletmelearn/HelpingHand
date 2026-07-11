<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\AcademicSession;
use App\Models\User;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Route;
use App\Models\ImportError;
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
            "Priya Rao,priya.rao@school.com,9876543211,123456789012,EMP202,PGT,M.Sc,Physics,female,1988-04-10,2021-06-01,60000,Campus Housing,active,permanent,senior,PGT,123456789012,SBIN0002345,6 years\n";

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
            'teacher_type' => 'PGT',
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

    /**
     * A real HR spreadsheet will have gaps -- a missing PAN here, a blank
     * Aadhaar there. Only the teacher's name is a hard requirement at import
     * time; every other blank cell must import as null rather than reject
     * the row, so staff can fill it in later from the teacher's edit form.
     */
    public function test_teacher_import_tolerates_blank_optional_fields()
    {
        $csvContent = "Name,Email,Phone,Employee ID,Designation,Gender,Aadhar Number,PAN Number\n" .
            "Bare Minimum,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('teachers_sparse.csv', $csvContent);
        $session = $this->importEngine->initializeSession('teachers', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'email' => 'Email', 'phone' => 'Phone',
            'employee_id' => 'Employee ID', 'designation' => 'Designation',
            'gender' => 'Gender', 'aadhar_number' => 'Aadhar Number', 'pan_number' => 'PAN Number',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertDatabaseHas('teachers', [
            'name' => 'Bare Minimum',
            'email' => null,
            'phone' => null,
            'employee_id' => null,
            'designation' => null,
            'aadhar_number' => null,
            'pan_number' => null,
        ]);
    }

    /**
     * Real HR spreadsheets are messy: an unparseable date, "PRT ART" in a
     * numbers-only "No. of Periods" column, a mistyped PAN, a blank "Status"
     * cell that would otherwise send an explicit NULL into a NOT NULL column.
     * None of that should reject the row -- only 'name' is a hard requirement.
     */
    public function test_teacher_import_tolerates_malformed_optional_fields()
    {
        $csvContent = "Name,Employee ID,PAN Number,Date of Joining,No. of Periods,Status\n" .
            "Rajendra Kumar,PNS7847,EOPPK7798Q1234,31.08.2024,I - V (CLUB ACTIVITY),\n" .
            "Palash Biswas,PNS8684,,not-a-real-date,24,\n";

        $file = UploadedFile::fake()->createWithContent('teachers_messy.csv', $csvContent);
        $session = $this->importEngine->initializeSession('teachers', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'employee_id' => 'Employee ID', 'pan_number' => 'PAN Number',
            'date_of_joining' => 'Date of Joining', 'no_of_periods' => 'No. of Periods', 'status' => 'Status',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(2, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals(2, Teacher::count());

        // "31.08.2024" parses fine; the non-numeric periods cell is dropped to null;
        // blank status falls back to the DB default instead of erroring.
        $this->assertDatabaseHas('teachers', [
            'employee_id' => 'PNS7847',
            'date_of_joining' => '2024-08-31 00:00:00',
            'no_of_periods' => null,
            'status' => 'active',
        ]);

        // Garbled date and a malformed-but-harmless PAN both degrade gracefully.
        $this->assertDatabaseHas('teachers', [
            'employee_id' => 'PNS8684',
            'date_of_joining' => null,
            'no_of_periods' => 24,
            'status' => 'active',
        ]);
    }

    /**
     * The bulk Student import spreadsheet lets staff mark each row NEW or OLD so
     * the fee module knows who owes an Admission Fee vs. only the Annual Fee.
     * Only an explicit "NEW" should stamp the current academic session; blank
     * or "OLD" (or anything else) must default to "old/continuing".
     */
    public function test_student_import_admission_type_column_sets_admission_session_id()
    {
        $session = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csvContent = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address,Admission Type (NEW/OLD)\n" .
            "New Kid,Father A,Mother A,2016-01-01,male,9998880001,Class 6,A,Somewhere,NEW\n" .
            "Old Kid,Father B,Mother B,2015-01-01,female,9998880002,Class 6,A,Somewhere,OLD\n" .
            "Blank Type Kid,Father C,Mother C,2015-06-01,male,9998880003,Class 6,A,Somewhere,\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);
        $importSession = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
            'admission_type' => 'Admission Type (NEW/OLD)',
        ];

        $dryRunRes = $this->importEngine->dryRun($importSession->uuid, $mappings);
        $this->assertEquals(3, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($importSession->uuid, 'skip');

        $newKid = Student::where('name', 'New Kid')->firstOrFail();
        $oldKid = Student::where('name', 'Old Kid')->firstOrFail();
        $blankKid = Student::where('name', 'Blank Type Kid')->firstOrFail();

        $this->assertEquals($session->id, $newKid->admission_session_id);
        $this->assertNull($oldKid->admission_session_id);
        $this->assertNull($blankKid->admission_session_id);
    }

    /** @test */
    public function student_import_overwrite_preserves_admission_session_id_when_column_left_blank()
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);
        SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);
        Section::create(['name' => 'A']);

        // First import: mark the student NEW.
        $firstCsv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address,Admission Type (NEW/OLD)\n" .
            "Repeat Kid,Father A,Mother A,2016-01-01,male,9998880011,Class 6,A,Somewhere,NEW\n";
        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
            'admission_type' => 'Admission Type (NEW/OLD)',
        ];

        $file1 = UploadedFile::fake()->createWithContent('students1.csv', $firstCsv);
        $session1 = $this->importEngine->initializeSession('students', $file1, $this->admin->id);
        $this->importEngine->dryRun($session1->uuid, $mappings);
        $this->importEngine->execute($session1->uuid, 'skip');

        $student = Student::where('name', 'Repeat Kid')->firstOrFail();
        $this->assertNotNull($student->admission_session_id);

        // Second import: same student (matches by name+dob), Admission Type left
        // blank, resolved as 'overwrite' -- must NOT wipe the value just set.
        $secondCsv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address,Admission Type (NEW/OLD)\n" .
            "Repeat Kid,Father A,Mother A,2016-01-01,male,9998880011,Class 6,A,Updated Address,\n";
        $file2 = UploadedFile::fake()->createWithContent('students2.csv', $secondCsv);
        $session2 = $this->importEngine->initializeSession('students', $file2, $this->admin->id);
        $this->importEngine->dryRun($session2->uuid, $mappings);
        $this->importEngine->execute($session2->uuid, 'overwrite');

        $this->assertEquals(1, Student::where('name', 'Repeat Kid')->count(), 'Overwrite must update the existing student, not create a second one.');

        $student->refresh();
        $this->assertNotNull($student->admission_session_id);
        $this->assertEquals('Updated Address', $student->address);
    }

    /**
     * Real admission spreadsheets frequently leave father/mother name, address,
     * Aadhaar, roll number, etc. blank for many rows -- exactly the shape
     * reported in production ("EKANSH GUPTA","ANKIT GUPTA","SAKSHI GUPTA",
     * "16-Apr-21","M","8077294244","8279824193","UKG","B",...,"" with a blank
     * Address column). None of that should reject the row; only name, DOB,
     * class, and section are hard requirements.
     */
    public function test_student_import_tolerates_blank_optional_fields_matching_real_spreadsheet()
    {
        SchoolClass::create(['name' => 'UKG', 'class_order' => 1, 'is_active' => true]);
        Section::create(['name' => 'B']);

        $headers = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Phone,Class,Section,Aadhar Number,Roll Number,Religion,Caste,Blood Group,Address,Sibling Admission No,Admission No,Admission Type (NEW/OLD)\n";
        $row = "EKANSH GUPTA,ANKIT GUPTA,SAKSHI GUPTA,16-Apr-21,M,8077294244,8279824193,UKG,B,,,,,,,,1815,\n";

        $file = UploadedFile::fake()->createWithContent('students_real.csv', $headers . $row);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'phone' => 'Phone', 'class' => 'Class', 'section' => 'Section',
            'aadhar_number' => 'Aadhar Number', 'roll_number' => 'Roll Number',
            'religion' => 'Religion', 'caste' => 'Caste', 'blood_group' => 'Blood Group',
            'address' => 'Address', 'sibling_admission_no' => 'Sibling Admission No',
            'admission_no' => 'Admission No', 'admission_type' => 'Admission Type (NEW/OLD)',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $student = Student::where('name', 'EKANSH GUPTA')->firstOrFail();
        $this->assertEquals('ANKIT GUPTA', $student->father_name);
        $this->assertEquals('SAKSHI GUPTA', $student->mother_name);
        $this->assertEquals('Not Specified', $student->address);
        $this->assertEquals('1815', $student->admission_no);
        $this->assertNull($student->aadhar_number);
        $this->assertNull($student->roll_number);
    }

    /**
     * dryRun() used to collect every failing row's error into one array and
     * insert them all in a single SQL statement -- for a large real spreadsheet
     * with many failing rows, that one statement could exceed the DB server's
     * max_allowed_packet, which MySQL/MariaDB responds to by dropping the
     * connection ("MySQL server has gone away") rather than a normal error.
     * Errors are now inserted in small batches; verify none go missing.
     */
    public function test_dry_run_records_every_error_when_many_rows_fail_validation()
    {
        $rowCount = 250;
        $csv = "Name,Class,Section\n";
        for ($i = 1; $i <= $rowCount; $i++) {
            // No 'class' value at all -- every row fails the 'class' required rule.
            $csv .= "Student {$i},,\n";
        }

        $file = UploadedFile::fake()->createWithContent('students_bulk_fail.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = ['name' => 'Name', 'class' => 'Class', 'section' => 'Section'];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);

        $this->assertEquals($rowCount, $dryRunRes['errors']);
        $this->assertEquals($rowCount, ImportError::where('import_session_id', $session->id)->count());
    }

    /**
     * The reported "Allowed memory size ... exhausted" happened specifically
     * on a real .xlsx upload -- no existing test exercised that code path at
     * all (only .csv fixtures). readFileRows() now loads .xlsx via
     * setReadDataOnly(true) instead of the default IOFactory::load(), which
     * otherwise builds a full styles/formatting object graph for every cell.
     * Verify a real xlsx file (built with actual styling, like a real
     * school-produced spreadsheet) still parses correctly end-to-end.
     */
    public function test_student_import_parses_a_real_xlsx_file_with_styling()
    {
        SchoolClass::create(['name' => 'Class 3', 'class_order' => 3, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Name', 'Father Name', 'Mother Name', 'Date of Birth', 'Gender', 'Mobile', 'Class', 'Section', 'Address'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['Xlsx Kid', 'Father X', 'Mother X', '2017-03-05', 'female', '9998880099', 'Class 3', 'A', 'Somewhere'], null, 'A2');

        // Apply real styling/formatting -- the actual memory cost IOFactory::load()
        // pulls in that setReadDataOnly() is meant to skip.
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('CCCCCC');
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_test_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpPath);
        $fileBytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        $file = UploadedFile::fake()->createWithContent('students_real.xlsx', $fileBytes);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $this->assertEquals($headers, $session->settings['headers']);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertDatabaseHas('students', ['name' => 'Xlsx Kid', 'mobile' => '9998880099']);
    }

    /**
     * Real-world Excel files often have a "used range" far larger than their
     * actual data -- a stray formatting click can make a sheet with 2 real
     * rows report thousands of rows/columns to PhpSpreadsheet. The previous
     * unbounded getRowIterator()/getCellIterator() walked that whole inflated
     * range for every file, multiplying memory use for no reason. Verify a
     * sheet with real data confined to a few cells, but formatting applied far
     * beyond it, still parses to exactly the real data -- proving the
     * getHighestDataRow/Column bound doesn't drop legitimate rows while it
     * skips the phantom range.
     */
    public function test_student_import_ignores_inflated_worksheet_dimensions()
    {
        SchoolClass::create(['name' => 'Class 4', 'class_order' => 4, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Name', 'Father Name', 'Mother Name', 'Date of Birth', 'Gender', 'Mobile', 'Class', 'Section', 'Address'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray(['Bounded Kid', 'Father B', 'Mother B', '2016-09-10', 'male', '9998880088', 'Class 4', 'A', 'Somewhere'], null, 'A2');

        // Simulate the real-world "accidentally formatted way beyond the data"
        // case: a fill color applied out to column AZ and row 3000, with no
        // actual data there.
        $sheet->getStyle('A1:AZ3000')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EEEEEE');

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_bloat_test_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmpPath);
        $fileBytes = file_get_contents($tmpPath);
        unlink($tmpPath);

        $file = UploadedFile::fake()->createWithContent('students_bloated.xlsx', $fileBytes);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals(1, Student::where('name', 'Bounded Kid')->count());
    }

    /**
     * Reported directly: a mismatched Class value gave a plain "not found"
     * error with no clue what value would actually work. It must now list
     * the valid class names so the fix is self-explanatory. Uses a value the
     * normalizer genuinely can't resolve (out of the 1-12 range) so this
     * keeps testing the true "not found" path rather than a recognized
     * variant like "1st" (covered separately -- see
     * test_student_import_accepts_common_class_name_variants).
     */
    public function test_student_import_class_not_found_error_lists_valid_class_names()
    {
        SchoolClass::create(['name' => 'Class 1', 'class_order' => 1, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Mismatch Kid,Father M,Mother M,2016-01-01,male,9998880077,Robotics Club,A,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_bad_class.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['errors']);

        $error = ImportError::where('import_session_id', $session->id)->firstOrFail();
        $this->assertStringContainsString("Class 'Robotics Club' not found", $error->error_message);
        $this->assertStringContainsString('Class 1', $error->error_message);
    }

    /** @test */
    public function student_import_class_lookup_tolerates_incidental_whitespace()
    {
        SchoolClass::create(['name' => 'Class 2', 'class_order' => 2, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Whitespace Kid,Father W,Mother W,2016-01-01,male,9998880066, Class 2 ,A,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_whitespace_class.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);
    }

    /**
     * Reported directly, end-to-end: staff wrote plain "1" in the Class
     * column (the system's configured class is "Class 1"). Different schools
     * write the same class differently -- digit, ordinal, roman numeral, word
     * name, with/without a "Class"/"Grade"/"Std" prefix -- none of that
     * should be rejected as "not found" when it unambiguously means an
     * existing configured class.
     */
    public function test_student_import_accepts_common_class_name_variants()
    {
        SchoolClass::create(['name' => 'Class 1', 'class_order' => 1, 'is_active' => true]);
        SchoolClass::create(['name' => 'Class 11 Science', 'class_order' => 20, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Digit Kid,Father A,Mother A,2016-01-01,male,9998880001,1,A,Somewhere\n" .
            "Ordinal Kid,Father B,Mother B,2016-01-02,male,9998880002,1st,A,Somewhere\n" .
            "Roman Kid,Father C,Mother C,2016-01-03,male,9998880003,I,A,Somewhere\n" .
            "Word Kid,Father D,Mother D,2016-01-04,male,9998880004,One,A,Somewhere\n" .
            "Grade Kid,Father E,Mother E,2016-01-05,male,9998880005,Grade 1,A,Somewhere\n" .
            "Std Kid,Father F,Mother F,2016-01-06,male,9998880006,Std 1,A,Somewhere\n" .
            "Streamed Kid,Father G,Mother G,2016-01-07,male,9998880007,XI Science,A,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_class_variants.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(7, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $classOneId = SchoolClass::where('name', 'Class 1')->value('id');
        $classElevenScienceId = SchoolClass::where('name', 'Class 11 Science')->value('id');

        foreach (['Digit Kid', 'Ordinal Kid', 'Roman Kid', 'Word Kid', 'Grade Kid', 'Std Kid'] as $name) {
            $this->assertDatabaseHas('students', ['name' => $name, 'class_id' => $classOneId]);
        }
        $this->assertDatabaseHas('students', ['name' => 'Streamed Kid', 'class_id' => $classElevenScienceId]);
    }

    /**
     * A bare "11"/"XI" with no stream must NOT be silently guessed -- this
     * school has three separate 11th-grade classes (Science/Commerce/Arts)
     * and guessing wrong would misplace the student.
     */
    public function test_student_import_does_not_guess_ambiguous_streamed_class()
    {
        SchoolClass::create(['name' => 'Class 11 Science', 'class_order' => 20, 'is_active' => true]);
        SchoolClass::create(['name' => 'Class 11 Commerce', 'class_order' => 21, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Ambiguous Kid,Father A,Mother A,2016-01-01,male,9998880001,11,A,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_ambiguous_class.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['errors']);

        $error = ImportError::where('import_session_id', $session->id)->firstOrFail();
        $this->assertStringContainsString("Class '11' not found", $error->error_message);
    }

    /**
     * Reported directly: a real roster had plain "XI"/"XII" (no stream) in
     * the Class column. A school that configures 11th/12th WITHOUT splitting
     * by stream (a plain "Class 11" exists, alongside or instead of the
     * streamed variants) should have bare "XI"/"11" resolve straight to it --
     * this is the safe case the ambiguity guard is meant to allow, as
     * opposed to guessing a specific stream that was never mentioned.
     */
    public function test_student_import_resolves_bare_11_12_when_a_plain_class_exists()
    {
        SchoolClass::create(['name' => 'Class 11', 'class_order' => 14, 'is_active' => true]);
        SchoolClass::create(['name' => 'Class 11 Science', 'class_order' => 20, 'is_active' => true]);
        Section::create(['name' => 'A']);

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Plain Eleven Kid,Father A,Mother A,2016-01-01,male,9998880001,XI,A,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_plain_class11.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(1, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $plainClass11Id = SchoolClass::where('name', 'Class 11')->value('id');
        $this->assertDatabaseHas('students', ['name' => 'Plain Eleven Kid', 'class_id' => $plainClass11Id]);
    }

    /**
     * Reported directly against a real roster: the Section column holds
     * plain letters ("A", "B") for some students but stream-like labels
     * ("COM", "Science (PCB)", "Science (PCM)", "Commerce", "Humanities")
     * for others, plus a stray "XI-" typo in the Class column. A section is
     * an arbitrary school-chosen label with no universal convention (unlike
     * a class, which has a real, fixed academic meaning) -- there's no
     * "wrong guess" risk in creating whatever the sheet says, so an
     * unrecognized section must now auto-create rather than block the row.
     */
    public function test_student_import_auto_creates_unrecognized_sections()
    {
        SchoolClass::create(['name' => 'Class 11', 'class_order' => 14, 'is_active' => true]);
        SchoolClass::create(['name' => 'Class 12', 'class_order' => 15, 'is_active' => true]);
        Section::create(['name' => 'A']); // pre-existing, must not be duplicated

        $csv = "Name,Father Name,Mother Name,Date of Birth,Gender,Mobile,Class,Section,Address\n" .
            "Plain Section Kid,Father A,Mother A,2016-01-01,male,9998880001,XI,A,Somewhere\n" .
            "Dashed Class Kid,Father B,Mother B,2016-01-02,male,9998880002,XI-,A,Somewhere\n" .
            "Commerce Short Kid,Father C,Mother C,2016-01-03,male,9998880003,XI,COM,Somewhere\n" .
            "PCB Kid,Father D,Mother D,2016-01-04,male,9998880004,XII,Science (PCB),Somewhere\n" .
            "PCM Kid,Father E,Mother E,2016-01-05,male,9998880005,XII,Science (PCM),Somewhere\n" .
            "Commerce Kid,Father F,Mother F,2016-01-06,male,9998880006,XII,Commerce,Somewhere\n" .
            "Humanities Kid,Father G,Mother G,2016-01-07,male,9998880007,XII,Humanities,Somewhere\n";

        $file = UploadedFile::fake()->createWithContent('students_new_sections.csv', $csv);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Name', 'father_name' => 'Father Name', 'mother_name' => 'Mother Name',
            'date_of_birth' => 'Date of Birth', 'gender' => 'Gender', 'mobile' => 'Mobile',
            'class' => 'Class', 'section' => 'Section', 'address' => 'Address',
        ];

        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals(7, $dryRunRes['success']);
        $this->assertEquals(0, $dryRunRes['errors']);

        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals(7, Student::whereIn('name', [
            'Plain Section Kid', 'Dashed Class Kid', 'Commerce Short Kid',
            'PCB Kid', 'PCM Kid', 'Commerce Kid', 'Humanities Kid',
        ])->count());

        // The pre-existing "A" section was reused, not duplicated.
        $this->assertEquals(1, Section::where('name', 'A')->count());

        foreach (['COM', 'Science (PCB)', 'Science (PCM)', 'Commerce', 'Humanities'] as $newSection) {
            $this->assertDatabaseHas('sections', ['name' => $newSection]);
        }

        $dashedClassKid = Student::where('name', 'Dashed Class Kid')->firstOrFail();
        $this->assertEquals(SchoolClass::where('name', 'Class 11')->value('id'), $dashedClassKid->class_id);
    }
}
