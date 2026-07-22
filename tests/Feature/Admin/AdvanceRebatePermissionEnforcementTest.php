<?php

namespace Tests\Feature\Admin;

use App\Models\AdvanceRebateRule;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceRebatePermissionEnforcementTest extends TestCase
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
    public function accountant_has_no_advance_rebate_access_by_default()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.advance-rebate-rules.index'))->assertForbidden();
        $this->actingAs($accountant)->get(route('admin.advance-rebate-rules.create'))->assertForbidden();
    }

    /** @test */
    public function admin_role_always_has_full_advance_rebate_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.advance-rebate-rules.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.advance-rebate-rules.create'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_mutating_actions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        Role::where('name', 'clerk')->first()->grantPermission('view-advance-rebate-rules');

        $this->actingAs($clerk)->get(route('admin.advance-rebate-rules.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.advance-rebate-rules.create'))->assertForbidden();
    }

    /** @test */
    public function admin_can_delegate_advance_rebate_management_to_another_role_via_manage_permissions()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');
        $role = Role::where('name', 'accountant')->first();

        $this->actingAs($accountant)->get(route('admin.advance-rebate-rules.create'))->assertForbidden();

        $role->grantPermission('view-advance-rebate-rules');
        $role->grantPermission('manage-advance-rebate-rules');

        $this->actingAs($accountant->fresh())->get(route('admin.advance-rebate-rules.create'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_advance_rebate_permission_checks_entirely()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.advance-rebate-rules.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.advance-rebate-rules.create'))->assertOk();
    }

    /** @test */
    public function manual_override_requires_manage_advance_rebate_rules()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');
        $feeType = FeeType::create(['name' => 'Annual Charges', 'status' => 'active']);
        $rule = AdvanceRebateRule::create([
            'name' => 'Full Year Rebate', 'type' => 'percent', 'value' => 5,
            'cutoff_month_day' => '04-30', 'min_coverage' => 'full_session', 'is_active' => true,
        ]);
        $student = Student::create([
            'name' => 'Test Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2014-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887766', 'address' => 'Somewhere',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'apply', 'advance_rebate_rule_id' => $rule->id,
            'academic_year' => '2025-2026', 'amount' => 500,
        ]);
        $response->assertRedirect();

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'apply', 'advance_rebate_rule_id' => $rule->id,
            'academic_year' => '2025-2026', 'amount' => 500,
        ])->assertForbidden();
    }
}
