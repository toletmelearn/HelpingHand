<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fees V1 completion pass: a single realistic end-to-end walkthrough of
 * the admin fee-collection narrative, chained through the REAL routes on
 * isolated fixtures this test builds and owns. Uses the 'super-admin'
 * role (bypasses PermissionMiddleware in code, via User::isSuperAdmin())
 * rather than 'admin' + an explicit permission grant, since this codebase's
 * permission catalog is seeded by database/seeders/PermissionSeeder,
 * which RefreshDatabase does not run -- a real, pre-existing gap across
 * roughly 40 other Fee/Finance tests, documented separately, not touched
 * here.
 *
 * Every individual behavior already has narrower coverage elsewhere
 * (FeeDuplicateSubmissionGuardTest, ReceiptTotalsMathTest,
 * IssueRefundEndToEndTest, FeeStructureController's own dependency-check
 * tests, etc.) -- this suite's value is proving the full CHAIN holds:
 * create fee head -> create structure -> assign to student (auto-bills
 * the ledger) -> partial payment -> balance verification -> receipt ->
 * remaining payment -> fully paid -> history -> invalid amount rejected
 * -> duplicate submission blocked -> unauthorized user denied -> another
 * student's record stays isolated -> reversal preserves history.
 */
class FeesV1UatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['role' => 'super-admin']);
        $role = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(string $suffix): Student
    {
        return Student::create([
            'name' => "Fees UAT Student $suffix", 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'class' => 'Fees UAT Class',
        ]);
    }

    public function test_full_admin_fee_lifecycle(): void
    {
        $admin = $this->admin();

        // ------------------------------------------------------------
        // 1-2. Admin creates a fee head and a fee structure for the class.
        // ------------------------------------------------------------
        $feeType = FeeType::create(['name' => 'Fees UAT Tuition', 'status' => 'active']);
        $structure = FeeStructure::create([
            'class_name' => 'Fees UAT Class', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active', 'is_active' => true,
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $feeType->id, 'amount' => 1000,
        ]);

        // ------------------------------------------------------------
        // 3. Assign the fee to a student -- this auto-bills the ledger
        // (StudentFeeAssignment::booted()'s created hook), so the debit
        // is real, not a test fixture shortcut.
        // ------------------------------------------------------------
        $student = $this->makeStudent('Main');
        StudentFeeAssignment::create([
            'student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027',
        ]);

        // 4. Outstanding balance reflects the auto-billed charge.
        $this->assertSame(1000.0, LedgerService::getOutstandingBalance($student->id));

        // ------------------------------------------------------------
        // 5-6. Admin records a partial payment via the real route.
        // ------------------------------------------------------------
        $partial = $this->actingAs($admin)->post(route('admin.fees.process.collection'), [
            'student_id' => $student->id,
            'total_amount' => 400,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);
        $partial->assertSessionHas('success');
        $this->assertSame(600.0, LedgerService::getOutstandingBalance($student->id));

        $firstCollection = FeeCollection::where('student_id', $student->id)->firstOrFail();
        $this->assertSame(400.0, (float) $firstCollection->final_amount);
        $this->assertNotEmpty($firstCollection->receipt_no);

        // ------------------------------------------------------------
        // 6-7. Receipt reflects the actual stored payment.
        // ------------------------------------------------------------
        $receiptView = $this->actingAs($admin)->get(route('admin.fees.receipt', $firstCollection->id));
        $receiptView->assertOk();
        $receiptView->assertSee($firstCollection->receipt_no);
        $receiptView->assertSee($student->name);

        // ------------------------------------------------------------
        // 10. Invalid amount (zero) is rejected server-side.
        // ------------------------------------------------------------
        $invalid = $this->actingAs($admin)->post(route('admin.fees.process.collection'), [
            'student_id' => $student->id, 'total_amount' => 0, 'payment_mode' => 'cash',
        ]);
        $invalid->assertSessionHasErrors('total_amount');
        $this->assertSame(600.0, LedgerService::getOutstandingBalance($student->id), 'a rejected zero-amount submission must not touch the balance');

        // ------------------------------------------------------------
        // 7-8. Admin records the remaining payment -- fully paid.
        // ------------------------------------------------------------
        $final = $this->actingAs($admin)->post(route('admin.fees.process.collection'), [
            'student_id' => $student->id,
            'total_amount' => 600,
            'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(),
        ]);
        $final->assertSessionHas('success');
        $this->assertSame(0.0, LedgerService::getOutstandingBalance($student->id));

        // ------------------------------------------------------------
        // 9. Payment history shows both collections, correctly summed.
        // ------------------------------------------------------------
        $collections = FeeCollection::where('student_id', $student->id)->orderBy('id')->get();
        $this->assertCount(2, $collections);
        $this->assertSame(1000.0, (float) $collections->sum('final_amount'));

        // ------------------------------------------------------------
        // 11. Duplicate/immediate resubmission with the same token is
        // blocked (fingerprint + token locks).
        // ------------------------------------------------------------
        $otherStudent = $this->makeStudent('Dup');
        StudentFeeAssignment::create([
            'student_id' => $otherStudent->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027',
        ]);
        $dupToken = 'uat-dup-token-' . uniqid();
        $dupPayload = [
            'student_id' => $otherStudent->id, 'total_amount' => 100, 'payment_mode' => 'cash',
            'payment_date' => today()->toDateString(), 'submission_token' => $dupToken,
        ];
        $this->actingAs($admin)->post(route('admin.fees.process.collection'), $dupPayload)->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.fees.process.collection'), $dupPayload)
            ->assertSessionHas('error', 'This transaction is already being processed.');
        $this->assertSame(1, FeeCollection::where('student_id', $otherStudent->id)->count(), 'the duplicate must not create a second collection row');

        // ------------------------------------------------------------
        // 13/17. Reversal: an admin reverses the first partial payment.
        // History is preserved (soft-deleted, not destroyed), and an
        // audit trail (FeeRefund) is created.
        // ------------------------------------------------------------
        $balanceBeforeReversal = LedgerService::getOutstandingBalance($student->id);
        $reverse = $this->actingAs($admin)->post(route('admin.fees.reverse', $firstCollection->id), [
            'reason' => 'Fees UAT reversal test',
        ]);
        $reverse->assertSessionHas('success');
        $this->assertSame($balanceBeforeReversal + 400, LedgerService::getOutstandingBalance($student->id));
        $this->assertSoftDeleted('fee_collections', ['id' => $firstCollection->id]);
        $this->assertDatabaseHas('fee_refunds', ['fee_collection_id' => $firstCollection->id, 'type' => 'reversal']);
        // The reversed row is still retrievable for historical audit.
        $this->assertNotNull(FeeCollection::withTrashed()->find($firstCollection->id));

        // ------------------------------------------------------------
        // 14/17. Another student's record stays completely isolated
        // throughout -- unaffected by anything done to $student.
        // ------------------------------------------------------------
        $this->assertSame(900.0, LedgerService::getOutstandingBalance($otherStudent->id));
        $this->assertNotEquals(LedgerService::getOutstandingBalance($student->id), LedgerService::getOutstandingBalance($otherStudent->id));

        // ------------------------------------------------------------
        // 12/16. Unauthorized user cannot record a payment or view the
        // receipt for someone else's fees.
        // ------------------------------------------------------------
        $intruder = User::factory()->create();
        $this->actingAs($intruder)->post(route('admin.fees.process.collection'), [
            'student_id' => $otherStudent->id, 'total_amount' => 50, 'payment_mode' => 'cash',
        ])->assertForbidden();
        $this->actingAs($intruder)->get(route('admin.fees.receipt', $firstCollection->id))->assertForbidden();
    }

    public function test_guest_cannot_record_a_payment_or_view_a_receipt(): void
    {
        $structure = FeeStructure::create([
            'class_name' => 'Fees UAT Guest Class', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active', 'is_active' => true,
        ]);
        $student = $this->makeStudent('Guest');
        $collection = FeeCollection::create([
            'receipt_no' => 'UAT-GUEST-' . uniqid(), 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 100, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 100,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $this->admin()->id,
        ]);

        $this->post(route('admin.fees.process.collection'), [
            'student_id' => $student->id, 'total_amount' => 100, 'payment_mode' => 'cash',
        ])->assertRedirect(route('login'));

        $this->get(route('admin.fees.receipt', $collection->id))->assertRedirect(route('login'));
    }
}
