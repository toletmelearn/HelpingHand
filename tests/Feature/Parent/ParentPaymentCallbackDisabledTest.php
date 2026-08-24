<?php

namespace Tests\Feature\Parent;

use App\Models\ParentModel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P0 security fix (Phase 2A): ParentPaymentController::callbackSuccess()
 * (GET /parent/payments/stripe-success) previously let any authenticated
 * parent fabricate a real, receipted fee_collections row on ANY student's
 * ledger via a crafted request -- no ownership check, no payment-gateway
 * verification of any kind. The endpoint and its route have been removed
 * entirely, since it had no legitimate caller (no UI ever linked to it,
 * confirmed by repository-wide search) and disabling it doesn't touch the
 * real, verified UPI-claim-matching payment path
 * (submitClaim()/PaymentClaimMatchingService), which is untouched by this
 * fix and remains fully covered by its own existing tests.
 */
class ParentPaymentCallbackDisabledTest extends TestCase
{
    use RefreshDatabase;

    private function seedFamily(string $label = 'Target'): array
    {
        $student = Student::create([
            'name' => "{$label} Kid", 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-' . strtoupper($label) . '-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);
        $parent = ParentModel::create([
            'name' => "{$label} Parent",
            'email' => strtolower($label) . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);

        return compact('student', 'parent');
    }

    private function assertNoFeeCollectionsExist(): void
    {
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('fee_collections')->count());
    }

    // 1. Direct callback cannot create a payment.
    public function test_direct_callback_url_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');

        $response = $this->actingAs($own['parent'], 'parent')->get('/parent/payments/stripe-success?' . http_build_query([
            'student_id' => $own['student']->id,
            'fee_structure_id' => 1,
            'amount' => 5000,
        ]));

        $response->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 2. Changing student_id cannot create a payment (IDOR).
    public function test_changing_student_id_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');
        $other = $this->seedFamily('OtherFamily');

        $response = $this->actingAs($own['parent'], 'parent')->get('/parent/payments/stripe-success?' . http_build_query([
            'student_id' => $other['student']->id,
            'fee_structure_id' => 1,
            'amount' => 999999,
        ]));

        $response->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 3. Changing amount cannot create a payment.
    public function test_changing_amount_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');

        $response = $this->actingAs($own['parent'], 'parent')->get('/parent/payments/stripe-success?' . http_build_query([
            'student_id' => $own['student']->id,
            'fee_structure_id' => 1,
            'amount' => 1000000,
        ]));

        $response->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 4. Changing transaction_id (or any other param) cannot create a payment.
    public function test_adding_a_transaction_id_parameter_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');

        $response = $this->actingAs($own['parent'], 'parent')->get('/parent/payments/stripe-success?' . http_build_query([
            'student_id' => $own['student']->id,
            'fee_structure_id' => 1,
            'amount' => 5000,
            'transaction_id' => 'FAKE-TXN-12345',
            'status' => 'success',
        ]));

        $response->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 5. Replaying an old (previously bookmarked/logged) callback URL cannot create a payment.
    public function test_replaying_the_old_callback_url_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');
        $oldStyleUrl = '/parent/payments/stripe-success?student_id=' . $own['student']->id . '&fee_structure_id=1&amount=5000';

        $first = $this->actingAs($own['parent'], 'parent')->get($oldStyleUrl);
        $second = $this->actingAs($own['parent'], 'parent')->get($oldStyleUrl);

        $first->assertNotFound();
        $second->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 6. Unauthenticated access cannot create a payment.
    public function test_unauthenticated_access_cannot_create_a_payment(): void
    {
        $own = $this->seedFamily('Own');

        $response = $this->get('/parent/payments/stripe-success?' . http_build_query([
            'student_id' => $own['student']->id,
            'fee_structure_id' => 1,
            'amount' => 5000,
        ]));

        $response->assertNotFound();
        $this->assertNoFeeCollectionsExist();
    }

    // 7. Authenticated parent access cannot create a payment through the disabled path (route gone entirely, not merely blocked).
    public function test_authenticated_parent_cannot_reach_the_disabled_path_at_all(): void
    {
        $this->assertFalse(Route::has('parent.payments.stripe-success'));
    }

    // 8. Existing fee viewing still works.
    public function test_fee_viewing_still_works(): void
    {
        $family = $this->seedFamily('Viewer');

        $response = $this->actingAs($family['parent'], 'parent')->get('/parent/payments/pay-fees');

        $response->assertOk();
    }

    // 9. Existing admin fee management still works (spot-check: the admin fee collection route resolves and is reachable).
    public function test_admin_fee_management_route_remains_registered(): void
    {
        $this->assertTrue(Route::has('admin.fees.collect.form'));
    }

    // 10. Existing historical payment records remain unchanged by this fix.
    public function test_existing_historical_payment_records_are_unaffected(): void
    {
        $family = $this->seedFamily('Historical');
        $admin = User::factory()->create();
        $feeStructureId = \Illuminate\Support\Facades\DB::table('fee_structures')->insertGetId([
            'class_name' => '8-A',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);

        \App\Models\FeeCollection::create([
            'receipt_no' => 'HIST-0001',
            'student_id' => $family['student']->id,
            'fee_structure_id' => $feeStructureId,
            'total_amount' => 3000,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 3000,
            'payment_date' => now()->subMonth()->format('Y-m-d'),
            'payment_mode' => 'cash',
            'remarks' => 'Pre-existing historical record',
            'collected_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('fee_collections', [
            'receipt_no' => 'HIST-0001',
            'student_id' => $family['student']->id,
            'final_amount' => 3000,
        ]);
    }
}
