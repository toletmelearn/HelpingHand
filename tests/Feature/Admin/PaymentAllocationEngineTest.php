<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\StudentFeeAssignment;
use App\Models\StudentFeeLedger;
use App\Models\AdminConfiguration;
use App\Models\AuditLog;
use App\Services\PaymentAllocationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaymentAllocationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $student;
    protected $feeStructure;

    protected function setUp(): void
    {
        parent::setUp();

        // Standard setup matching existing database structures
        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now()
        ]);
        $role = \App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($role->id);

        $class = \App\Models\SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
        ]);

        $this->student = Student::create([
            'name' => 'Jane Doe',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '987654321012',
            'address' => 'Address',
            'phone' => '1234567890',
            'mobile' => '1234567890',
            'admission_no' => 'ADM-1002',
            'class_id' => $class->id,
            'school_class_id' => $class->id,
            'section_id' => 1,
            'section' => 'A',
            'roll_number' => 5
        ]);

        $this->feeStructure = FeeStructure::create([
            'class_name' => 'Class 10',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'is_active' => true
        ]);

        StudentFeeAssignment::create([
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'academic_year' => '2026-27',
            'assigned_date' => '2026-06-01',
            'effective_from' => '2026-06-01',
            'status' => 'active'
        ]);

        // Clean up or seed default configurations if needed
        AdminConfiguration::updateOrCreate(
            ['module' => 'fee', 'key' => 'payment_allocation_policy'],
            [
                'value' => [
                    'rules' => [
                        ['name' => 'mandatory_first', 'enabled' => true, 'weight' => 1000],
                        ['name' => 'current_session_first', 'enabled' => true, 'weight' => 500],
                        ['name' => 'current_month_first', 'enabled' => true, 'weight' => 200],
                        ['name' => 'priority_based', 'enabled' => true, 'weight' => 100],
                        ['name' => 'oldest_due_first', 'enabled' => true, 'weight' => 50]
                    ],
                    'priority_list' => ['admission', 'tuition', 'late_fine', 'late_fee', 'transport']
                ],
                'type' => 'json',
                'label' => 'Payment Allocation Policy',
                'is_active' => true
            ]
        );
    }

    /** @test */
    public function test_auto_allocation_applies_priority_scoring_correctly()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $transportFeeType = FeeType::create([
            'name' => 'Transport Fee',
            'is_optional' => true, // Optional!
            'status' => 'active'
        ]);

        // Create debits
        // 1. Optional Transport Fee - older date (date: 2026-06-01, amount: 500)
        $debit1 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Transport Fee - June 2026',
            'reference_type' => 'App\Models\TransportFee',
            'reference_id' => 1,
            'debit' => 500.00,
            'credit' => 0.00,
            'unpaid_amount' => 500.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $transportFeeType->id
        ]);

        // 2. Mandatory Tuition Fee - newer date (date: 2026-06-15, amount: 1000)
        $debit2 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-15',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 2,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        // Prioritize debits
        $prioritized = PaymentAllocationEngine::getPrioritizedDebits($this->student->id);

        // Tuition Fee is mandatory (is_optional = false), so it should rank higher than optional Transport Fee
        // despite Transport Fee having an older due date.
        $this->assertEquals($debit2->id, $prioritized->first()->id);
        $this->assertEquals($debit1->id, $prioritized->last()->id);

        // Let's allocate 1200
        $result = PaymentAllocationEngine::autoAllocate($this->student->id, 1200.00);

        // It should fully pay Tuition Fee (1000) and partially pay Transport Fee (200)
        $this->assertCount(2, $result['allocations']);
        $this->assertEquals(1000.00, $result['allocations'][0]['amount']);
        $this->assertEquals($debit2->id, $result['allocations'][0]['debit']->id);

        $this->assertEquals(200.00, $result['allocations'][1]['amount']);
        $this->assertEquals($debit1->id, $result['allocations'][1]['debit']->id);
        $this->assertEquals(0.00, $result['advance']);
    }

    /** @test */
    public function test_updating_policy_weights_updates_auto_allocation_ordering()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $transportFeeType = FeeType::create([
            'name' => 'Transport Fee',
            'is_optional' => true, // Optional!
            'status' => 'active'
        ]);

        // 1. Optional Transport Fee - older date (date: 2026-06-01, amount: 500)
        $debit1 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Transport Fee - June 2026',
            'reference_type' => 'App\Models\TransportFee',
            'reference_id' => 1,
            'debit' => 500.00,
            'credit' => 0.00,
            'unpaid_amount' => 500.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $transportFeeType->id
        ]);

        // 2. Mandatory Tuition Fee - newer date (date: 2026-06-15, amount: 1000)
        $debit2 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-15',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 2,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        // Change config weights to prioritize oldest_due_first over mandatory_first
        AdminConfiguration::set('fee', 'payment_allocation_policy', [
            'rules' => [
                ['name' => 'mandatory_first', 'enabled' => true, 'weight' => 5], // Lower weight!
                ['name' => 'oldest_due_first', 'enabled' => true, 'weight' => 1000], // High weight!
            ]
        ], 'json');

        $prioritized = PaymentAllocationEngine::getPrioritizedDebits($this->student->id);

        // Now, older Transport Fee must be prioritized over newer Tuition Fee
        $this->assertEquals($debit1->id, $prioritized->first()->id);
        $this->assertEquals($debit2->id, $prioritized->last()->id);
    }

    /** @test */
    public function test_manual_override_validation_and_ledger_processing()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $debit1 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        $debit2 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-02',
            'description' => 'Tuition Fee - July 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 2,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        // 1. Sum of allocations exceeds payment amount
        $result = PaymentAllocationEngine::validateManualAllocation(
            $this->student->id,
            1500.00,
            [$debit1->id => 1000.00, $debit2->id => 600.00]
        );
        $this->assertFalse($result['is_valid']);
        $this->assertContains("Sum of manual allocations (₹1,600.00) exceeds the total payment amount of ₹1,500.00.", $result['errors']);

        // 2. Allocation exceeds outstanding unpaid amount
        $result = PaymentAllocationEngine::validateManualAllocation(
            $this->student->id,
            1500.00,
            [$debit1->id => 1100.00]
        );
        $this->assertFalse($result['is_valid']);
        $this->assertContains("Allocation amount of ₹1,100.00 for 'Tuition Fee - June 2026' exceeds its unpaid amount of ₹1,000.00.", $result['errors']);

        // 3. Valid allocations with advance credit remaining
        $result = PaymentAllocationEngine::validateManualAllocation(
            $this->student->id,
            1500.00,
            [$debit1->id => 400.00, $debit2->id => 800.00]
        );
        $this->assertTrue($result['is_valid']);
        $this->assertEquals(300.00, $result['advance']);

        // Perform request posting to verify full workflow, controller store and audit logs
        $payload = [
            'student_id' => $this->student->id,
            'total_amount' => 1500.00,
            'payment_mode' => 'cash',
            'payment_date' => '2026-06-30',
            'remarks' => 'Manual Override Test',
            'manual_allocation_override' => true,
            'manual_allocations' => [
                $debit1->id => 400.00,
                $debit2->id => 800.00
            ]
        ];

        $response = $this->actingAs($this->adminUser)->post(route('admin.fees.store'), $payload);
        $response->assertRedirect();

        // Verify debits updated correctly in database
        $this->assertEquals(600.00, $debit1->fresh()->unpaid_amount); // 1000 - 400 = 600
        $this->assertEquals(200.00, $debit2->fresh()->unpaid_amount); // 1000 - 800 = 200

        // Verify audit log generated detailing exact allocations
        $auditLog = AuditLog::where('action', 'payment_allocation')->first();
        $this->assertNotNull($auditLog);
        $details = json_decode($auditLog->new_value, true);
        $this->assertEquals('manual_override', $details['allocation_type']);
        $this->assertEquals(1500.00, $details['payment_amount']);
        $this->assertEquals(300.00, $details['advance_credited']);
        $this->assertCount(2, $details['allocations']);
    }

    /** @test */
    public function test_persistent_allocations_table_records_auto_allocations()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $debit = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        // Post auto allocation payment
        $payload = [
            'student_id' => $this->student->id,
            'total_amount' => 600.00,
            'payment_mode' => 'cash',
            'payment_date' => '2026-06-30',
            'remarks' => 'Auto Allocation Test',
        ];

        $response = $this->actingAs($this->adminUser)->post(route('admin.fees.store'), $payload);
        $response->assertRedirect();

        // Find the created credit ledger entry
        $creditLedger = StudentFeeLedger::where('student_id', $this->student->id)
            ->where('credit', 600.00)
            ->first();
        $this->assertNotNull($creditLedger);

        // Verify the database record in payment_allocations
        $allocation = \App\Models\PaymentAllocation::where('credit_ledger_id', $creditLedger->id)
            ->where('debit_ledger_id', $debit->id)
            ->first();

        $this->assertNotNull($allocation);
        $this->assertEquals(600.00, $allocation->amount);
        $this->assertEquals('auto', $allocation->allocation_type);
        $this->assertEquals(400.00, $debit->fresh()->unpaid_amount);
    }

    /** @test */
    public function test_rebuild_unpaid_amounts_preserves_manual_overrides()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $debit1 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        $debit2 = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-02',
            'description' => 'Tuition Fee - July 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 2,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        // Post manual override collection
        $payload = [
            'student_id' => $this->student->id,
            'total_amount' => 1500.00,
            'payment_mode' => 'cash',
            'payment_date' => '2026-06-30',
            'remarks' => 'Manual Override Test',
            'manual_allocation_override' => true,
            'manual_allocations' => [
                $debit1->id => 500.00,
                $debit2->id => 1000.00
            ]
        ];

        $response = $this->actingAs($this->adminUser)->post(route('admin.fees.store'), $payload);
        $response->assertRedirect();

        // Verify initial override
        $this->assertEquals(500.00, $debit1->fresh()->unpaid_amount);
        $this->assertEquals(0.00, $debit2->fresh()->unpaid_amount);

        // Run Ledger rebuild
        \App\Services\LedgerService::rebuildUnpaidAmounts($this->student->id);

        // Verify that manual overrides are still preserved (June is 500, July is 0)
        // rather than the June fee being fully paid (0 due) and July having 500 due.
        $this->assertEquals(500.00, $debit1->fresh()->unpaid_amount);
        $this->assertEquals(0.00, $debit2->fresh()->unpaid_amount);
    }

    /** @test */
    public function test_duplicated_allocations_prevented_by_unique_constraint()
    {
        $tuitionFeeType = FeeType::create([
            'name' => 'Tuition Fee',
            'is_optional' => false,
            'status' => 'active'
        ]);

        $debit = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Tuition Fee - June 2026',
            'reference_type' => 'App\Models\FeeStructureDetail',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'unpaid_amount' => 1000.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        $credit = StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2026-06-01',
            'description' => 'Payment',
            'reference_type' => 'App\Models\FeeCollection',
            'reference_id' => 1,
            'debit' => 0.00,
            'credit' => 500.00,
            'unpaid_amount' => 0.00,
            'academic_year' => '2026-27',
            'fee_type_id' => $tuitionFeeType->id
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        \App\Models\PaymentAllocation::create([
            'student_id' => $this->student->id,
            'credit_ledger_id' => $credit->id,
            'debit_ledger_id' => $debit->id,
            'amount' => 100.00,
            'allocation_type' => 'auto'
        ]);

        // Trying to create a duplicate mapping should fail the unique constraint
        \App\Models\PaymentAllocation::create([
            'student_id' => $this->student->id,
            'credit_ledger_id' => $credit->id,
            'debit_ledger_id' => $debit->id,
            'amount' => 200.00,
            'allocation_type' => 'auto'
        ]);
    }
}
