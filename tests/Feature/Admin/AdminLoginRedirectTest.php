<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Local pilot blocker fix: CentralLoginController::login() redirected every
 * successful admin-role login to '/home' -> HomeController::index(), which
 * shows 0 students / 0 teachers (it counts User records carrying the
 * 'teacher'/'student' role, not the actual Student/Teacher tables this app
 * is built on -- teachers authenticate through a separate TeacherLogin
 * guard entirely). The correct, already-built, already-tested dashboard at
 * admin.dashboard was reachable only by typing the URL directly. Fixed by
 * changing only the admin-role branch of the login redirect.
 */
class AdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $password = 'password123'): User
    {
        $user = User::factory()->create([
            'email' => 'admin-redirect-uat@example.com',
            'password' => Hash::make($password),
        ]);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_login_succeeds_and_redirects_to_admin_dashboard(): void
    {
        $this->makeAdmin('password123');

        $response = $this->post('/login', [
            'login' => 'admin-redirect-uat@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated('web');
        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_dashboard_loads_correctly_after_login_and_shows_real_data(): void
    {
        $admin = $this->makeAdmin();
        Student::create([
            'name' => 'Redirect UAT Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2012-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
        ]);
        Teacher::create(['name' => 'Redirect UAT Teacher', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['total_students'] === 1 && $stats['total_teachers'] === 1);
    }

    /**
     * Preserve existing behavior: non-admin web-guard users (and any role
     * not explicitly branched) must still land on '/home', unchanged.
     */
    public function test_non_admin_web_login_still_redirects_to_home(): void
    {
        $user = User::factory()->create([
            'email' => 'plain-user-uat@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'login' => 'plain-user-uat@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated('web');
        $response->assertRedirect('/home');
    }

    public function test_receptionist_login_still_redirects_to_front_office_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'receptionist-uat@example.com',
            'password' => Hash::make('password123'),
        ]);
        $role = Role::firstOrCreate(['name' => 'receptionist'], ['display_name' => 'Receptionist']);
        $user->roles()->attach($role->id);

        $response = $this->post('/login', [
            'login' => 'receptionist-uat@example.com',
            'password' => 'password123',
        ]);

        $this->assertAuthenticated('web');
        $response->assertRedirect(route('admin.front-office.dashboard'));
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
