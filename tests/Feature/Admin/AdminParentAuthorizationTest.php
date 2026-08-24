<?php

namespace Tests\Feature\Admin;

use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1A P0 fix: AdminParentController previously had zero authorization
 * (only `auth` middleware) -- any authenticated web-guard account (clerk,
 * accountant, or any other non-admin staff role sharing that guard) could
 * view every parent's PII, rewrite their contact details, and reset an
 * arbitrary parent's password. This proves the new ParentPolicy closes that
 * gap from every angle: unauthorized roles, IDOR via a valid-but-not-owned
 * id, direct URL access, forged requests, and that the legitimate admin
 * workflow still works end-to-end.
 */
class AdminParentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function clerkUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function seedParent(string $label = 'Target'): ParentModel
    {
        $student = Student::create([
            'name' => "{$label} Kid", 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-' . strtoupper($label) . '-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        return ParentModel::create([
            'name' => "{$label} Parent",
            'email' => strtolower($label) . uniqid() . '@example.com',
            'password' => bcrypt('original-password'),
            'student_id' => $student->id,
        ]);
    }

    // --- authorized admin: normal workflow still works ---------------------

    public function test_admin_can_view_parent_index(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('admin.parents.index'));

        $response->assertOk();
    }

    public function test_admin_can_view_a_parent_profile(): void
    {
        $admin = $this->adminUser();
        $parent = $this->seedParent();

        $response = $this->actingAs($admin)->get(route('admin.parents.show', $parent->id));

        $response->assertOk();
    }

    public function test_admin_can_update_a_parent_profile(): void
    {
        $admin = $this->adminUser();
        $parent = $this->seedParent();

        $response = $this->actingAs($admin)->put(route('admin.parents.update', $parent->id), [
            'name' => 'Updated Name',
            'email' => $parent->email,
            'phone' => '9999999999',
            'mobile' => '9999999999',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.parents.show', $parent->id));
        $this->assertDatabaseHas('parents', ['id' => $parent->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_reset_a_parent_password(): void
    {
        $admin = $this->adminUser();
        $parent = $this->seedParent();
        $originalHash = $parent->password;

        $response = $this->actingAs($admin)->post(route('admin.parents.reset-password', $parent->id), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ]);

        $response->assertRedirect(route('admin.parents.show', $parent->id));
        $parent->refresh();
        $this->assertNotSame($originalHash, $parent->password);
        $this->assertTrue($parent->must_reset_password);
    }

    // --- unauthorized roles: every protected operation is blocked ----------

    public function test_clerk_cannot_view_parent_index(): void
    {
        $clerk = $this->clerkUser();

        $response = $this->actingAs($clerk)->get(route('admin.parents.index'));

        $response->assertForbidden();
    }

    public function test_clerk_cannot_view_a_parent_profile_direct_url(): void
    {
        $clerk = $this->clerkUser();
        $parent = $this->seedParent();

        $response = $this->actingAs($clerk)->get(route('admin.parents.show', $parent->id));

        $response->assertForbidden();
    }

    public function test_clerk_cannot_update_a_parent_profile(): void
    {
        $clerk = $this->clerkUser();
        $parent = $this->seedParent();

        $response = $this->actingAs($clerk)->put(route('admin.parents.update', $parent->id), [
            'name' => 'Forged Update',
            'email' => $parent->email,
            'status' => 'active',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('parents', ['id' => $parent->id, 'name' => 'Forged Update']);
    }

    /** The core account-takeover vector this fix closes: a forged password-reset request. */
    public function test_clerk_cannot_reset_a_parent_password_forged_request(): void
    {
        $clerk = $this->clerkUser();
        $parent = $this->seedParent()->refresh();
        $originalHash = $parent->password;
        $originalMustReset = $parent->must_reset_password;

        $response = $this->actingAs($clerk)->post(route('admin.parents.reset-password', $parent->id), [
            'password' => 'attacker-controlled-password',
            'password_confirmation' => 'attacker-controlled-password',
        ]);

        $response->assertForbidden();
        $parent->refresh();
        $this->assertSame($originalHash, $parent->password);
        $this->assertSame($originalMustReset, $parent->must_reset_password);
    }

    public function test_teacher_cannot_view_parent_index(): void
    {
        $teacher = $this->teacherUser();

        $response = $this->actingAs($teacher)->get(route('admin.parents.index'));

        $response->assertForbidden();
    }

    public function test_teacher_cannot_view_a_parent_profile(): void
    {
        $teacher = $this->teacherUser();
        $parent = $this->seedParent();

        $response = $this->actingAs($teacher)->get(route('admin.parents.show', $parent->id));

        $response->assertForbidden();
    }

    /**
     * Parent-guard sessions (a different guard entirely) cannot reach the
     * web/admin-guarded routes at all -- redirected to login, never granted
     * access. Deliberately sets only the 'parent' guard's user directly
     * (not via actingAs(), which also calls Auth::shouldUse() and would
     * make the parent session masquerade as the default web guard here) --
     * this mirrors what a real browser session authenticated only via the
     * parent login actually looks like to the 'auth' (web-guard) middleware.
     */
    public function test_parent_guard_session_cannot_reach_admin_parent_routes(): void
    {
        $parentSession = $this->seedParent('SelfServiceGuard');
        $this->app['auth']->guard('parent')->setUser($parentSession);

        $response = $this->get(route('admin.parents.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.parents.index'));

        $response->assertRedirect(route('login'));
    }

    // --- IDOR: an unauthorized user cannot reach any valid parent id -------

    public function test_clerk_idor_attempt_against_a_different_valid_parent_id_is_blocked(): void
    {
        $clerk = $this->clerkUser();
        $decoyParent = $this->seedParent('Decoy');
        $realTargetParent = $this->seedParent('RealTarget');

        // Even with a genuinely valid id belonging to a totally unrelated
        // parent record, a clerk gets 403 -- the block is role-based, not
        // dependent on which id is guessed.
        $response = $this->actingAs($clerk)->get(route('admin.parents.show', $realTargetParent->id));
        $response->assertForbidden();

        $response2 = $this->actingAs($clerk)->get(route('admin.parents.show', $decoyParent->id));
        $response2->assertForbidden();
    }
}
