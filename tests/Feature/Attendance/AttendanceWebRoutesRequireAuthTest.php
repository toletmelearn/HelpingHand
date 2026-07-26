<?php

namespace Tests\Feature\Attendance;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bare (non-admin-prefixed) attendance/* routes are the application's
 * main attendance web UI -- linked from the admin, home, and parent
 * dashboards -- and previously carried only the global 'web' middleware,
 * no auth at all. Remediation Task 3 added auth/verified/
 * redirect.if.not.onboarded to match the admin.attendance.* sibling.
 */
class AttendanceWebRoutesRequireAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_guest_is_redirected_to_login_on_index(): void
    {
        $this->get(route('attendance.index'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_on_create(): void
    {
        $this->get(route('attendance.create'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_on_store(): void
    {
        $this->post(route('attendance.store'), [])->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_on_reports(): void
    {
        $this->get(route('attendance.reports'))->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_on_export(): void
    {
        $this->get(route('attendance.export'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_still_reach_index(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('attendance.index'))->assertOk();
    }
}
