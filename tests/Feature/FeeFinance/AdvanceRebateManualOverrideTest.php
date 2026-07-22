<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdvanceRebateRule;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceRebateManualOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);
        return $admin;
    }

    private function makeAccountant(): User
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $role = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);
        $accountant->roles()->attach($role->id);
        return $accountant;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Override Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);
    }

    public function test_admin_can_manually_apply_a_rebate()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $rule = AdvanceRebateRule::create([
            'name' => 'Manual Rule', 'type' => 'fixed', 'value' => 500, 'cutoff_month_day' => '06-30', 'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'apply',
            'advance_rebate_rule_id' => $rule->id,
            'academic_year' => '2026-2027',
            'amount' => 800.00,
        ]);

        $response->assertSessionHas('success');
        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(800.00, $snapshot->rebate_amount);
        $this->assertEquals($admin->id, $snapshot->approved_by);
        $this->assertEquals(-800.00, LedgerService::getOutstandingBalance($student->id));
    }

    public function test_accountant_cannot_reach_manual_override_endpoint()
    {
        $accountant = $this->makeAccountant();
        $student = $this->makeStudent();
        $rule = AdvanceRebateRule::create([
            'name' => 'Manual Rule', 'type' => 'fixed', 'value' => 500, 'cutoff_month_day' => '06-30', 'is_active' => true,
        ]);

        $response = $this->actingAs($accountant)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'apply',
            'advance_rebate_rule_id' => $rule->id,
            'academic_year' => '2026-2027',
            'amount' => 800.00,
        ]);

        $response->assertForbidden();
        $this->assertEquals(0, StudentAdvanceRebate::where('student_id', $student->id)->count());
    }

    public function test_accountant_cannot_reach_rule_crud_routes()
    {
        $accountant = $this->makeAccountant();

        $response = $this->actingAs($accountant)->get(route('admin.advance-rebate-rules.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_cancel_an_applied_rebate()
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $rule = AdvanceRebateRule::create([
            'name' => 'Manual Rule', 'type' => 'fixed', 'value' => 500, 'cutoff_month_day' => '06-30', 'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'apply', 'advance_rebate_rule_id' => $rule->id, 'academic_year' => '2026-2027', 'amount' => 800.00,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.advance-rebate-rules.manual-override', $student->id), [
            'action' => 'cancel', 'academic_year' => '2026-2027',
        ]);

        $response->assertSessionHas('success');
        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertEquals('clawed_back', $snapshot->status);
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));
    }
}
