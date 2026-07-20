<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountRulePermissionEnforcementTest extends TestCase
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
    public function accountant_has_no_discount_rule_access_by_default()
    {
        // Unlike Budget, this stays admin-only by default -- it's a
        // financial-policy decision, matching the original role:admin
        // behavior. The admin must explicitly delegate it if they want to.
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.discount-rules.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('admin.discount-rules.create'))->assertForbidden();
    }

    /** @test */
    public function admin_role_always_has_full_discount_rule_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.discount-rules.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.discount-rules.create'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_mutating_actions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        Role::where('name', 'clerk')->first()->grantPermission('view-discount-rules');

        $this->actingAs($clerk)->get(route('admin.discount-rules.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.discount-rules.create'))->assertForbidden();
    }

    /** @test */
    public function admin_can_delegate_discount_rule_management_to_another_role_via_manage_permissions()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');
        $role = Role::where('name', 'accountant')->first();

        $this->actingAs($accountant)->get(route('admin.discount-rules.create'))->assertForbidden();

        $role->grantPermission('view-discount-rules');
        $role->grantPermission('manage-discount-rules');

        $this->actingAs($accountant->fresh())->get(route('admin.discount-rules.create'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_discount_rule_permission_checks_entirely()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.discount-rules.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.discount-rules.create'))->assertOk();
    }

    /** @test */
    public function creating_a_discount_rule_requires_manage_permission()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.discount-rules.store'), [
            'name' => 'Sibling Discount',
            'type' => 'sibling',
            'priority' => 10,
            'is_active' => '1',
            'percentage' => 10,
        ]);

        $response->assertRedirect(route('admin.discount-rules.index'));
        $this->assertDatabaseHas('discount_rules', ['name' => 'Sibling Discount']);

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->post(route('admin.discount-rules.store'), [
            'name' => 'Blocked Rule', 'type' => 'sibling', 'is_active' => '1',
        ])->assertForbidden();
    }
}
