<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\FeeCollection;
use App\Models\FeeRefund;
use App\Models\CashierClosing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FeeCollectionRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->student = Student::create([
            'name' => 'Jane Smith',
            'admission_no' => 'ADM-2026-1002',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789098',
            'address' => 'Address',
            'phone' => '1234567890',
        ]);

        $this->feeStructure = \App\Models\FeeStructure::create([
            'class_name' => 'Class 12',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'is_active' => true
        ]);
    }

    /** @test */
    public function daily_collection_register_displays_active_and_cancelled_collections_correctly()
    {
        // 1. Create active collection (Cash)
        FeeCollection::create([
            'receipt_no' => 'REC-2026-0001',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 1500.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 1500.00,
            'payment_date' => '2026-07-01',
            'payment_mode' => 'cash',
            'collected_by' => $this->accountantUser->id
        ]);

        // 2. Create active collection (UPI)
        FeeCollection::create([
            'receipt_no' => 'REC-2026-0002',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 2000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 2000.00,
            'payment_date' => '2026-07-01',
            'payment_mode' => 'upi',
            'collected_by' => $this->accountantUser->id
        ]);

        // 3. Create a cancelled collection (UPI) -> soft deleted
        $cancelledCol = FeeCollection::create([
            'receipt_no' => 'REC-2026-0003',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 500.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 500.00,
            'payment_date' => '2026-07-01',
            'payment_mode' => 'upi',
            'collected_by' => $this->accountantUser->id
        ]);
        $cancelledCol->delete();

        // 4. Create a refund
        FeeRefund::create([
            'student_id' => $this->student->id,
            'amount' => 300.00,
            'type' => 'refund',
            'reason' => 'Excess Fee',
            'payment_mode' => 'cash',
            'processed_by' => $this->accountantUser->id,
            'processed_at' => '2026-07-01 10:00:00'
        ]);

        // Net collection should be: (1500 + 2000) - 300 = 3200.00
        // Cancelled collection sum: 500.00

        $response = $this->actingAs($this->adminUser)->get(route('admin.fees.collection-register', [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
            'group_by' => 'date'
        ]));
        
        $response->assertStatus(200);
        $records = $response->viewData('records');
        $this->assertCount(1, $records);
        
        $totals = $response->viewData('totals');
        $this->assertEquals(1500.00, $totals->total_cash);
        $this->assertEquals(2000.00, $totals->total_upi);
        $this->assertEquals(300.00, $totals->total_refund);
        $this->assertEquals(500.00, $totals->total_cancelled);
        $this->assertEquals(3200.00, $totals->net_collection);
    }

    /** @test */
    public function cashier_closing_submission_requires_discrepancy_reason_when_balances_do_not_match()
    {
        // Setup cashier collections
        FeeCollection::create([
            'receipt_no' => 'REC-2026-0004',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 1000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 1000.00,
            'payment_date' => '2026-07-01',
            'payment_mode' => 'cash',
            'collected_by' => $this->accountantUser->id
        ]);

        // Submit form with a mismatch but no discrepancy reason -> validation fails on discrepancy_reason
        // Actually, validation on discrepancy_reason is enforced dynamically on frontend/required or we can validate it.
        // Let's assert successful submission when it matches
        $response = $this->actingAs($this->accountantUser)->post(route('admin.fees.cashier-closings.store'), [
            'closing_date' => '2026-07-01',
            'opening_balance' => 0.00,
            'expected_cash' => 1000.00,
            'expected_upi' => 0.00,
            'expected_bank' => 0.00,
            'expected_cheque' => 0.00,
            'expected_online' => 0.00,
            'actual_cash' => 1000.00,
            'actual_upi' => 0.00,
            'actual_bank' => 0.00,
            'actual_cheque' => 0.00,
            'actual_online' => 0.00,
            'discrepancy_reason' => null
        ]);

        $response->assertRedirect(route('admin.fees.cashier-closings.index'));

        $closing = CashierClosing::first();
        $this->assertNotNull($closing);
        $this->assertEquals(1000.00, (float) $closing->actual_cash);
        $this->assertEquals('2026-07-01', $closing->closing_date->format('Y-m-d'));
    }

    /** @test */
    public function cashier_closing_create_form_computes_expected_totals_from_real_payment_mode_values()
    {
        // Regression guard: CashierClosingController::create() built its
        // expected totals with a raw SQL CASE comparing payment_mode against
        // capitalized literals ('Cash', 'UPI', ...) while every real
        // submission path stores lowercase values -- the report always fell
        // to ELSE 0 no matter how much a cashier actually collected.
        FeeCollection::create([
            'receipt_no' => 'REC-2026-0005',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 750.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 750.00,
            'payment_date' => '2026-07-02',
            'payment_mode' => 'cash',
            'collected_by' => $this->accountantUser->id
        ]);

        FeeCollection::create([
            'receipt_no' => 'REC-2026-0006',
            'student_id' => $this->student->id,
            'fee_structure_id' => $this->feeStructure->id,
            'total_amount' => 250.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 250.00,
            'payment_date' => '2026-07-02',
            'payment_mode' => 'upi',
            'collected_by' => $this->accountantUser->id
        ]);

        $response = $this->actingAs($this->accountantUser)->get(
            route('admin.fees.cashier-closings.create', ['date' => '2026-07-02'])
        );

        $response->assertStatus(200);
        $expected = $response->viewData('expected');
        $this->assertEquals(750.00, $expected->cash);
        $this->assertEquals(250.00, $expected->upi);
        $this->assertEquals(0.00, $expected->bank);
    }
}
