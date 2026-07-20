<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarPermissionVisibilityTest extends TestCase
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
     * Mirrors the exact real-world report: clerk has view-discount-rules
     * granted, but NOT view-families/view-advance-rebate-rules/view-fee-types.
     * The sidebar must show only the one it's actually allowed into.
     *
     * @test
     */
    public function clerk_only_sees_sidebar_links_it_has_been_granted()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fees'); // so the Fee Management section itself is visible
        $role->grantPermission('view-discount-rules');

        $response = $this->actingAs($clerk)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Discount Rules');
        $response->assertDontSee('Family Link Suggestions');
        $response->assertDontSee('Advance Payment Rebates');
        $response->assertDontSee('Fee Head Master');
    }

    /**
     * Mirrors the real-world report for accountant: has view-budgets/
     * view-families/view-fee-types by default, but NOT view-discount-rules/
     * view-advance-rebate-rules. Those two must not appear.
     *
     * @test
     */
    public function accountant_does_not_see_discount_rules_or_advance_rebates_it_was_never_granted()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $response = $this->actingAs($accountant)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Families');
        $response->assertSee('Fee Head Master');
        $response->assertSee('Budget');
        $response->assertDontSee('Discount Rules');
        $response->assertDontSee('Advance Payment Rebates');
    }

    /** @test */
    public function admin_sees_every_wired_fee_link_regardless_of_not_holding_the_accountant_role()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Discount Rules');
        $response->assertSee('Advance Payment Rebates');
        $response->assertSee('Families');
        $response->assertSee('Family Link Suggestions');
        $response->assertSee('Fee Head Master');
        $response->assertSee('Budget');
        $response->assertSee('Expenses');
    }

    /**
     * Once the admin grants a role a permission through Manage Permissions,
     * the matching sidebar link must appear on the very next page load.
     *
     * @test
     */
    public function granting_a_permission_makes_the_matching_link_appear()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();
        $role->grantPermission('view-fees');

        $this->actingAs($clerk)->get(route('admin.dashboard'))->assertDontSee('Advance Payment Rebates');

        $role->grantPermission('view-advance-rebate-rules');

        $this->actingAs($clerk->fresh())->get(route('admin.dashboard'))->assertSee('Advance Payment Rebates');
    }

    /**
     * A role with none of the underlying fee permissions must not see the
     * Fee Management section at all (not just individual links hidden).
     *
     * @test
     */
    public function a_role_with_no_fee_permissions_does_not_see_the_fee_management_section()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $response = $this->actingAs($teacher)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Fee Management');
    }
}
