<?php

namespace Tests\Feature\Admin;

use App\Models\Budget;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetPermissionEnforcementTest extends TestCase
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
    public function a_role_with_no_budget_permission_is_forbidden()
    {
        $this->seedPermissions();
        $teacher = $this->makeUserWithRole('teacher');

        $this->actingAs($teacher)->get(route('admin.budgets.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.expenses.index'))->assertForbidden();
    }

    /** @test */
    public function accountant_role_has_budget_and_expense_access_by_default()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');

        $this->actingAs($accountant)->get(route('admin.budgets.index'))->assertOk();
        $this->actingAs($accountant)->get(route('admin.expenses.index'))->assertOk();
    }

    /** @test */
    public function admin_role_always_has_full_budget_access()
    {
        $this->seedPermissions();
        $admin = $this->makeUserWithRole('admin');

        $this->actingAs($admin)->get(route('admin.budgets.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.budgets.create'))->assertOk();
    }

    /** @test */
    public function view_only_permission_does_not_grant_mutating_actions()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $viewOnlyRole = Role::where('name', 'clerk')->first();
        $viewOnlyRole->grantPermission('view-budgets');
        // Deliberately NOT granting manage-budgets.

        $this->actingAs($clerk)->get(route('admin.budgets.index'))->assertOk();
        $this->actingAs($clerk)->get(route('admin.budgets.create'))->assertForbidden();
    }

    /** @test */
    public function granting_manage_budgets_through_the_role_permission_ui_unlocks_creation()
    {
        $this->seedPermissions();
        $clerk = $this->makeUserWithRole('clerk');
        $role = Role::where('name', 'clerk')->first();

        $this->actingAs($clerk)->get(route('admin.budgets.create'))->assertForbidden();

        $role->grantPermission('view-budgets');
        $role->grantPermission('manage-budgets');

        $this->actingAs($clerk->fresh())->get(route('admin.budgets.create'))->assertOk();
    }

    /** @test */
    public function super_admin_bypasses_budget_permission_checks_entirely()
    {
        $this->seedPermissions();
        $superAdmin = $this->makeUserWithRole('super-admin');

        $this->actingAs($superAdmin)->get(route('admin.budgets.index'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.budgets.create'))->assertOk();
    }

    /** @test */
    public function expense_approve_and_reject_require_manage_expenses()
    {
        $this->seedPermissions();
        $accountant = $this->makeUserWithRole('accountant');
        $category = \App\Models\BudgetCategory::create(['name' => 'Test Category', 'is_active' => true]);
        $budget = Budget::create([
            'name' => 'FY Budget', 'fiscal_year' => 2026, 'total_amount' => 100000,
            'allocated_amount' => 100000, 'spent_amount' => 0, 'status' => 'approved',
            'created_by' => $accountant->id,
        ]);
        $expense = Expense::create([
            'budget_id' => $budget->id, 'budget_category_id' => $category->id,
            'title' => 'Test Expense', 'amount' => 500, 'expense_date' => now(),
            'status' => 'pending', 'created_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)
            ->put(route('admin.expense.approve', $expense->id))
            ->assertStatus(302); // accountant has manage-expenses -- succeeds (redirect), not 403

        $teacher = $this->makeUserWithRole('teacher');
        $this->actingAs($teacher)
            ->put(route('admin.expense.approve', $expense->id))
            ->assertForbidden();
    }
}
