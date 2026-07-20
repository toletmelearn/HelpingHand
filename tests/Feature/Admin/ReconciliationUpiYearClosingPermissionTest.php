<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationUpiYearClosingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function seedPermissions(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        (new PermissionSeeder())->run();
    }

    /** @test */
    public function a_role_with_no_permission_is_forbidden_from_all_three_modules()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.finance.reconciliation.overpayments'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.payment-claims.queue'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.fees.year-closing.index'))->assertForbidden();
    }

    /** @test */
    public function accountant_retains_full_access_to_all_three_modules_by_default()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.finance.reconciliation.overpayments'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.payment-claims.queue'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fees.year-closing.index'))->assertOk();
    }

    /** @test */
    public function admin_role_always_has_full_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.finance.reconciliation.overpayments'))->assertOk();
        $this->actingAs($admin)->get(route('admin.payment-claims.queue'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fees.year-closing.index'))->assertOk();
    }

    /**
     * Mirrors the real-world state: clerk was already granted
     * manage-reconciliation/manage-upi-matching/manage-year-closing (and
     * their view- counterparts) via Manage Permissions before this wiring
     * existed -- those grants must now actually work.
     *
     * @test
     */
    public function clerk_with_pre_granted_permissions_now_actually_gets_access()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        foreach (['view-reconciliation', 'manage-reconciliation', 'view-upi-matching', 'manage-upi-matching', 'view-year-closing', 'manage-year-closing'] as $perm) {
            $role->grantPermission($perm);
        }

        $this->actingAs($clerk)->get(route('admin.finance.reconciliation.overpayments'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.finance.reconciliation.rebuild-ledger'), ['student_id' => 999999])
            ->assertStatus(302); // passes permission gate; 404/validation is a separate concern
        $this->actingAs($clerk)->get(route('admin.payment-claims.queue'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.payment-claims.run-matching'))->assertStatus(302);
        $this->actingAs($clerk)->get(route('admin.fees.year-closing.index'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_mutating_actions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-reconciliation');
        $role->grantPermission('view-upi-matching');
        $role->grantPermission('view-year-closing');

        $this->actingAs($clerk)->get(route('admin.finance.reconciliation.overpayments'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.finance.reconciliation.bulk-assign'), ['ledger_ids' => [1]])->assertForbidden();

        $this->actingAs($clerk)->get(route('admin.payment-claims.queue'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.payment-claims.run-matching'))->assertForbidden();

        $this->actingAs($clerk)->get(route('admin.fees.year-closing.index'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.fees.year-closing.stage'))->assertForbidden();
    }

    /** @test */
    public function super_admin_bypasses_all_three_permission_checks()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.finance.reconciliation.overpayments'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.payment-claims.queue'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.fees.year-closing.index'))->assertOk();
    }

    /** @test */
    public function sidebar_only_shows_links_the_role_actually_has_permission_for()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fees');
        $role->grantPermission('view-reconciliation');

        $response = $this->actingAs($clerk)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Reconciliation Center');
        $response->assertDontSee('UPI Payment Matching');
        $response->assertDontSee('Year-End Closing');
    }
}
