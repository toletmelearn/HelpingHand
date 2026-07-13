<?php

namespace Tests\Feature\FeeFinance;

use App\Models\BankStatementRow;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\ImportSession;
use App\Models\ParentModel;
use App\Models\PaymentClaim;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The accountant-facing half of the review queue: approving a suggested
 * match (one-click confirm), rejecting one that doesn't check out, and
 * cancelling an already-matched claim -- the "match cancellation requires
 * reason + audit log" hard rule.
 */
class PaymentClaimMatchingControllerTest extends TestCase
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
        $student = Student::create([
            'name' => 'Review Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        // allocateOnlinePayment() resolves fee_structure_id (NOT NULL on
        // fee_collections) from the student's latest assignment when not
        // explicitly provided.
        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027']);

        return $student;
    }

    private function makeSuggestedPair(Student $student, float $amount): array
    {
        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-' . Str::random(6),
            'utr' => null,
            'amount' => $amount,
            'status' => 'claimed',
            'match_confidence' => 'narration',
            'submitted_at' => now(),
        ]);

        $session = ImportSession::create(['uuid' => (string) Str::uuid(), 'module' => 'bank_statement', 'status' => 'completed']);
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => $amount,
            'narration' => 'suggested row',
            'status' => 'suggested',
            'payment_claim_id' => $claim->id,
        ]);
        $claim->update(['bank_statement_row_id' => $row->id]);

        return [$claim, $row];
    }

    public function test_accountant_can_approve_a_suggested_match()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        ParentModel::create(['name' => 'P', 'email' => 'ap1@example.com', 'password' => bcrypt('x'), 'student_id' => $student->id]);
        LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, 1500.00);
        [$claim, $row] = $this->makeSuggestedPair($student, 1500.00);

        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.approve', $claim->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $claim->refresh();
        $this->assertEquals('matched', $claim->status);
        $this->assertNotNull($claim->fee_collection_id);
        $this->assertEquals($accountant->id, $claim->resolved_by);

        $this->assertEquals('matched', $row->fresh()->status);
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));
        $this->assertDatabaseHas('fee_collections', ['student_id' => $student->id, 'final_amount' => 1500.00]);
    }

    public function test_approving_an_already_matched_row_is_rejected()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        [$claim, $row] = $this->makeSuggestedPair($student, 1000.00);
        $row->update(['status' => 'matched']);

        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.approve', $claim->id));

        $response->assertSessionHas('error');
        $this->assertEquals('claimed', $claim->fresh()->status);
    }

    public function test_reject_requires_a_reason()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        [$claim, $row] = $this->makeSuggestedPair($student, 800.00);

        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.reject', $claim->id), []);

        $response->assertSessionHasErrors('reason');
        $this->assertEquals('claimed', $claim->fresh()->status);
    }

    public function test_rejecting_a_claimed_suggestion_frees_the_row()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        [$claim, $row] = $this->makeSuggestedPair($student, 800.00);

        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.reject', $claim->id), [
            'reason' => 'Amount does not match after all.',
        ]);

        $response->assertSessionHas('success');

        $claim->refresh();
        $row->refresh();
        $this->assertEquals('rejected', $claim->status);
        $this->assertEquals('Amount does not match after all.', $claim->cancellation_reason);
        $this->assertNull($claim->bank_statement_row_id);
        $this->assertEquals('unmatched', $row->status);
        $this->assertNull($row->payment_claim_id);
    }

    public function test_cancelling_a_matched_claim_reverses_the_collection_and_requires_reason()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, 2000.00);
        $collection = LedgerService::allocateOnlinePayment($student->id, 2000.00, 'upi', ['remarks' => 'test']);

        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-CANCEL-1',
            'utr' => '444455556666',
            'amount' => 2000.00,
            'status' => 'matched',
            'match_confidence' => 'exact',
            'fee_collection_id' => $collection->id,
            'submitted_at' => now(),
            'resolved_at' => now(),
        ]);

        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));

        // No reason -> rejected
        $noReasonResponse = $this->actingAs($accountant)->post(route('admin.payment-claims.reject', $claim->id), []);
        $noReasonResponse->assertSessionHasErrors('reason');

        // With reason -> cancelled and reversed
        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.reject', $claim->id), [
            'reason' => 'Cheque bounced, reversing this UPI credit.',
        ]);
        $response->assertSessionHas('success');

        $claim->refresh();
        $this->assertEquals('cancelled', $claim->status);
        $this->assertEquals('Cheque bounced, reversing this UPI credit.', $claim->cancellation_reason);
        // fee_collection_id intentionally left in place for audit history.
        $this->assertEquals($collection->id, $claim->fee_collection_id);

        $this->assertSoftDeleted('fee_collections', ['id' => $collection->id]);
        $this->assertEquals(2000.00, LedgerService::getOutstandingBalance($student->id));

        $this->assertDatabaseHas('fee_refunds', ['student_id' => $student->id, 'fee_collection_id' => $collection->id, 'type' => 'reversal']);
    }

    /**
     * The on-demand extension point PaymentClaimMatchingService::run() was
     * designed for -- a claim submitted after a statement was already
     * imported and matched once. Confirms the accountant can re-trigger
     * matching and it correctly picks up the new claim.
     */
    public function test_accountant_can_manually_trigger_matching()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();

        $session = ImportSession::create(['uuid' => (string) Str::uuid(), 'module' => 'bank_statement', 'status' => 'completed']);
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 750.00,
            'utr' => '777788889999',
            'narration' => 'NEFT CR 777788889999',
            'status' => 'unmatched',
        ]);

        // Claim submitted after the row was already imported -- never got
        // a chance to auto-match since that only runs once, right after execute().
        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-LATE-1',
            'utr' => '777788889999',
            'amount' => 750.00,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($accountant)->post(route('admin.payment-claims.run-matching'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $claim->refresh();
        $row->refresh();
        $this->assertEquals('matched', $claim->status);
        $this->assertEquals('exact', $claim->match_confidence);
        $this->assertEquals('matched', $row->status);
        $this->assertNotNull($claim->fee_collection_id);
    }
}
