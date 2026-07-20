<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\FeeType;
use App\Models\StudentFeeLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FeeDemandRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $regularUser;
    protected $student;
    protected $schoolClass;
    protected $section;
    protected $feeType;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        // 2. Create Users
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->regularUser = User::factory()->create(['role' => 'teacher']);

        // 3. Create Class and Section
        $this->schoolClass = SchoolClass::create(['name' => 'Class 12', 'class_order' => 12]);
        $this->section = Section::create(['name' => 'Section A', 'school_class_id' => $this->schoolClass->id]);

        // 4. Create Student
        $this->student = Student::create([
            'name' => 'John Doe',
            'admission_no' => 'ADM-2026-0001',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Address',
            'phone' => '1234567890',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id,
        ]);

        $this->feeType = FeeType::create([
            'name' => 'Tuition Fee',
            'code' => 'TUIT'
        ]);
    }

    /** @test */
    public function only_authorized_users_can_access_fee_demand_register()
    {
        // Unauthenticated -> Redirect to login
        $response = $this->get(route('admin.fees.demand-register'));
        $response->assertStatus(302);

        // Regular user -> Redirect or Forbidden
        $response = $this->actingAs($this->regularUser)->get(route('admin.fees.demand-register'));
        $response->assertStatus(403);

        // Accountant -> OK
        $response = $this->actingAs($this->accountantUser)->get(route('admin.fees.demand-register'));
        $response->assertStatus(200);

        // Admin -> OK
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register'));
        $response->assertStatus(200);
    }

    /** @test */
    public function database_conditional_aggregations_calculate_correct_financials()
    {
        // 1. Post a Fee Demand (Debit: reference_type = fee_structure_item)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-15',
            'description' => 'June 2026 Tuition Fee',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1200.00,
            'credit' => 0.00,
            'fee_type_id' => $this->feeType->id,
            'academic_year' => '2026-27'
        ]);

        // 2. Post a Discount (Credit: description containing 'Discount')
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-15',
            'description' => 'Sibling Discount Applied',
            'reference_type' => 'fee_collection',
            'reference_id' => 1,
            'debit' => 0.00,
            'credit' => 200.00,
            'academic_year' => '2026-27'
        ]);

        // 3. Post a Late Fine (Debit: reference_type = fee_collection)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-20',
            'description' => 'Late Fine Charged (Receipt No: 101)',
            'reference_type' => 'fee_collection',
            'reference_id' => 1,
            'debit' => 50.00,
            'credit' => 0.00,
            'academic_year' => '2026-27'
        ]);

        // 4. Post a Collection/Payment (Credit: reference_type = fee_collection, description no Discount)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-20',
            'description' => 'Tuition Fee Payment (Receipt No: 101)',
            'reference_type' => 'fee_collection',
            'reference_id' => 1,
            'debit' => 0.00,
            'credit' => 800.00,
            'academic_year' => '2026-27'
        ]);

        // 5. Post a Refund (Debit: reference_type = fee_refund)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-25',
            'description' => 'Excess Fee Refund Issued',
            'reference_type' => 'fee_refund',
            'reference_id' => 1,
            'debit' => 100.00,
            'credit' => 0.00,
            'academic_year' => '2026-27'
        ]);

        // Outstanding should be: (1200 + 50 + 100) - (200 + 800) = 1350 - 1000 = 350.00

        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register'));
        $response->assertStatus(200);

        // Verify the rendered financial fields in view variables
        $records = $response->viewData('records');
        $this->assertCount(1, $records);
        
        $row = $records->first();
        $this->assertEquals(1200.00, (float) $row->fee_demand);
        $this->assertEquals(200.00, (float) $row->discount);
        $this->assertEquals(50.00, (float) $row->late_fee);
        $this->assertEquals(800.00, (float) $row->collected);
        $this->assertEquals(100.00, (float) $row->refund);
        $this->assertEquals(350.00, (float) $row->outstanding);
    }

    /** @test */
    public function filtering_works_correctly_on_all_filters()
    {
        // Student 1 (Class 12, Section A, Outstanding > 0)
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-15',
            'description' => 'Tuition Fee',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 500.00,
            'credit' => 0.00,
            'fee_type_id' => $this->feeType->id,
            'academic_year' => '2026-27'
        ]);

        // Student 2 (Class 10, Outstanding = 0)
        $class10 = SchoolClass::create(['name' => 'Class 10', 'class_order' => 10]);
        $student2 = Student::create([
            'name' => 'Alice Smith',
            'admission_no' => 'ADM-2026-0002',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789023',
            'address' => 'Address',
            'phone' => '1234567890',
            'school_class_id' => $class10->id,
        ]);

        StudentFeeLedger::create([
            'student_id' => $student2->id,
            'date' => '2026-06-15',
            'description' => 'Tuition Fee Paid',
            'reference_type' => 'fee_collection',
            'reference_id' => 2,
            'debit' => 0.00,
            'credit' => 500.00,
            'academic_year' => '2026-27'
        ]);

        // Filter by class_id
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register', ['class_id' => $this->schoolClass->id]));
        $this->assertCount(1, $response->viewData('records'));
        $this->assertEquals($this->student->name, $response->viewData('records')->first()->student_name);

        // Filter by status (unpaid)
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register', ['status' => 'unpaid']));
        $this->assertCount(1, $response->viewData('records'));
        $this->assertEquals($this->student->name, $response->viewData('records')->first()->student_name);

        // Filter by status (paid)
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register', ['status' => 'paid']));
        $this->assertCount(1, $response->viewData('records'));
        $this->assertEquals($student2->name, $response->viewData('records')->first()->student_name);
    }

    /** @test */
    public function export_endpoints_stream_valid_responses()
    {
        // Excel/CSV export response check
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register.export', ['format' => 'excel']));
        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=fee-demand-register-' . now()->format('Y-m-d') . '.csv');

        // Print export check
        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.demand-register.export', ['format' => 'print']));
        $response->assertStatus(200);
        $response->assertViewIs('admin.fees.demand-register-print');
    }
}
