<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountAndFamilyPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        // These pages are permission-gated (view-discount-rules, view-families,
        // ...); the admin role only holds them once the catalog is seeded.
        (new PermissionSeeder())->run();

        return $admin;
    }

    /** @test */
    public function discount_rules_index_loads_for_admin()
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.discount-rules.index'))
            ->assertOk();
    }

    /** @test */
    public function families_index_loads_for_admin()
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.families.index'))
            ->assertOk();
    }

    /** @test */
    public function family_link_suggestions_index_loads_for_admin()
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.family-link-suggestions.index'))
            ->assertOk();
    }

    /** @test */
    public function sidebar_links_families_and_family_link_suggestions()
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Families');
        $response->assertSee('Family Link Suggestions');
    }
}
