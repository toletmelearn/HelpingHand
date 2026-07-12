<?php

namespace Tests\Feature\FeeFinance;

use App\Models\SchoolClass;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Services\LedgerService;
use App\Services\StructureAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * StructureAdjustmentService::withdrawStudent() is the single choke point
 * both markAsPassedOut() and CertificateController::publish() (TC) funnel
 * through -- these test the deposit-refund-pending calculation it now
 * performs directly, rather than through both HTTP call sites redundantly
 * (those are covered by PassedOutPrunesFutureDuesTest/TcIssuancePrunesFutureDuesTest
 * for the pre-existing dues-pruning behavior).
 */
class SecurityDepositWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeStudent(string $name): Student
    {
        $class = SchoolClass::create(['name' => 'Class ' . random_int(1, 12), 'class_order' => random_int(1, 12), 'is_active' => true]);

        return Student::create([
            'name' => $name, 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);
    }

    public function test_withdrawal_with_no_outstanding_dues_refunds_the_full_deposit()
    {
        $student = $this->makeStudent('No Dues Kid');
        $deposit = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 5000.00, 'status' => 'held']);

        (new StructureAdjustmentService())->withdrawStudent($student, now()->toDateString());

        $deposit->refresh();
        $this->assertEquals('refund_pending', $deposit->status);
        $this->assertEquals(5000.00, $deposit->refund_amount);
    }

    public function test_withdrawal_with_partial_outstanding_dues_reduces_the_refund_amount()
    {
        $student = $this->makeStudent('Partial Dues Kid');
        $deposit = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 5000.00, 'status' => 'held']);

        LedgerService::postDebit($student->id, now()->subMonth()->toDateString(), 'Already-due tuition', 'fee_structure_item', 1, 2000.00);

        (new StructureAdjustmentService())->withdrawStudent($student, now()->toDateString());

        $deposit->refresh();
        $this->assertEquals('refund_pending', $deposit->status);
        $this->assertEquals(3000.00, $deposit->refund_amount);
    }

    public function test_withdrawal_with_dues_exceeding_deposit_floors_refund_amount_at_zero()
    {
        $student = $this->makeStudent('Heavy Dues Kid');
        $deposit = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 1000.00, 'status' => 'held']);

        LedgerService::postDebit($student->id, now()->subMonth()->toDateString(), 'Already-due tuition', 'fee_structure_item', 1, 4000.00);

        (new StructureAdjustmentService())->withdrawStudent($student, now()->toDateString());

        $deposit->refresh();
        $this->assertEquals('refund_pending', $deposit->status);
        $this->assertEquals(0.00, $deposit->refund_amount);
    }

    public function test_dues_are_netted_across_multiple_held_deposits_not_double_subtracted()
    {
        $student = $this->makeStudent('Two Deposits Kid');
        $depositA = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 2000.00, 'status' => 'held']);
        $depositB = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 3000.00, 'status' => 'held']);

        // 2500 in dues should consume all of deposit A (2000) plus 500 of
        // deposit B, leaving deposit B refundable for 2500 -- not each
        // deposit independently reduced by the full 2500.
        LedgerService::postDebit($student->id, now()->subMonth()->toDateString(), 'Already-due tuition', 'fee_structure_item', 1, 2500.00);

        (new StructureAdjustmentService())->withdrawStudent($student, now()->toDateString());

        $depositA->refresh();
        $depositB->refresh();
        $this->assertEquals(0.00, $depositA->refund_amount);
        $this->assertEquals(2500.00, $depositB->refund_amount);
    }

    public function test_withdrawal_only_moves_held_deposits_not_already_resolved_ones()
    {
        $student = $this->makeStudent('Already Refunded Kid');
        $refunded = SecurityDeposit::create(['student_id' => $student->id, 'amount' => 1000.00, 'status' => 'refunded', 'refund_amount' => 1000.00]);

        (new StructureAdjustmentService())->withdrawStudent($student, now()->toDateString());

        $refunded->refresh();
        $this->assertEquals('refunded', $refunded->status);
    }
}
