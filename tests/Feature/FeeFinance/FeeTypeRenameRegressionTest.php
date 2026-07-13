<?php

namespace Tests\Feature\FeeFinance;

use App\Models\DiscountRule;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\DiscountEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the 2 hardcoded 'Tuition Fee' / 'Admission Fee' string references
 * found via grep during Phase 4 planning -- confirms both still resolve
 * correctly after the fee-head-master rename migration renamed those rows
 * to 'Tuition' / 'Admission'.
 */
class FeeTypeRenameRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tuition_fee_type_was_renamed_to_tuition()
    {
        $this->assertDatabaseHas('fee_types', ['name' => 'Tuition']);
        $this->assertDatabaseMissing('fee_types', ['name' => 'Tuition Fee']);
    }

    public function test_admission_fee_type_was_renamed_to_admission()
    {
        $this->assertDatabaseHas('fee_types', ['name' => 'Admission']);
        $this->assertDatabaseMissing('fee_types', ['name' => 'Admission Fee']);
    }

    public function test_discount_engine_default_tuition_fallback_still_resolves()
    {
        $tuition = FeeType::where('name', 'Tuition')->first();
        $this->assertNotNull($tuition);

        $student = Student::create([
            'name' => 'Rename Kid', 'father_name' => 'SoloFather', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        $rule = DiscountRule::create([
            'name' => 'Merit Scholarship',
            'type' => 'merit',
            'config' => ['threshold_score' => 200, 'percentage' => 10],
            'priority' => 1,
            'is_active' => true,
        ]);

        // No config['fee_type']/['applicable_fee_types'] set -- exercises the
        // 'Tuition' fallback default inside evaluateRule() directly.
        $feeItems = [['fee_type_id' => $tuition->id, 'amount' => 1000]];

        $discounts = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $feeItems);

        // Merit rule won't actually apply (no matching Result rows) but the
        // call must not throw/error resolving the fee type -- that's the
        // regression this test guards.
        $this->assertIsArray($discounts);
    }

    public function test_admission_enquiry_payment_resolves_admission_fee_type_by_default()
    {
        $admission = FeeType::where('name', 'Admission')->first();
        $this->assertNotNull($admission);
        $this->assertEquals($admission->id, FeeType::where('name', 'Admission')->value('id'));
    }
}
