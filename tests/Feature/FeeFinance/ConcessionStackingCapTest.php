<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdminConfiguration;
use App\Models\DiscountApproval;
use App\Models\DiscountRule;
use App\Models\FeeType;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDiscountApplied;
use App\Models\User;
use App\Services\DiscountEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConcessionStackingCapTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Stacking Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'SC',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'staff_user_id' => null,
        ]);
    }

    private function twoOverlappingRules(): FeeType
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);

        DiscountRule::create([
            'name' => 'Category Concession',
            'type' => 'category',
            'config' => ['mappings' => ['SC' => 40]],
            'priority' => 1,
            'is_active' => true,
        ]);
        DiscountRule::create([
            'name' => 'Merit Concession',
            'type' => 'merit',
            'config' => ['threshold_score' => 0, 'percentage' => 30], // threshold 0 always passes (avg defaults to 0)
            'priority' => 2,
            'is_active' => true,
        ]);

        return $tuition;
    }

    public function test_default_highest_single_wins_returns_only_one_discount()
    {
        $tuition = $this->twoOverlappingRules();
        $student = $this->makeStudent();

        $engine = new DiscountEngineService();
        $result = $engine->calculateDiscounts($student, 'April', '2026-2027', [['fee_type_id' => $tuition->id, 'amount' => 1000.00]]);

        $this->assertCount(1, $result);
        $this->assertEquals(400.00, $result[0]['amount']); // the larger of 40% and 30%
    }

    public function test_stack_with_cap_sums_and_clamps_to_the_configured_percent()
    {
        AdminConfiguration::set('fee', 'concession_stacking_policy', 'stack_with_cap', 'string', 'Stacking Policy');
        AdminConfiguration::set('fee', 'concession_stacking_cap_percent', '50', 'string', 'Stacking Cap');

        $tuition = $this->twoOverlappingRules();
        $student = $this->makeStudent();

        $engine = new DiscountEngineService();
        $result = $engine->calculateDiscounts($student, 'April', '2026-2027', [['fee_type_id' => $tuition->id, 'amount' => 1000.00]]);

        // 40% + 30% = 70%, capped at 50% of 1000 = 500.
        $this->assertCount(1, $result);
        $this->assertEquals(500.00, $result[0]['amount']);
    }

    public function test_stack_with_cap_does_not_clamp_when_sum_is_under_the_cap()
    {
        AdminConfiguration::set('fee', 'concession_stacking_policy', 'stack_with_cap', 'string', 'Stacking Policy');
        AdminConfiguration::set('fee', 'concession_stacking_cap_percent', '90', 'string', 'Stacking Cap');

        $tuition = $this->twoOverlappingRules();
        $student = $this->makeStudent();

        $engine = new DiscountEngineService();
        $result = $engine->calculateDiscounts($student, 'April', '2026-2027', [['fee_type_id' => $tuition->id, 'amount' => 1000.00]]);

        $this->assertCount(1, $result);
        $this->assertEquals(700.00, $result[0]['amount']); // 40% + 30% = 70%, under the 90% cap
    }

    public function test_manually_verified_discount_snapshot_records_approved_by_and_applied_at()
    {
        Storage::fake('public');
        $clerk = User::factory()->create(['role' => 'clerk']);
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $clerk->roles()->attach($role->id);

        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $student = $this->makeStudent();
        $rule = DiscountRule::create([
            'name' => 'Manual Override', 'type' => 'category', 'config' => [], 'priority' => 1, 'is_active' => true,
        ]);

        $approval = DiscountApproval::create([
            'student_id' => $student->id,
            'discount_rule_id' => $rule->id,
            'fee_type_id' => $tuition->id,
            'amount' => 300.00,
            'month' => 'April',
            'academic_year' => '2026-2027',
            'status' => 'pending_verification',
            'created_by' => $clerk->id,
        ]);

        $response = $this->actingAs($clerk)->post(route('admin.discount-approvals.verify', $approval->id), [
            'verification_slip' => UploadedFile::fake()->create('slip.pdf', 100),
        ]);

        $response->assertSessionHas('success');

        $snapshot = StudentDiscountApplied::where('student_id', $student->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals($clerk->id, $snapshot->approved_by);
        $this->assertNotNull($snapshot->applied_at);
    }
}
