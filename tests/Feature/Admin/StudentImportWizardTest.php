<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\ImportSession;
use App\Models\ImportError;
use App\Services\Imports\ImportEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentImportWizardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $importEngine;
    protected $testClass;
    protected $testSection;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);

        $this->actingAs($this->admin);

        // Seed Class and Section for lookups
        $this->testClass = SchoolClass::create([
            'name' => 'Grade 1',
            'class_order' => 1,
            'is_active' => true
        ]);

        $this->testSection = Section::create([
            'name' => 'A',
            'capacity' => 30
        ]);

        $this->importEngine = app(ImportEngine::class);
    }

    public function test_import_engine_session_initialization()
    {
        $csvContent = "Student Name,DOB,Gender,Father Mobile,Father Name,Mother Name,Class,Section,Aadhar,Roll No,Address\n" .
                      "John Doe,2018-05-12,Male,9876543210,Father Doe,Mother Doe,Grade 1,A,123456789012,10,123 School St\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);

        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $this->assertDatabaseHas('import_sessions', [
            'uuid' => $session->uuid,
            'module' => 'students',
            'status' => 'uploaded',
            'total_rows' => 1
        ]);

        $this->assertNotNull($session->column_mappings);
        $this->assertEquals('Grade 1', $session->settings['preview_rows'][0][6]);
    }

    public function test_dry_run_validation_reverts_database_writes_but_logs_errors()
    {
        // 1 row valid, 1 row invalid (missing father name, invalid DOB)
        $csvContent = "Student Name,DOB,Gender,Father Mobile,Father Name,Mother Name,Class,Section,Aadhar,Roll No,Address\n" .
                      "John Doe,2018-05-12,Male,9876543210,Father Doe,Mother Doe,Grade 1,A,123456789012,10,123 School St\n" .
                      "Jane Doe,invalid-date,Female,9876543211,,Mother Doe,Grade 1,A,123456789013,11,123 School St\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Student Name',
            'date_of_birth' => 'DOB',
            'gender' => 'Gender',
            'mobile' => 'Father Mobile',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'class' => 'Class',
            'section' => 'Section',
            'aadhaar_number' => 'Aadhar',
            'roll_number' => 'Roll No',
            'address' => 'Address'
        ];

        $res = $this->importEngine->dryRun($session->uuid, $mappings);

        $this->assertEquals('failed', $res['status']);
        $this->assertEquals(1, $res['success']);
        $this->assertEquals(1, $res['errors']);

        // Assert no student was written to DB because of transaction rollback
        $this->assertEquals(0, Student::count());

        // Assert errors were stored in database error log table
        $this->assertDatabaseHas('import_errors', [
            'import_session_id' => $session->id,
            'row_number' => 3
        ]);
    }

    public function test_successful_execute_imports_students_creates_parents_and_ledgers()
    {
        $csvContent = "Student Name,DOB,Gender,Father Mobile,Father Name,Mother Name,Class,Section,Aadhar,Roll No,Address\n" .
                      "John Doe,2018-05-12,Male,9876543210,Father Doe,Mother Doe,Grade 1,A,123456789012,10,123 School St\n" .
                      "Jimmy Doe,2017-06-15,Male,9876543210,Father Doe,Mother Doe,Grade 1,A,123456789015,12,123 School St\n"; // Sibling row matching on mobile

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Student Name',
            'date_of_birth' => 'DOB',
            'gender' => 'Gender',
            'mobile' => 'Father Mobile',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'class' => 'Class',
            'section' => 'Section',
            'aadhaar_number' => 'Aadhar',
            'roll_number' => 'Roll No',
            'address' => 'Address'
        ];

        // First complete dry run successfully
        $dryRunRes = $this->importEngine->dryRun($session->uuid, $mappings);
        $this->assertEquals('validated', $dryRunRes['status']);

        // Execute import
        $res = $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals('completed', $res['status']);
        $this->assertEquals(2, $res['success']);
        $this->assertEquals(0, $res['errors']);

        // Assert students created
        $this->assertEquals(2, Student::count());
        $this->assertDatabaseHas('students', ['name' => 'John Doe', 'class_id' => $this->testClass->id]);
        $this->assertDatabaseHas('students', ['name' => 'Jimmy Doe', 'class_id' => $this->testClass->id]);

        // Sibling deduplication: assert only one parent account created
        $this->assertEquals(1, ParentModel::count());
        $parent = ParentModel::first();
        $this->assertEquals('9876543210', $parent->mobile);

        // Assert both students point to the same parent
        $students = Student::all();
        $this->assertEquals($parent->id, $students[0]->parent_id);
        $this->assertEquals($parent->id, $students[1]->parent_id);

        // Assert financial accounts created for both
        $this->assertDatabaseHas('student_financial_accounts', ['student_id' => $students[0]->id]);
        $this->assertDatabaseHas('student_financial_accounts', ['student_id' => $students[1]->id]);
    }

    public function test_rollback_undo_deletes_imported_records_but_leaves_others_untouched()
    {
        // 1. Create a pre-existing student
        $existingStudent = Student::create([
            'name' => 'Pre Existing Student',
            'father_name' => 'Father Ex',
            'mother_name' => 'Mother Ex',
            'date_of_birth' => '2015-01-01',
            'gender' => 'male',
            'mobile' => '9999999999',
            'class_id' => $this->testClass->id,
            'section_id' => $this->testSection->id,
            'aadhaar_number' => '999999999999',
            'address' => 'Existing road'
        ]);

        // 2. Upload and execute import
        $csvContent = "Student Name,DOB,Gender,Father Mobile,Father Name,Mother Name,Class,Section,Aadhar,Roll No,Address\n" .
                      "John Doe,2018-05-12,Male,9876543210,Father Doe,Mother Doe,Grade 1,A,123456789012,10,123 School St\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csvContent);
        $session = $this->importEngine->initializeSession('students', $file, $this->admin->id);

        $mappings = [
            'name' => 'Student Name',
            'date_of_birth' => 'DOB',
            'gender' => 'Gender',
            'mobile' => 'Father Mobile',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'class' => 'Class',
            'section' => 'Section',
            'aadhaar_number' => 'Aadhar',
            'roll_number' => 'Roll No',
            'address' => 'Address'
        ];

        $this->importEngine->dryRun($session->uuid, $mappings);
        $this->importEngine->execute($session->uuid, 'skip');

        $this->assertEquals(2, Student::count()); // 1 existing + 1 imported

        // 3. Rollback the import session
        $this->importEngine->rollback($session->uuid);

        // 4. Assert imported student is deleted, but pre-existing remains
        $this->assertEquals(1, Student::count());
        $this->assertDatabaseHas('students', ['id' => $existingStudent->id]);
        $this->assertDatabaseMissing('students', ['name' => 'John Doe']);
    }
}
