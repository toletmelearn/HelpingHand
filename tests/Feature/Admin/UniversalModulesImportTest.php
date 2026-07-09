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
use App\Models\FeeStructure;
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

    public function test_subject_and_fee_structure_and_route_import()
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

        // Fee Structure Ingestion
        $feeCsv = "Class Name,Year,Frequency\n" .
                  "Class 10,2026-2027,monthly\n";

        $feeFile = UploadedFile::fake()->createWithContent('fee_structures.csv', $feeCsv);
        $feeSession = $this->importEngine->initializeSession('fee-structures', $feeFile, $this->admin->id);
        $feeMappings = ['class_name' => 'Class Name', 'academic_year' => 'Year', 'frequency' => 'Frequency'];

        $this->importEngine->dryRun($feeSession->uuid, $feeMappings);
        $this->importEngine->execute($feeSession->uuid, 'skip');

        $this->assertEquals(1, FeeStructure::count());
        $this->assertDatabaseHas('fee_structures', ['class_name' => 'Class 10', 'academic_year' => '2026-2027']);

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
        $this->importEngine->rollback($feeSession->uuid);
        $this->importEngine->rollback($subjectSession->uuid);

        $this->assertEquals(0, Route::count());
        $this->assertEquals(0, FeeStructure::count());
        $this->assertEquals(0, Subject::count());
    }
}
