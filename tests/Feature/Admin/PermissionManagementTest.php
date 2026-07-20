<?php

namespace Tests\Feature\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        return $admin;
    }

    /** @test */
    public function seeder_creates_a_comprehensive_module_tagged_permission_catalog()
    {
        (new PermissionSeeder())->run();

        $this->assertGreaterThan(150, Permission::count(), 'Expected a comprehensive permission catalog, not just the original hand-picked set.');
        $this->assertGreaterThan(30, Permission::distinct('module')->count('module'), 'Expected permissions grouped across many modules.');

        // Every permission must be tagged with a module + human label for the UI to group it.
        $this->assertEquals(0, Permission::whereNull('module')->orWhereNull('label')->count());

        // New modules exist alongside the original ones, both intact.
        $this->assertDatabaseHas('permissions', ['name' => 'view-fees']); // original
        $this->assertDatabaseHas('permissions', ['name' => 'view-budgets', 'module' => 'budgets']); // new
        $this->assertDatabaseHas('permissions', ['name' => 'manage-hostel', 'module' => 'hostel']); // new
    }

    /** @test */
    public function admin_role_is_granted_every_permission_after_seeding()
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);

        (new PermissionSeeder())->run();

        $admin = Role::where('name', 'admin')->first();
        $this->assertEquals(Permission::count(), $admin->permissions()->count());
    }

    /** @test */
    public function seeder_is_idempotent_and_does_not_duplicate_or_lose_permissions()
    {
        (new PermissionSeeder())->run();
        $firstCount = Permission::count();

        (new PermissionSeeder())->run();
        $secondCount = Permission::count();

        $this->assertEquals($firstCount, $secondCount);
    }

    /** @test */
    public function manage_permissions_page_renders_permissions_grouped_by_module()
    {
        (new PermissionSeeder())->run();
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.role-permissions.edit', $role->id));

        $response->assertOk();
        $response->assertSee('Budget'); // module label appears somewhere in a grouped section
        $response->assertSee('Grant all in this module');
    }

    /** @test */
    public function admin_can_grant_a_new_module_permission_to_a_role_through_the_edit_form()
    {
        (new PermissionSeeder())->run();
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $permission = Permission::where('name', 'manage-hostel')->firstOrFail();

        $this->assertFalse($role->fresh()->hasPermission('manage-hostel'));

        $response = $this->actingAs($this->makeAdmin())->put(route('admin.role-permissions.update', $role->id), [
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('admin.role-permissions.index'));
        $this->assertTrue($role->fresh()->hasPermission('manage-hostel'));
    }

    /** @test */
    public function permissions_index_can_be_filtered_by_module()
    {
        (new PermissionSeeder())->run();

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.permissions.index', ['module' => 'library']));

        $response->assertOk();
        $response->assertSee('view-library');
        $response->assertDontSee('view-budget');
    }
}
