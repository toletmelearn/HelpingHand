<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarOpeningBalanceLinkTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function opening_balance_link_renders_in_sidebar_for_admin_role()
    {
        $admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($adminRole->id);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('Upload Opening Balance (Previous Fee)');
    }
}
