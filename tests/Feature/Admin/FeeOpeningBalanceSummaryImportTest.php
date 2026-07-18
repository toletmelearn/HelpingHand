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
use App\Services\Imports\FeeOpeningBalanceSummaryImportDefinition;
use App\Services\Imports\ImportEngine;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers importing a real historical fee register (one row per student,
 * a single lump total-paid figure, no fee-head/period breakdown) as
 * opening balances -- distinct from FeeOpeningBalanceImportTest, which
 * covers the narrower "one row per already-paid fee-head+period" shape.
 */
class FeeOpeningBalanceSummaryImportTest extends TestCase
{
    use RefreshDatabase;

    private function seedStudentWithLiveDebits(string $admissionNo = 'ADM-SUM-001'): Student
    {
        SchoolClass::firstOrCreate(['name' => 'Class 6'], ['class_order' => 6, 'is_active' => true]);

        $tuition = FeeType::firstOrCreate(['name' => 'Tuition Fee'], ['status' => 'active']);
        $annual = FeeType::firstOrCreate(['name' => 'Annual Charges'], ['status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Class 6', 'academic_year' => '2025-2026',
            'frequency' => 'custom', 'status' => 'active',
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 2000, 'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'June'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $annual->id,
            'amount' => 6000, 'billing_frequency' => 'session_wise_continuing', 'charge_months' => ['Annual'],
        ]);

        $student = Student::create([
            'name' => 'Summary Import Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887771', 'address' => 'Somewhere', 'admission_no' => $admissionNo,
        ]);

        BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        return $student;
    }

    /** @test */
    public function total_paid_is_auto_allocated_across_existing_debits_without_a_fee_head_breakdown()
    {
        $student = $this->seedStudentWithLiveDebits();
        // Total billed: 2000(Apr) + 2000(May) + 2000(Jun) + 6000(Annual) = 12000.
        $session = ImportSession::create(['uuid' => 'test-summary-1', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $result = (new FeeOpeningBalanceSummaryImportDefinition())->executeWrite([
            'admission_no' => $student->admission_no,
            'total_paid' => 4000, // enough to clear the two smallest/oldest debits
            'prior_year_pending' => null,
        ], $session, 'skip');

        $this->assertEquals('created', $result['status']);

        $totalCredited = StudentFeeLedger::where('student_id', $student->id)
            ->where('reference_type', 'opening_balance')
            ->sum('credit');
        $this->assertEquals(4000.00, (float) $totalCredited);

        $remainingOutstanding = LedgerService::getOutstandingBalance($student->id);
        $this->assertEquals(8000.00, $remainingOutstanding, '12000 billed - 4000 paid = 8000 still outstanding.');
    }

    /** @test */
    public function prior_year_pending_amount_is_posted_as_a_new_debit_and_included_in_the_allocation()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-summary-2', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $result = (new FeeOpeningBalanceSummaryImportDefinition())->executeWrite([
            'admission_no' => $student->admission_no,
            'total_paid' => 3000,
            'prior_year_pending' => 5000,
        ], $session, 'skip');

        $this->assertEquals('created', $result['status']);

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_type' => 'opening_balance_prior_year',
            'debit' => 5000.00,
        ]);

        // Billed: 12000 (current session) + 5000 (prior year pending) = 17000. Paid: 3000.
        $this->assertEquals(14000.00, LedgerService::getOutstandingBalance($student->id));
    }

    /** @test */
    public function a_row_with_zero_paid_but_a_pending_amount_still_records_the_pending_debit()
    {
        // Regression case found in the real file (e.g. "AL RAMAAN": paid=0,
        // pending=318) -- total_paid must be nullable, not required, or
        // this student's pending debit would never get recorded at all.
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-summary-3', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $definition = new FeeOpeningBalanceSummaryImportDefinition();
        $rules = $definition->getValidationRules([]);
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['admission_no' => $student->admission_no, 'total_paid' => null, 'prior_year_pending' => 318],
            $rules
        );
        $this->assertTrue($validator->passes(), 'A row with paid=null but pending>0 must still pass validation.');

        $result = $definition->executeWrite([
            'admission_no' => $student->admission_no,
            'total_paid' => null,
            'prior_year_pending' => 318,
        ], $session, 'skip');

        $this->assertEquals('created', $result['status']);
        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_type' => 'opening_balance_prior_year',
            'debit' => 318.00,
        ]);
        $this->assertEquals(
            0,
            StudentFeeLedger::where('student_id', $student->id)->where('reference_type', 'opening_balance')->count(),
            'No opening_balance credit should be created when nothing was paid.'
        );
    }

    /** @test */
    public function a_row_with_neither_paid_nor_pending_is_skipped_not_errored()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-summary-4', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $result = (new FeeOpeningBalanceSummaryImportDefinition())->executeWrite([
            'admission_no' => $student->admission_no,
            'total_paid' => null,
            'prior_year_pending' => null,
        ], $session, 'skip');

        $this->assertEquals('skipped', $result['status']);
        $this->assertEquals(0, StudentFeeLedger::where('student_id', $student->id)->where('reference_type', 'like', 'opening_balance%')->count());
    }

    /** @test */
    public function an_unmatched_admission_number_throws_and_does_not_touch_other_rows()
    {
        $session = ImportSession::create(['uuid' => 'test-summary-5', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/No student found/');

        (new FeeOpeningBalanceSummaryImportDefinition())->executeWrite([
            'admission_no' => 'DOES-NOT-EXIST', 'total_paid' => 1000, 'prior_year_pending' => null,
        ], $session, 'skip');
    }

    /** @test */
    public function rollback_removes_both_the_pending_debit_and_the_opening_balance_credit()
    {
        $student = $this->seedStudentWithLiveDebits();
        $session = ImportSession::create(['uuid' => 'test-summary-6', 'module' => 'fee_opening_balance_summary', 'status' => 'processing']);

        $before = LedgerService::getOutstandingBalance($student->id);

        (new FeeOpeningBalanceSummaryImportDefinition())->executeWrite([
            'admission_no' => $student->admission_no,
            'total_paid' => 3000,
            'prior_year_pending' => 5000,
        ], $session, 'skip');

        $this->assertNotEquals($before, LedgerService::getOutstandingBalance($student->id));

        (new FeeOpeningBalanceSummaryImportDefinition())->executeRollback($session->fresh());

        $this->assertEquals($before, LedgerService::getOutstandingBalance($student->id), 'Outstanding balance must return to its pre-import value after rollback.');
        $this->assertEquals(
            0,
            StudentFeeLedger::where('student_id', $student->id)->whereIn('reference_type', ['opening_balance', 'opening_balance_prior_year'])->count(),
            'Rollback must remove both the pending debit and the opening-balance credit.'
        );
    }

    /** @test */
    public function real_wide_format_file_is_reshaped_and_imports_correctly_end_to_end()
    {
        Storage::fake('local');

        $studentA = $this->seedStudentWithLiveDebits('ADM-SUM-A');
        $studentB = $this->seedStudentWithLiveDebits('ADM-SUM-B');

        // Mirrors the real file's actual column shape and header wording
        // (only the columns transformRows() reads are populated here; the
        // real file also has Class/Section/fee-head-amount columns this
        // hook correctly ignores).
        $csv = "S.NO.,Enrl No.,Name  of Student,Class,Total Fee Amount,TOTAL PAID,Balance,PENDING AMOUNT  2025-26\n"
            . "1,ADM-SUM-A,Student A,Class 6,12000,4000,8000,\n"
            . "2,ADM-SUM-B,Student B,Class 6,12000,0,12000,318\n"
            . "3,DOES-NOT-EXIST,Ghost Student,Class 6,12000,1000,11000,\n";

        $file = UploadedFile::fake()->createWithContent('old_fee.csv', $csv);
        $engine = app(ImportEngine::class);
        $session = $engine->initializeSession('fee_opening_balance_summary', $file, 1);

        $this->assertEquals(['Admission No', 'Total Paid', 'Prior Year Pending'], $session->settings['headers']);

        $dryRun = $engine->dryRun($session->uuid, $session->column_mappings);
        $this->assertEquals(2, $dryRun['success'], 'Student A (paid only) and Student B (pending only) should validate.');
        $this->assertEquals(1, $dryRun['errors'], 'The unmatched admission number should fail validation (exists:students,admission_no).');

        $engine->execute($session->uuid, 'skip');

        $this->assertEquals(8000.00, LedgerService::getOutstandingBalance($studentA->id));
        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $studentB->id, 'reference_type' => 'opening_balance_prior_year', 'debit' => 318.00,
        ]);
    }

    /** @test */
    public function admin_can_reach_the_wizard_and_download_the_template_over_http()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $this->actingAs($admin)
            ->get(route('imports.wizard', ['module' => 'fee_opening_balance_summary']))
            ->assertOk();

        $response = $this->actingAs($admin)
            ->get(route('imports.download-template', ['module' => 'fee_opening_balance_summary']));

        $response->assertOk();
        $content = str_replace("\xEF\xBB\xBF", '', $response->streamedContent());
        $this->assertStringContainsString('Admission No', $content);
        $this->assertStringContainsString('Prior Year Pending', $content);
    }
}
