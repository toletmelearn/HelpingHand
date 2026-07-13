<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdminConfiguration;
use App\Models\PaymentClaim;
use App\Models\ParentModel;
use App\Models\Student;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The parent-facing half of the UPI flow: generating a QR with a live
 * outstanding balance, and submitting a UTR afterward. Nothing here ever
 * touches the ledger -- a claim is just "the parent says they paid,"
 * unresolved until the matching engine or an accountant confirms it.
 */
class PaymentClaimSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithDues(float $amount = 5000.00): Student
    {
        $student = Student::create([
            'name' => 'UPI Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-2026-9001',
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, $amount);

        return $student;
    }

    private function makeParent(Student $student): ParentModel
    {
        return ParentModel::create([
            'name' => 'UPI Parent',
            'email' => 'upiparent' . $student->id . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);
    }

    public function test_generate_qr_returns_error_when_no_vpa_configured()
    {
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr'));

        $response->assertStatus(422);
        $response->assertJson(['status' => false]);
    }

    public function test_generate_qr_succeeds_once_vpa_is_configured()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');

        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr'));

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'amount' => 5000]);
        $response->assertJsonStructure(['qr_code', 'upi_uri', 'reference_token']);
    }

    public function test_submitting_a_claim_creates_it_with_claimed_status()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-claim'), [
            'reference_token' => 'PC-TEST-0001',
            'utr' => '123456789012',
            'amount' => 5000.00,
        ]);

        $response->assertRedirect(route('parent.payments.pay-fees'));
        $this->assertDatabaseHas('payment_claims', [
            'student_id' => $student->id,
            'utr' => '123456789012',
            'status' => 'claimed',
        ]);
    }

    public function test_duplicate_utr_is_rejected()
    {
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        PaymentClaim::create([
            'student_id' => $student->id,
            'reference_token' => 'PC-EXISTING-0001',
            'utr' => '999999999999',
            'amount' => 1000.00,
            'status' => 'claimed',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-claim'), [
            'reference_token' => 'PC-TEST-0002',
            'utr' => '999999999999',
            'amount' => 5000.00,
        ]);

        $response->assertSessionHasErrors('utr');
        $this->assertEquals(1, PaymentClaim::where('utr', '999999999999')->count());
    }

    public function test_utr_must_be_exactly_12_digits()
    {
        $student = $this->makeStudentWithDues();
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-claim'), [
            'reference_token' => 'PC-TEST-0003',
            'utr' => 'abc123',
            'amount' => 5000.00,
        ]);

        $response->assertSessionHasErrors('utr');
    }
}
