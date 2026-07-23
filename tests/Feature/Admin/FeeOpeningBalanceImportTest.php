<?php

namespace Tests\Feature\Admin;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\ImportSession;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\User;
use App\Services\BulkFeeAssignmentService;
use App\Services\Imports\FeeOpeningBalanceImportDefinition;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeOpeningBalanceImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudentWithLiveDebits(): Student
    {
        SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);

        $tuition = FeeType::firstOrCreate(['name' => 'Tuition Fee'], ['status' => 'active']);
        $annual = FeeType::firstOrCreate(['name' => 'Annual Charges'], ['status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Class 6', 'academic_year' => '2025-2026',
            'frequency' => 'custom', 'status' => 'active',
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 2000, 'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $annual->id,
            'amount' => 6000, 'billing_frequency' => 'session_wise_continuing', 'charge_months' => ['Annual'],
        ]);

        $student = Student::create([
            'name' => 'Opening Balance Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887772', 'address' => 'Somewhere', 'admission_no' => 'ADM-OB-001',
        ]);

        BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        return $student;
    }

    /** @test */
    public function it_credits_the_exact_matching_debit_for_monthly_quarterly_and_annual_periods()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-uuid-1', 'module' => 'fee_opening_balance', 'status' => 'processing']);

        $definition = new FeeOpeningBalanceImportDefinition();

        // Already paid April's Tuition Fee before onboarding.
        $result1 = $definition->executeWrite([
            'admission_no' => 'ADM-OB-001', 'fee_head' => 'Tuition Fee', 'period' => 'April',
            'amount_paid' => 2000, 'payment_date' => '2026-04-05', 'academic_year' => null, 'remarks' => 'Cash register',
        ], $session, 'skip');

        $this->assertEquals('created', $result1['status']);

        // Already paid the full Annual Charges before onboarding.
        $result2 = $definition->executeWrite([
            'admission_no' => 'ADM-OB-001', 'fee_head' => 'Annual Charges', 'period' => 'Annual',
            'amount_paid' => 6000, 'payment_date' => '2026-04-10', 'academic_year' => null, 'remarks' => null,
        ], $session, 'skip');

        $this->assertEquals('created', $result2['status']);

        $aprilTuitionDebit = StudentFeeLedger::where('student_id', $student->id)
            ->where('reference_type', 'fee_structure_item')
            ->where('description', 'like', '%Tuition Fee - April')
            ->first();
        $this->assertEquals(0.00, (float) $aprilTuitionDebit->unpaid_amount, 'April Tuition Fee must be marked fully paid.');

        $mayTuitionDebit = StudentFeeLedger::where('student_id', $student->id)
            ->where('reference_type', 'fee_structure_item')
            ->where('description', 'like', '%Tuition Fee - May')
            ->first();
        $this->assertEquals(2000.00, (float) $mayTuitionDebit->unpaid_amount, 'May Tuition Fee must be untouched -- only April was marked paid.');

        $annualDebit = StudentFeeLedger::where('student_id', $student->id)
            ->where('reference_type', 'fee_structure_item')
            ->where('description', 'like', '%Annual Charges - Annual')
            ->first();
        $this->assertEquals(0.00, (float) $annualDebit->unpaid_amount, 'Annual Charges must be marked fully paid.');

        $this->assertEquals(
            8000.00,
            (float) StudentFeeLedger::where('student_id', $student->id)
                ->where('reference_type', 'opening_balance')
                ->sum('credit'),
            'Both opening-balance credits together must total 8000.'
        );
    }

    /** @test */
    public function it_caps_an_overpaid_amount_to_the_remaining_due_and_reports_it()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-uuid-2', 'module' => 'fee_opening_balance', 'status' => 'processing']);

        $result = (new FeeOpeningBalanceImportDefinition())->executeWrite([
            'admission_no' => 'ADM-OB-001', 'fee_head' => 'Tuition Fee', 'period' => 'April',
            'amount_paid' => 5000, 'payment_date' => '2026-04-05', 'academic_year' => null, 'remarks' => null,
        ], $session, 'skip');

        $this->assertEquals('created', $result['status']);
        $this->assertStringContainsString('capped', $result['message']);

        $this->assertEquals(
            2000.00,
            (float) StudentFeeLedger::where('student_id', $student->id)->where('reference_type', 'opening_balance')->sum('credit'),
            'Credit must be capped at the 2000 actually due for April, not the 5000 entered.'
        );
    }

    /** @test */
    public function it_rejects_a_row_with_no_matching_billed_period()
    {
        $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-uuid-3', 'module' => 'fee_opening_balance', 'status' => 'processing']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/No .*charge found/');

        (new FeeOpeningBalanceImportDefinition())->executeWrite([
            'admission_no' => 'ADM-OB-001', 'fee_head' => 'Tuition Fee', 'period' => 'Q2',
            'amount_paid' => 2000, 'payment_date' => '2026-04-05', 'academic_year' => null, 'remarks' => null,
        ], $session, 'skip');
    }

    /** @test */
    public function rollback_restores_unpaid_amount_and_running_balance()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-uuid-4', 'module' => 'fee_opening_balance', 'status' => 'processing']);

        $before = LedgerService::getOutstandingBalance($student->id);

        (new FeeOpeningBalanceImportDefinition())->executeWrite([
            'admission_no' => 'ADM-OB-001', 'fee_head' => 'Tuition Fee', 'period' => 'April',
            'amount_paid' => 2000, 'payment_date' => '2026-04-05', 'academic_year' => null, 'remarks' => null,
        ], $session, 'skip');

        $afterCredit = LedgerService::getOutstandingBalance($student->id);
        $this->assertEquals($before - 2000, $afterCredit);

        (new FeeOpeningBalanceImportDefinition())->executeRollback($session->fresh());

        $afterRollback = LedgerService::getOutstandingBalance($student->id);
        $this->assertEquals($before, $afterRollback, 'Outstanding balance must return to its pre-import value after rollback.');

        $aprilTuitionDebit = StudentFeeLedger::where('student_id', $student->id)
            ->where('reference_type', 'fee_structure_item')
            ->where('description', 'like', '%Tuition Fee - April')
            ->first();
        $this->assertEquals(2000.00, (float) $aprilTuitionDebit->unpaid_amount, 'April Tuition Fee must be unpaid again after rollback.');

        $this->assertEquals(
            0,
            StudentFeeLedger::where('student_id', $student->id)->where('reference_type', 'opening_balance')->count(),
            'Rollback must remove the opening-balance credit entries.'
        );
    }

    /** @test */
    public function admin_can_reach_the_wizard_and_download_the_template_over_http()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($admin)
            ->get(route('imports.wizard', ['module' => 'fee_opening_balance']))
            ->assertOk();

        $response = $this->actingAs($admin)
            ->get(route('imports.download-template', ['module' => 'fee_opening_balance']));

        $response->assertOk();
        $content = str_replace("\xEF\xBB\xBF", '', $response->streamedContent());
        $this->assertStringContainsString('Admission No', $content);
    }
}
