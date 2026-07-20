<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeCollectionAndOperationsPermissionTest extends TestCase
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

    /**
     * The exact reported bug: clerk previously had can-manage-fees/
     * create-fees/view-fee-structures granted but FeeCollectionController
     * never checked them, so clerk was always blocked regardless.
     *
     * @test
     */
    public function clerk_with_legacy_fee_permissions_can_now_actually_collect_and_view_fees()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fees');
        $role->grantPermission('can-manage-fees');
        $role->grantPermission('view-fee-structures');

        $this->actingAs($clerk)->get(route('admin.fees.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.fee-structures.index'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.fees.process.collection'), [])->assertStatus(302);
    }

    /** @test */
    public function a_role_with_no_fee_permission_is_forbidden_from_collecting_fees()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.fees.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.fees.process.collection'), [])->assertForbidden();
    }

    /** @test */
    public function view_only_permission_does_not_grant_fee_collection()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fees');
        // PermissionSeeder grants clerk can-manage-fees by default (clerks
        // are expected to collect payments) -- revoke it here so this test
        // still isolates the view-fees-vs-can-manage-fees tier distinction
        // it's actually about, independent of clerk's evolving default set.
        $role->revokePermission('can-manage-fees');

        $this->actingAs($clerk)->get(route('admin.fees.index'))->assertOk();
        $this->actingAs($clerk)->post(route('admin.fees.process.collection'), [])->assertForbidden();
    }

    /** @test */
    public function accountant_retains_full_access_to_all_seven_modules_by_default()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.fees.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fees.dashboard'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fee-structures.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fees.cashier-closings.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fees.defaulters.dashboard'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fees.reports.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.security-deposits.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.payment-info.show'))->assertOk();
    }

    /** @test */
    public function admin_role_always_has_full_access_to_all_seven_modules()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.fees.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fee-structures.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fees.cashier-closings.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fees.defaulters.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fees.reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.security-deposits.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.payment-info.show'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_all_permission_checks()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.fees.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.fee-structures.index'))->assertOk();
    }

    /** @test */
    public function sidebar_shows_fees_link_once_permission_is_granted()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        // PermissionSeeder grants clerk view-fees by default now -- revoke it
        // first so this test can still exercise the before/after toggle it's
        // actually about.
        $role->revokePermission('view-fees');

        $this->actingAs($clerk)->get(route('admin.dashboard'))->assertDontSee('>Fees<', false);

        $role->grantPermission('view-fees');

        $this->actingAs($clerk->fresh())->get(route('admin.dashboard'))->assertSee('>Fees<', false);
    }

    /** @test */
    public function fee_structures_crud_permission_tiers_are_distinct()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fee-structures');

        $this->actingAs($clerk)->get(route('admin.fee-structures.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.fee-structures.create'))->assertForbidden();

        $role->grantPermission('create-fee-structures');
        $this->actingAs($clerk->fresh())->get(route('admin.fee-structures.create'))->assertOk();
    }
}
