<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeRefund;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: RefundService::issueRefund() was fully built and
 * tested, but had zero callers from any controller or route -- there was
 * no reachable way to issue a partial refund from the UI, only a full
 * collection reversal. This proves the real route now works end to end,
 * including the two guard rails (no overpayment to refund, refund amount
 * exceeding the overpaid balance).
 */
class IssueRefundEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccountant(): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant->roles()->attach($role->id);
        return $accountant;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Refund Test Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887799', 'address' => 'Somewhere',
        ]);
    }

    public function test_accountant_can_issue_a_partial_refund_for_an_overpaid_student()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        LedgerService::postDebit($student->id, today()->toDateString(), 'Tuition', 'fee_structure_item', 1, 1000);
        LedgerService::postCredit($student->id, today()->toDateString(), 'Payment', 'fee_collection', 1, 1500);

        $this->assertEquals(-500, LedgerService::getOutstandingBalance($student->id));

        $response = $this->actingAs($accountant)->post(route('admin.finance.reconciliation.issue-refund'), [
            'student_id' => $student->id,
            'amount' => 500,
            'payment_mode' => 'cash',
            'reason' => 'Excess fee refund',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('fee_refunds', [
            'student_id' => $student->id, 'amount' => 500, 'type' => 'refund', 'payment_mode' => 'cash',
        ]);

        // Refund posts a debit, bringing the balance back toward zero.
        $this->assertEquals(0, LedgerService::getOutstandingBalance($student->id));
    }

    public function test_refund_is_rejected_when_student_has_no_overpaid_balance()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        LedgerService::postDebit($student->id, today()->toDateString(), 'Tuition', 'fee_structure_item', 1, 1000);

        $response = $this->actingAs($accountant)->post(route('admin.finance.reconciliation.issue-refund'), [
            'student_id' => $student->id,
            'amount' => 100,
            'payment_mode' => 'cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, FeeRefund::where('student_id', $student->id)->count());
    }

    public function test_refund_amount_cannot_exceed_the_overpaid_balance()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        LedgerService::postCredit($student->id, today()->toDateString(), 'Payment', 'fee_collection', 1, 500);
        $this->assertEquals(-500, LedgerService::getOutstandingBalance($student->id));

        $response = $this->actingAs($accountant)->post(route('admin.finance.reconciliation.issue-refund'), [
            'student_id' => $student->id,
            'amount' => 1000,
            'payment_mode' => 'cash',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, FeeRefund::where('student_id', $student->id)->count());
    }
}
