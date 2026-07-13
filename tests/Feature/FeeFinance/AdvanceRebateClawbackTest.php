<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdvanceRebateRule;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Models\StudentFeeAssignment;
use App\Models\StudentFeeLedger;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\StructureAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors TcIssuancePrunesFutureDuesTest / SecurityDepositWithdrawalTest's
 * fixture pattern: full-session tuition paid + rebate applied, then
 * mid-session withdrawal -- covers the clawback amount, deposit-deduction
 * order, and shortfall-becomes-new-due behavior.
 */
class AdvanceRebateClawbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithAppliedRebate(float $depositAmount = null): array
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);

        $student = Student::create([
            'name' => 'Clawback Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'monthly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027']);
        $item = FeeStructureItem::create(['fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id, 'amount' => 1000.00]);

        // 12 monthly debits of 1000 each = 12,000 for the full session,
        // 6 already past (Apr-Sep), 6 still future-dated (Oct-Mar) as of
        // the 2026-09-30 withdrawal date used in these tests. All share the
        // same FeeStructureItem id -- LedgerService resolves academic_year/
        // fee_type_id by looking that row up, which AdvanceRebateService's
        // query depends on.
        $months = [
            '2026-04-01', '2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01', '2026-09-01',
            '2026-10-01', '2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01', '2027-03-01',
        ];
        foreach ($months as $i => $date) {
            LedgerService::postDebit($student->id, $date, "Tuition - Month $i", 'fee_structure_item', $item->id, 1000.00);
        }

        // Fully paid (unpaid_amount = 0) as of today, matching the
        // "full session paid before cutoff" precondition.
        StudentFeeLedger::where('student_id', $student->id)->update(['unpaid_amount' => 0]);

        $rule = AdvanceRebateRule::create([
            'name' => '10% Advance Rebate',
            'type' => 'percent',
            'value' => 10,
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-CLAWBACK-' . $student->id,
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 12000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 12000.00,
            'payment_date' => '2026-04-10',
            'payment_mode' => 'cash',
            'collected_by' => User::factory()->create()->id,
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->firstOrFail();
        $this->assertEquals(1200.00, $snapshot->rebate_amount); // 10% of 12,000

        if ($depositAmount !== null) {
            SecurityDeposit::create([
                'student_id' => $student->id,
                'amount' => $depositAmount,
                'status' => 'held',
            ]);
        }

        return [$student, $snapshot];
    }

    public function test_mid_session_withdrawal_computes_pro_rated_clawback_and_covers_it_from_deposit()
    {
        [$student, $snapshot] = $this->makeStudentWithAppliedRebate(2000.00);

        (new StructureAdjustmentService())->withdrawStudent($student, '2026-09-30');

        $snapshot->refresh();
        $this->assertEquals('clawed_back', $snapshot->status);
        // 6 of 12 months (Oct-Mar) unbilled -- half the rebate is clawed back: 600.
        $this->assertEquals(600.00, $snapshot->clawback_amount);
        $this->assertEquals(0.00, $snapshot->clawback_shortfall_amount);

        $deposit = SecurityDeposit::where('student_id', $student->id)->first();
        $this->assertEquals('refund_pending', $deposit->status);
        // 2000 deposit - 600 clawback (no other outstanding dues) = 1400 refundable.
        $this->assertEquals(1400.00, $deposit->refund_amount);

        // No new due posted -- the deposit fully covered the clawback.
        $this->assertDatabaseMissing('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_type' => 'advance_rebate_clawback',
        ]);
    }

    public function test_clawback_deducts_after_outstanding_dues_not_before()
    {
        [$student, $snapshot] = $this->makeStudentWithAppliedRebate(2000.00);

        // getOutstandingBalance() sums debit - credit, not unpaid_amount --
        // the fixture's automatic credits (full-session payment + rebate)
        // leave the balance well below zero, so top it up by exactly
        // enough that, AFTER withdrawStudent() prunes the 6 still-future
        // months (Oct-Mar, 6000 total), the balance lands on a real, known
        // 500 outstanding due, competing for the same deposit pool ahead
        // of the clawback.
        $futureDebitsToBePruned = 6 * 1000.00;
        $balanceAfterPruning = LedgerService::getOutstandingBalance($student->id) - $futureDebitsToBePruned;
        $topUpNeeded = 500.00 - $balanceAfterPruning;
        LedgerService::postDebit($student->id, '2026-09-01', 'Late Fine', 'fee_collection', 999, $topUpNeeded);

        (new StructureAdjustmentService())->withdrawStudent($student, '2026-09-30');

        $snapshot->refresh();
        // Clawback amount itself is unaffected by unrelated outstanding dues.
        $this->assertEquals(600.00, $snapshot->clawback_amount);

        $deposit = SecurityDeposit::where('student_id', $student->id)->first();
        // 2000 deposit - 500 (dues, deducted FIRST) - 600 (clawback, deducted SECOND) = 900.
        $this->assertEquals(900.00, $deposit->refund_amount);
    }

    public function test_clawback_shortfall_beyond_the_deposit_becomes_a_new_due()
    {
        [$student, $snapshot] = $this->makeStudentWithAppliedRebate(300.00); // less than the 600 clawback

        (new StructureAdjustmentService())->withdrawStudent($student, '2026-09-30');

        $snapshot->refresh();
        $this->assertEquals('clawed_back', $snapshot->status);
        $this->assertEquals(600.00, $snapshot->clawback_amount);
        $this->assertEquals(300.00, $snapshot->clawback_shortfall_amount);

        $deposit = SecurityDeposit::where('student_id', $student->id)->first();
        $this->assertEquals(0.00, $deposit->refund_amount);

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $student->id,
            'reference_type' => 'advance_rebate_clawback',
            'reference_id' => $snapshot->id,
            'debit' => 300.00,
        ]);
    }

    public function test_no_clawback_when_withdrawal_is_at_the_very_end_of_the_session()
    {
        [$student, $snapshot] = $this->makeStudentWithAppliedRebate(2000.00);

        // Withdraw after the last billed month -- nothing left unbilled.
        (new StructureAdjustmentService())->withdrawStudent($student, '2027-03-31');

        $snapshot->refresh();
        $this->assertEquals('applied', $snapshot->status); // untouched
        $this->assertNull($snapshot->clawback_amount);

        $deposit = SecurityDeposit::where('student_id', $student->id)->first();
        $this->assertEquals(2000.00, $deposit->refund_amount);
    }
}
