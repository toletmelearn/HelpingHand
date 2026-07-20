<?php

namespace Tests\Feature\Admin;

use App\Models\FeeType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeHeadMasterPermissionEnforcementTest extends TestCase
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
    public function a_role_with_no_fee_type_permission_is_forbidden()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.fee-types.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.fee-types.master'))->assertForbidden();
    }

    /** @test */
    public function accountant_role_retains_fee_head_master_access_by_default()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.fee-types.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fee-types.master'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.fee-types.create'))->assertOk();
    }

    /** @test */
    public function admin_role_always_has_full_fee_head_master_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.fee-types.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.fee-types.master'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_fee_head_master_permission_checks_entirely()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.fee-types.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.fee-types.create'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_mutating_actions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        Role::where('name', 'clerk')->first()->grantPermission('view-fee-types');

        $this->actingAs($clerk)->get(route('admin.fee-types.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.fee-types.create'))->assertForbidden();

        $feeType = FeeType::create(['name' => 'Test Fee', 'status' => 'active']);
        $this->actingAs($clerk)
            ->put(route('admin.fee-types.deactivate', $feeType->id))
            ->assertForbidden();
    }

    /** @test */
    public function admin_can_delegate_fee_head_master_management_to_another_role_via_manage_permissions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();

        $this->actingAs($clerk)->get(route('admin.fee-types.create'))->assertForbidden();

        $role->grantPermission('view-fee-types');
        $role->grantPermission('manage-fee-types');

        $this->actingAs($clerk->fresh())->get(route('admin.fee-types.create'))->assertOk();
    }

    /** @test */
    public function updating_fee_type_master_defaults_requires_manage_permission()
    {
        $this->seedPermissions();
        $feeType = FeeType::create(['name' => 'Tuition', 'status' => 'active']);

        $accountant = $this->makeUserWithRole('accountant');
        $response = $this->actingAs($accountant)->post(route('admin.fee-types.update-master'), [
            'default_frequency' => [$feeType->id => 'monthly'],
        ]);
        $response->assertRedirect(route('admin.fee-structures.index'));

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->post(route('admin.fee-types.update-master'), [
            'default_frequency' => [$feeType->id => 'monthly'],
        ])->assertForbidden();
    }
}
