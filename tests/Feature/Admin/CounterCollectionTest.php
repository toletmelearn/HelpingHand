<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\StudentFeeLedger;
use App\Models\StudentFeeAssignment;
use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class CounterCollectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Student $student;
    private SchoolClass $class;
    private FeeStructure $structure;
    private FeeType $tuitionFeeType;
    private FeeType $admissionFeeType;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create verified admin user and attach Spatie admin role
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now()
        ]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($role->id);

        // 2. Setup school class
        $this->class = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
        ]);

        // 3. Create student
        $this->student = Student::create([
            'name' => 'John Doe',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '987654321012',
            'address' => 'Address',
            'phone' => '9876543210',
            'mobile' => '9876543210',
            'admission_no' => 'ADM-5001',
            'class_id' => $this->class->id,
            'school_class_id' => $this->class->id,
            'section_id' => 1,
            'section' => 'A',
            'roll_number' => 12
        ]);

        // 4. Resolve/Create Fee Types
        $this->admissionFeeType = FeeType::where('name', 'LIKE', '%Admission%')->first()
            ?? FeeType::create(['name' => 'Admission Fee']);
        $this->tuitionFeeType = FeeType::where('name', 'LIKE', '%Tuition%')->first()
            ?? FeeType::create(['name' => 'Tuition Fee']);

        // 5. Create Fee Structure
        $this->structure = FeeStructure::create([
            'name' => 'Class 10 Structure',
            'class_name' => 'Class 10',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'status' => 'active'
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $this->structure->id,
            'fee_type_id' => $this->admissionFeeType->id,
            'amount' => 5000.00,
            'billing_frequency' => 'yearly',
            'charge_months' => ['April'],
            'due_day' => 10
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $this->structure->id,
            'fee_type_id' => $this->tuitionFeeType->id,
            'amount' => 3000.00,
            'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May'],
            'due_day' => 10
        ]);

        // 6. Assign structure to student (This automatically creates ledger debits chronologically)
        StudentFeeAssignment::create([
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->structure->id,
            'academic_year' => '2026-27',
            'status' => 'active'
        ]);
    }

    /** @test */
    public function it_loads_collect_fee_form_with_outstanding_invoices()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.fees.collect.form', $this->student->id));

        $response->assertStatus(200);
        $response->assertSee('Counter Fee Collection');
        $response->assertSee('Fee Charge: Admission - April');
        $response->assertSee('Fee Charge: Tuition - April');
        $response->assertSee('₹5,000.00');
        $response->assertSee('₹3,000.00');
    }

    /** @test */
    public function it_allocates_partial_payment_to_earliest_prioritized_invoices()
    {
        // Submit partial payment of ₹6,500.00
        // Expected allocation:
        // - ₹5,000.00 to Admission Fee (fully paid)
        // - ₹1,500.00 to Tuition Fee (leaving ₹1,500.00 unpaid on April Tuition)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.fees.store'), [
                'student_id' => $this->student->id,
                'total_amount' => 6500.00,
                'payment_mode' => 'cash',
                'payment_date' => '2026-04-12',
                'remarks' => 'Partial counter payment'
            ]);

        $response->assertRedirect();
        
        $collection = FeeCollection::latest()->first();
        $this->assertNotNull($collection);
        $this->assertEquals(6500.00, $collection->total_amount);

        // Verify items were created correctly
        $this->assertDatabaseHas('fee_collection_items', [
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $this->admissionFeeType->id,
            'amount' => 5000.00
        ]);

        $this->assertDatabaseHas('fee_collection_items', [
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $this->tuitionFeeType->id,
            'amount' => 1500.00
        ]);

        // Verify outstanding dues in ledger
        $admissionLedger = StudentFeeLedger::where('description', 'Fee Charge: Admission - April')->first();
        $tuitionLedger = StudentFeeLedger::where('description', 'Fee Charge: Tuition - April')->first();
        
        $this->assertEquals(0.00, $admissionLedger->unpaid_amount);
        $this->assertEquals(1500.00, $tuitionLedger->unpaid_amount);
    }

    /** @test */
    public function it_allocates_excess_payment_as_advance_credit()
    {
        // Clear all except Admission Fee from ledger to test excess/advance payment easily
        StudentFeeLedger::where('student_id', $this->student->id)
            ->where('description', 'NOT LIKE', '%Admission%')
            ->delete();

        // Check that only Admission Fee remains (₹5,000)
        $this->assertEquals(5000.00, \App\Services\LedgerService::getOutstandingBalance($this->student->id));

        // Submit payment of ₹7,500.00 (₹2,500.00 excess)
        // Expected allocation:
        // - ₹5,000.00 to Admission Fee
        // - ₹2,500.00 to Tuition Fee (as fallback advance allocation)
        $response = $this->actingAs($this->admin)
            ->post(route('admin.fees.store'), [
                'student_id' => $this->student->id,
                'total_amount' => 7500.00,
                'payment_mode' => 'cash',
                'payment_date' => '2026-04-12',
                'remarks' => 'Excess payment'
            ]);

        $response->assertRedirect();

        $collection = FeeCollection::latest()->first();
        
        $this->assertDatabaseHas('fee_collection_items', [
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $this->admissionFeeType->id,
            'amount' => 5000.00
        ]);

        $this->assertDatabaseHas('fee_collection_items', [
            'fee_collection_id' => $collection->id,
            'fee_type_id' => $this->tuitionFeeType->id, // Fallback type for advance
            'amount' => 2500.00
        ]);

        // Outstanding ledger balance should be -₹2,500.00 (credit)
        $this->assertEquals(-2500.00, \App\Services\LedgerService::getOutstandingBalance($this->student->id));
        
        // Post a new Tuition Fee charge of ₹3,000.00
        // It should automatically consume the ₹2,500.00 credit, leaving ₹500.00 unpaid
        \App\Services\LedgerService::postDebit(
            $this->student->id,
            '2026-05-01',
            'Tuition Fee - May',
            'fee_structure_item',
            99,
            3000.00
        );

        $newChargeLedger = StudentFeeLedger::where('description', 'Tuition Fee - May')->first();
        $this->assertEquals(500.00, $newChargeLedger->unpaid_amount);
    }

    /** @test */
    public function it_can_rollback_payment_restoring_dues()
    {
        // Keep only Admission Fee for clean verification
        StudentFeeLedger::where('student_id', $this->student->id)
            ->where('description', 'NOT LIKE', '%Admission%')
            ->delete();

        // Post Counter Collection of ₹5,000.00
        $response = $this->actingAs($this->admin)
            ->post(route('admin.fees.store'), [
                'student_id' => $this->student->id,
                'total_amount' => 5000.00,
                'payment_mode' => 'cash',
                'payment_date' => '2026-04-12',
                'remarks' => 'Payment to reverse'
            ]);

        $collection = FeeCollection::latest()->first();
        $charge = StudentFeeLedger::where('description', 'Fee Charge: Admission - April')->first();
        
        // Verify invoice is paid
        $this->assertEquals(0.00, $charge->unpaid_amount);

        // Rollback the payment
        $responseReverse = $this->actingAs($this->admin)
            ->post(route('admin.fees.reverse', $collection->id), [
                'reason' => 'Cheque bounced'
            ]);

        $responseReverse->assertRedirect();

        // Verify outstanding charge is marked unpaid again
        $charge->refresh();
        $this->assertEquals(5000.00, $charge->unpaid_amount);
        $this->assertEquals(5000.00, \App\Services\LedgerService::getOutstandingBalance($this->student->id));
    }
}
