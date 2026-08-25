<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fees V1 completion pass: two live, unguarded controllers.
 *
 * Admin\FeeAutomationController had only 'auth' -- no permission or role
 * check at all -- so any authenticated account of any role could view
 * pending fees, the defaulters list, and the fee dashboard, and could
 * even trigger a WhatsApp reminder to a parent for an arbitrary student.
 *
 * Admin\FeeReceiptController had NO authorization whatsoever -- not even
 * 'auth' -- so any authenticated account of any role could download any
 * student's fee receipt PDF (amounts, payment mode, transaction id) by
 * guessing/iterating the id.
 *
 * Both now match Admin\FeeCollectionController's own view-fees/
 * can-manage-fees split. Uses 'super-admin' (code-level bypass via
 * User::isSuperAdmin()) for the authorized side, since this test suite's
 * permission catalog is seeded by PermissionSeeder, which RefreshDatabase
 * does not run.
 */
class FeeAutomationAndReceiptAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        $user = User::factory()->create(['role' => 'super-admin']);
        $role = Role::firstOrCreate(['name' => 'super-admin'], ['display_name' => 'Super Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeCollection(): FeeCollection
    {
        $student = Student::create([
            'name' => 'Fee Automation Test Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
        ]);
        $structure = FeeStructure::create([
            'class_name' => 'Fee Automation Class', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active', 'is_active' => true,
        ]);

        return FeeCollection::create([
            'receipt_no' => 'AUTOMATION-TEST-' . uniqid(), 'student_id' => $student->id,
            'fee_structure_id' => $structure->id, 'total_amount' => 500, 'discount' => 0, 'late_fine' => 0,
            'final_amount' => 500, 'payment_date' => today(), 'payment_mode' => 'cash',
            'collected_by' => $this->superAdmin()->id,
        ]);
    }

    // ------------------------------------------------------------
    // FeeAutomationController
    // ------------------------------------------------------------

    public function test_non_privileged_user_cannot_view_pending_fees(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.fees.pending'))->assertForbidden();
    }

    public function test_non_privileged_user_cannot_view_defaulters(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.fees.defaulters'))->assertForbidden();
    }

    public function test_non_privileged_user_cannot_view_fee_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.fee-dashboard'))->assertForbidden();
    }

    public function test_non_privileged_user_cannot_send_a_whatsapp_reminder(): void
    {
        $student = Student::create([
            'name' => 'Reminder Target', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2013-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887766', 'address' => 'Addr',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.fees.send-whatsapp-reminder'), ['student_id' => $student->id])
            ->assertForbidden();
    }

    public function test_guest_cannot_view_pending_fees(): void
    {
        $this->get(route('admin.fees.pending'))->assertRedirect(route('login'));
    }

    public function test_privileged_user_can_view_pending_fees(): void
    {
        $this->actingAs($this->superAdmin())->get(route('admin.fees.pending'))->assertOk();
    }

    // ------------------------------------------------------------
    // FeeReceiptController
    // ------------------------------------------------------------

    public function test_non_privileged_user_cannot_download_a_fee_receipt_pdf(): void
    {
        $collection = $this->makeCollection();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.fees.receipt.pdf', $collection->id))
            ->assertForbidden();
    }

    public function test_guest_cannot_download_a_fee_receipt_pdf(): void
    {
        $collection = $this->makeCollection();

        $this->get(route('admin.fees.receipt.pdf', $collection->id))->assertRedirect(route('login'));
    }

    public function test_privileged_user_can_download_a_fee_receipt_pdf(): void
    {
        $collection = $this->makeCollection();

        $response = $this->actingAs($this->superAdmin())->get(route('admin.fees.receipt.pdf', $collection->id));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
