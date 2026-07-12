<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeRefund;
use App\Models\Role;
use App\Models\SecurityDeposit;
use App\Models\Student;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SecurityDepositController::resolve() is the one manual approval step
 * every held deposit must pass through -- proves the route works end to
 * end, including the clearance-check guard rail on refund, the ledger
 * credit on adjust, and that an already-resolved row can't be re-resolved.
 */
class SecurityDepositResolveTest extends TestCase
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
            'name' => 'Deposit Resolve Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);
    }

    public function test_refund_succeeds_when_outstanding_balance_is_cleared()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        $deposit = SecurityDeposit::create([
            'student_id' => $student->id, 'amount' => 5000.00, 'status' => 'refund_pending', 'refund_amount' => 5000.00,
        ]);

        $response = $this->actingAs($accountant)->post(route('admin.security-deposits.resolve', $deposit->id), [
            'action' => 'refund',
            'payment_mode' => 'cash',
            'refund_ref' => 'CASH-0001',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $deposit->refresh();
        $this->assertEquals('refunded', $deposit->status);
        $this->assertEquals($accountant->id, $deposit->approved_by);
        $this->assertNotNull($deposit->approved_at);

        $this->assertDatabaseHas('fee_refunds', [
            'student_id' => $student->id, 'amount' => 5000.00, 'type' => 'deposit_refund', 'payment_mode' => 'cash',
        ]);

        // Refund never touches the tuition ledger.
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));
    }

    public function test_refund_is_rejected_when_outstanding_dues_remain()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        $deposit = SecurityDeposit::create([
            'student_id' => $student->id, 'amount' => 5000.00, 'status' => 'refund_pending', 'refund_amount' => 3000.00,
        ]);

        LedgerService::postDebit($student->id, now()->toDateString(), 'Still owed', 'fee_structure_item', 1, 2000.00);

        $response = $this->actingAs($accountant)->post(route('admin.security-deposits.resolve', $deposit->id), [
            'action' => 'refund',
            'payment_mode' => 'cash',
            'refund_ref' => 'CASH-0002',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $deposit->refresh();
        $this->assertEquals('refund_pending', $deposit->status);
        $this->assertEquals(0, FeeRefund::where('student_id', $student->id)->count());
    }

    public function test_adjust_posts_a_ledger_credit_for_the_deducted_portion()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        // Deposit was 5000, dues of 2000 were already netted at withdrawal
        // time leaving refund_amount = 3000 -- the deducted 2000 is what
        // "adjust" should credit back against the ledger.
        $deposit = SecurityDeposit::create([
            'student_id' => $student->id, 'amount' => 5000.00, 'status' => 'refund_pending', 'refund_amount' => 3000.00,
        ]);
        LedgerService::postDebit($student->id, now()->toDateString(), 'Still owed', 'fee_structure_item', 1, 2000.00);

        $this->assertEquals(2000.00, LedgerService::getOutstandingBalance($student->id));

        $response = $this->actingAs($accountant)->post(route('admin.security-deposits.resolve', $deposit->id), [
            'action' => 'adjust',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $deposit->refresh();
        $this->assertEquals('adjusted', $deposit->status);
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));

        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $student->id, 'reference_type' => 'security_deposit', 'reference_id' => $deposit->id, 'credit' => 2000.00,
        ]);
    }

    public function test_already_resolved_deposit_cannot_be_resolved_again()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        $deposit = SecurityDeposit::create([
            'student_id' => $student->id, 'amount' => 1000.00, 'status' => 'refunded', 'refund_amount' => 1000.00,
        ]);

        $response = $this->actingAs($accountant)->post(route('admin.security-deposits.resolve', $deposit->id), [
            'action' => 'refund',
            'payment_mode' => 'cash',
            'refund_ref' => 'CASH-0003',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(0, FeeRefund::where('student_id', $student->id)->where('type', 'deposit_refund')->count());
    }
}
