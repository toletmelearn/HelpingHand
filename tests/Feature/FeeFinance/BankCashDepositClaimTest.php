<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeStructure;
use App\Models\PaymentClaim;
use App\Models\ParentModel;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The parent-facing bank-cash-deposit claim path -- the third payment
 * option alongside counter cash and UPI. No UTR; the slip photo is the
 * only evidence, so it's required (unlike UPI's optional screenshot).
 */
class BankCashDepositClaimTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithDues(float $amount = 1500.00): Student
    {
        $student = Student::create([
            'name' => 'Cash Deposit Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-2026-9101',
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027']);

        \App\Services\LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, $amount);

        return $student;
    }

    private function makeParent(Student $student): ParentModel
    {
        return ParentModel::create([
            'name' => 'Cash Deposit Parent',
            'email' => 'cashdeposit' . $student->id . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);
    }

    public function test_submitting_a_cash_deposit_claim_creates_it_with_claimed_status()
    {
        Storage::fake('public');
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $slip = UploadedFile::fake()->image('slip.jpg');

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-cash-deposit-claim'), [
            'deposit_date' => now()->toDateString(),
            'branch' => 'MG Road Branch',
            'amount' => 1500.00,
            'slip' => $slip,
        ]);

        $response->assertRedirect(route('parent.payments.pay-fees'));

        $claim = PaymentClaim::where('student_id', $student->id)->first();
        $this->assertNotNull($claim);
        $this->assertEquals('bank_cash_deposit', $claim->claim_type);
        $this->assertEquals('claimed', $claim->status);
        $this->assertEquals('MG Road Branch', $claim->branch);
        $this->assertNull($claim->utr);
        $this->assertNotNull($claim->screenshot_path);
        Storage::disk('public')->assertExists($claim->screenshot_path);
    }

    public function test_slip_photo_is_required()
    {
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-cash-deposit-claim'), [
            'deposit_date' => now()->toDateString(),
            'branch' => 'MG Road Branch',
            'amount' => 1500.00,
        ]);

        $response->assertSessionHasErrors('slip');
        $this->assertDatabaseMissing('payment_claims', ['student_id' => $student->id]);
    }

    public function test_branch_and_deposit_date_are_required()
    {
        Storage::fake('public');
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-cash-deposit-claim'), [
            'amount' => 1500.00,
            'slip' => UploadedFile::fake()->image('slip.jpg'),
        ]);

        $response->assertSessionHasErrors(['deposit_date', 'branch']);
    }
}
