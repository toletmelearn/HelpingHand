<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdminConfiguration;
use App\Models\ParentModel;
use App\Models\Student;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The parent UPI QR flow was previously hardcoded to the full outstanding
 * balance -- this covers the new editable partial-amount support plus the
 * per-school minimum-payment-amount setting.
 */
class ParentUpiPartialPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudentWithDues(float $amount = 5000.00): Student
    {
        $student = Student::create([
            'name' => 'Partial Pay Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-2026-9201',
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        LedgerService::postDebit($student->id, now()->toDateString(), 'Tuition', 'fee_structure_item', 1, $amount);

        return $student;
    }

    private function makeParent(Student $student): ParentModel
    {
        return ParentModel::create([
            'name' => 'Partial Pay Parent',
            'email' => 'partialpay' . $student->id . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);
    }

    public function test_qr_defaults_to_full_outstanding_balance()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr'));

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'amount' => 5000]);
    }

    public function test_qr_accepts_a_valid_partial_amount()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr', ['amount' => 2000]));

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'amount' => 2000]);
    }

    public function test_qr_rejects_an_amount_over_the_balance()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr', ['amount' => 6000]));

        $response->assertStatus(422);
        $response->assertJson(['status' => false]);
    }

    public function test_qr_rejects_a_partial_amount_below_the_configured_minimum()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        AdminConfiguration::set('fee', 'minimum_payment_amount', '1000', 'string', 'Minimum Payment');
        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr', ['amount' => 500]));

        $response->assertStatus(422);
        $response->assertJson(['status' => false]);
    }

    public function test_minimum_does_not_block_clearing_the_full_remaining_balance()
    {
        AdminConfiguration::set('fee', 'upi_vpa', 'school@upi', 'string', 'School UPI VPA');
        AdminConfiguration::set('fee', 'minimum_payment_amount', '1000', 'string', 'Minimum Payment');
        // Balance itself is below the configured minimum -- must still be payable in full.
        $student = $this->makeStudentWithDues(500.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.payments.upi-qr', ['amount' => 500]));

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'amount' => 500]);
    }

    public function test_submit_claim_rejects_partial_amount_below_minimum()
    {
        AdminConfiguration::set('fee', 'minimum_payment_amount', '1000', 'string', 'Minimum Payment');
        $student = $this->makeStudentWithDues(5000.00);
        $parent = $this->makeParent($student);

        $response = $this->actingAs($parent, 'parent')->post(route('parent.payments.submit-claim'), [
            'reference_token' => 'PC-PARTIAL-1',
            'utr' => '555566667777',
            'amount' => 300.00,
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseMissing('payment_claims', ['utr' => '555566667777']);
    }
}
