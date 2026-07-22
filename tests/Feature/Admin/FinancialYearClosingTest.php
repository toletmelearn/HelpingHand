<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicSession;
use App\Models\StudentFeeLedger;
use App\Models\FeeType;
use App\Models\FinancialYearClosing;
use App\Services\LedgerService;
use App\Services\FinancialYearClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class FinancialYearClosingTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;
    protected $schoolClass;
    protected $section;
    protected $sessionOld;
    protected $sessionNew;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
            'is_active' => true
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'Jane Doe',
            'admission_no' => 'ADM-2026-6666',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);

        // Academic Sessions
        $this->sessionOld = AcademicSession::create([
            'name' => '2024-25',
            'code' => '2024-25',
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_current' => false,
            'is_active' => true
        ]);

        $this->sessionNew = AcademicSession::create([
            'name' => '2025-26',
            'code' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => true,
            'is_active' => true
        ]);
    }

    /** @test */
    public function guests_cannot_access_year_closing_portal()
    {
        $response = $this->get(route('admin.fees.year-closing.index'));
        $response->assertRedirect('/login');
    }

    /** @test */
    public function accountant_can_access_portal_and_stage_closing()
    {
        // 1. Post a debit item in old session (unpaid) to simulate dues
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-10',
            'description' => 'Tuition Fee May 24',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1500.00,
            'credit' => 0.00,
            'running_balance' => 1500.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 1500.00
        ]);

        // Access index page
        $response = $this->actingAs($this->accountantUser)->get(route('admin.fees.year-closing.index'));
        $response->assertStatus(200);
        $response->assertSee('Financial Year Closing');

        // Stage closing
        $responseStage = $this->actingAs($this->accountantUser)->post(route('admin.fees.year-closing.stage'), [
            'from_session_code' => '2024-25',
            'to_session_code' => '2025-26',
            'remarks' => 'Staging test session closing'
        ]);

        $responseStage->assertRedirect();
        
        // Assert opening balance debit was created in new year
        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'description' => 'Opening Balance (Arrears from Session 2024-25)',
            'debit' => 1500.00,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing'
        ]);
    }

    /** @test */
    public function closing_can_be_rolled_back_in_staged_status()
    {
        // 1. Stage a closing
        $closing = FinancialYearClosingService::stageClosing('2024-25', '2025-26', 'Rollback Test', $this->accountantUser->id);

        $this->assertDatabaseHas('financial_year_closings', [
            'id' => $closing->id,
            'status' => 'staged'
        ]);

        // Rollback
        $responseRollback = $this->actingAs($this->accountantUser)->post(route('admin.fees.year-closing.rollback'), [
            'closing_id' => $closing->id
        ]);

        $responseRollback->assertRedirect();

        // Closing entry should be deleted
        $this->assertDatabaseMissing('financial_year_closings', ['id' => $closing->id]);
        
        // Ledger entry should be removed
        $this->assertDatabaseMissing('student_fee_ledgers', [
            'reference_type' => 'year_closing',
            'reference_id' => $closing->id
        ]);
    }

    /** @test */
    public function closing_can_be_confirmed_and_disables_edits_for_closed_session()
    {
        // 1. Stage closing
        $closing = FinancialYearClosingService::stageClosing('2024-25', '2025-26', 'Confirm Test', $this->accountantUser->id);

        // 2. Confirm closing
        $responseConfirm = $this->actingAs($this->accountantUser)->post(route('admin.fees.year-closing.confirm'), [
            'closing_id' => $closing->id
        ]);

        $responseConfirm->assertRedirect();

        $this->assertDatabaseHas('financial_year_closings', [
            'id' => $closing->id,
            'status' => 'confirmed'
        ]);

        // Old session should be marked inactive
        $this->assertDatabaseHas('academic_sessions', [
            'code' => '2024-25',
            'is_active' => 0
        ]);

        // 3. Try posting to the closed session -> Should fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("This transaction is blocked because academic session '2024-25' is closed/frozen.");

        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-06-10',
            'description' => 'Blocked Tuition Fee',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'running_balance' => 1000.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 1000.00
        ]);
    }

    /** @test */
    public function closing_carries_forward_all_four_streams_and_archives_ledger()
    {
        // 1. Setup specific ledger items in old session
        // Arrears
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-10',
            'description' => 'Unpaid Tuition Fee',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1000.00,
            'credit' => 0.00,
            'running_balance' => 1000.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 1000.00
        ]);

        // Refund outstanding
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-11',
            'description' => 'Unsettled Refund',
            'reference_type' => 'fee_refund',
            'reference_id' => 2,
            'debit' => 400.00,
            'credit' => 0.00,
            'running_balance' => 1400.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 400.00
        ]);

        // Scholarship outstanding
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-12',
            'description' => 'Unused Scholarship',
            'reference_type' => 'scholarship_applied',
            'reference_id' => 3,
            'debit' => 0.00,
            'credit' => 600.00,
            'running_balance' => 800.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 0.00
        ]);

        // Advance outstanding
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-13',
            'description' => 'Advance Payment',
            'reference_type' => 'fee_collection',
            'reference_id' => 4,
            'debit' => 0.00,
            'credit' => 700.00,
            'running_balance' => 100.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 0.00
        ]);

        // Stage the closing
        $closing = FinancialYearClosingService::stageClosing('2024-25', '2025-26', 'Staging Four Streams', $this->accountantUser->id);

        $this->assertEquals(1000.00, (float)$closing->total_balance_carried);
        $this->assertEquals(400.00, (float)$closing->total_refund_carried);
        $this->assertEquals(600.00, (float)$closing->total_scholarship_carried);
        $this->assertEquals(700.00, (float)$closing->total_advance_carried);

        // Verify staged opening balance entries exist in new year
        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'description' => 'Opening Balance (Arrears from Session 2024-25)',
            'debit' => 1000.00,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing'
        ]);

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'description' => 'Opening Balance (Refund from Session 2024-25)',
            'debit' => 400.00,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing'
        ]);

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'description' => 'Opening Balance (Scholarship from Session 2024-25)',
            'credit' => 600.00,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing'
        ]);

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'description' => 'Opening Balance (Advance from Session 2024-25)',
            'credit' => 700.00,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing'
        ]);

        // Confirm the closing
        FinancialYearClosingService::confirmClosing($closing->id);

        // Verify that the old year's ledger entries are marked as archived
        $archivedCount = StudentFeeLedger::where('academic_year', '2024-25')
            ->where('is_archived', true)
            ->count();
        $this->assertEquals(4, $archivedCount);

        // Verify that new year opening balances are NOT archived
        $newYearCount = StudentFeeLedger::where('academic_year', '2025-26')
            ->where('is_archived', false)
            ->count();
        $this->assertEquals(4, $newYearCount);
    }
}
