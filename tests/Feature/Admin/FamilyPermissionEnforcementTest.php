<?php

namespace Tests\Feature\Admin;

use App\Models\Family;
use App\Models\FamilyLinkSuggestion;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilyPermissionEnforcementTest extends TestCase
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

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Test Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887755', 'address' => 'Somewhere',
        ]);
    }

    /** @test */
    public function a_role_with_no_family_permission_is_forbidden()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.families.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.family-link-suggestions.index'))->assertForbidden();
    }

    /** @test */
    public function accountant_role_retains_family_access_by_default()
    {
        // Unlike Discount Rules/Advance Rebates, this is a routine
        // accountant duty -- access is preserved by default, matching the
        // original role:accountant behavior.
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.families.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.family-link-suggestions.index'))->assertOk();
    }

    /** @test */
    public function admin_role_always_has_full_family_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.families.index'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_family_permission_checks_entirely()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.families.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.family-link-suggestions.index'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_confirm_or_dismiss()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        Role::where('name', 'clerk')->first()->grantPermission('view-families');

        $student = $this->makeStudent();
        $suggestion = FamilyLinkSuggestion::create([
            'student_id' => $student->id, 'match_basis' => 'mobile',
            'matched_value' => '9998887755', 'status' => 'pending',
        ]);

        $this->actingAs($clerk)->get(route('admin.family-link-suggestions.index'))->assertOk();
        $this->actingAs($clerk)
            ->post(route('admin.family-link-suggestions.confirm', $suggestion->id))
            ->assertForbidden();
    }

    /** @test */
    public function admin_can_delegate_family_management_to_another_role_via_manage_permissions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();

        $student = $this->makeStudent();
        $suggestion = FamilyLinkSuggestion::create([
            'student_id' => $student->id, 'match_basis' => 'mobile',
            'matched_value' => '9998887755', 'status' => 'pending',
        ]);

        $this->actingAs($clerk)
            ->post(route('admin.family-link-suggestions.dismiss', $suggestion->id))
            ->assertForbidden();

        $role->grantPermission('view-families');
        $role->grantPermission('manage-families');

        $this->actingAs($clerk->fresh())
            ->post(route('admin.family-link-suggestions.dismiss', $suggestion->id))
            ->assertRedirect();

        $this->assertEquals('dismissed', $suggestion->fresh()->status);
    }
}
