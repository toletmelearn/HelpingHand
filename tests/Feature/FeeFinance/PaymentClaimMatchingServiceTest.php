<?php

namespace Tests\Feature\FeeFinance;

use App\Models\BankStatementRow;
use App\Models\FeeStructure;
use App\Models\ImportSession;
use App\Models\ParentModel;
use App\Models\PaymentClaim;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\PaymentClaimMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentClaimMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(string $admissionNo = 'ADM-2026-8001'): Student
    {
        $student = Student::create([
            'name' => 'Matching Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => $admissionNo,
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        // allocateOnlinePayment() resolves fee_structure_id (NOT NULL on
        // fee_collections) from the student's latest assignment when not
        // explicitly provided.
        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027']);

        return $student;
    }

    private function makeSession(): ImportSession
    {
        return ImportSession::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'module' => 'bank_statement',
            'status' => 'completed',
        ]);
    }

    public function test_exact_utr_and_amount_match_auto_confirms_and_credits_ledger()
    {
        User::factory()->create(); // collected_by fallback resolves to the first user
        $student = $this->makeStudent();
        // Student::booted() auto-creates and links a ParentModel on
        // creation (students.parent_id) -- no need to create one manually.
        $parent = $student->fresh()->parent;

        LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, 3000.00);

        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-EXACT-1',
            'utr' => '111122223333',
            'amount' => 3000.00,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        $session = $this->makeSession();
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 3000.00,
            'utr' => '111122223333',
            'narration' => 'NEFT CR 111122223333',
            'status' => 'unmatched',
        ]);

        $stats = PaymentClaimMatchingService::run($session->id);

        $this->assertEquals(1, $stats['exact']);

        $claim->refresh();
        $row->refresh();

        $this->assertEquals('matched', $claim->status);
        $this->assertEquals('exact', $claim->match_confidence);
        $this->assertNotNull($claim->fee_collection_id);
        $this->assertEquals('matched', $row->status);
        $this->assertEquals($claim->id, $row->payment_claim_id);

        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));
        $this->assertDatabaseHas('fee_collections', ['student_id' => $student->id, 'final_amount' => 3000.00, 'payment_mode' => 'upi']);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $parent->id, 'notifiable_type' => ParentModel::class]);
    }

    public function test_narration_match_is_suggested_not_auto_confirmed()
    {
        $student = $this->makeStudent('ADM-2026-8002');

        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-NARR-1',
            'utr' => null,
            'amount' => 2500.00,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        $session = $this->makeSession();
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 2500.00,
            'utr' => null,
            'narration' => 'UPI/CR/9988/PC-NARR-1/School Fee',
            'status' => 'unmatched',
        ]);

        $stats = PaymentClaimMatchingService::run($session->id);

        $this->assertEquals(1, $stats['narration']);
        $this->assertEquals(0, $stats['exact']);

        $claim->refresh();
        $row->refresh();

        // Still claimed -- not auto-confirmed, no receipt, no ledger touch.
        $this->assertEquals('claimed', $claim->status);
        $this->assertEquals('narration', $claim->match_confidence);
        $this->assertEquals($row->id, $claim->bank_statement_row_id);
        $this->assertNull($claim->fee_collection_id);
        $this->assertEquals('suggested', $row->status);
        $this->assertDatabaseMissing('fee_collections', ['student_id' => $student->id]);
    }

    public function test_fuzzy_amount_and_date_match_is_suggested()
    {
        $student = $this->makeStudent('ADM-2026-8003');

        $claim = PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-FUZZY-1',
            'utr' => null,
            'amount' => 4200.00,
            'status' => 'claimed',
            'submitted_at' => now()->subDay(),
        ]);

        $session = $this->makeSession();
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 4200.00,
            'utr' => null,
            'narration' => 'Unrelated narration text',
            'status' => 'unmatched',
        ]);

        $stats = PaymentClaimMatchingService::run($session->id);

        $this->assertEquals(1, $stats['fuzzy']);

        $claim->refresh();
        $this->assertEquals('claimed', $claim->status);
        $this->assertEquals('fuzzy', $claim->match_confidence);
        $this->assertEquals('suggested', $row->fresh()->status);
    }

    public function test_no_candidate_leaves_row_unmatched()
    {
        $session = $this->makeSession();
        $row = BankStatementRow::create([
            'import_session_id' => $session->id,
            'transaction_date' => now()->toDateString(),
            'amount' => 9999.00,
            'utr' => '000000000000',
            'narration' => 'No matching claim for this',
            'status' => 'unmatched',
        ]);

        $stats = PaymentClaimMatchingService::run($session->id);

        $this->assertEquals(1, $stats['unmatched']);
        $this->assertEquals('unmatched', $row->fresh()->status);
    }
}
