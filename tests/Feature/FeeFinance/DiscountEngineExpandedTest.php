<?php

namespace Tests\Feature\FeeFinance;

use App\Models\DiscountRule;
use App\Models\FeeType;
use App\Models\Student;
use App\Services\DiscountEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountEngineExpandedTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Test Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ], $overrides));
    }

    private function tuition(): FeeType
    {
        return FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
    }

    private function feeItemsFor(FeeType $type, float $amount = 2000.00): array
    {
        return [['fee_type_id' => $type->id, 'amount' => $amount]];
    }

    private function makeExamId(): int
    {
        $class = \App\Models\SchoolClass::firstOrCreate(['name' => 'Class 10'], ['class_order' => 10, 'is_active' => true]);
        $subject = \App\Models\Subject::firstOrCreate(['name' => 'Science'], ['code' => 'Science', 'is_active' => true]);

        return \App\Models\Exam::create([
            'name' => 'Test Exam', 'exam_type' => 'mid_term',
            'class_id' => $class->id, 'class_name' => $class->name,
            'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => '2026-07-01', 'start_time' => '09:00:00',
            'end_time' => '12:00:00', 'total_marks' => 100, 'passing_marks' => 33, 'academic_year' => '2026',
        ])->id;
    }

    private function makeResult(int $studentId, float $percentage, string $academicYear = '2026-2027'): void
    {
        \App\Models\Result::create([
            'student_id' => $studentId,
            'exam_id' => $this->makeExamId(),
            'subject' => 'Science',
            'marks_obtained' => $percentage,
            'total_marks' => 100.00,
            'percentage' => $percentage,
            'grade' => 'A',
            'academic_year' => $academicYear,
        ]);
    }

    public function test_flat_amount_mode_gives_exact_rupee_discount_not_a_percentage()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Flat Scholarship',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 500]],
            'discount_mode' => 'flat_amount',
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(500.00, $result[0]['amount']);
    }

    public function test_flat_amount_never_exceeds_the_base_fee_it_discounts()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Oversized Flat Scholarship',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 5000]], // more than the fee itself
            'discount_mode' => 'flat_amount',
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertEquals(2000.00, $result[0]['amount']);
    }

    public function test_rule_before_its_valid_from_date_does_not_apply()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Future Rule',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 50]],
            'valid_from' => now()->addMonth()->toDateString(),
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition));

        $this->assertEmpty($result);
    }

    public function test_rule_after_its_valid_until_date_does_not_apply()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Expired Rule',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 50]],
            'valid_until' => now()->subMonth()->toDateString(),
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition));

        $this->assertEmpty($result);
    }

    public function test_rule_within_its_validity_window_applies_normally()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Currently Valid Rule',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 50]],
            'valid_from' => now()->subMonth()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(1000.00, $result[0]['amount']);
    }

    public function test_max_cap_amount_limits_a_percentage_discount()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'General']);

        DiscountRule::create([
            'name' => 'Capped Percentage Rule',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 90]], // 90% of 2000 = 1800, would exceed the cap
            'max_cap_amount' => 300.00,
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertEquals(300.00, $result[0]['amount']);
    }

    public function test_rte_quota_only_applies_to_flagged_students()
    {
        $tuition = $this->tuition();
        $rteStudent = $this->makeStudent(['is_rte' => true]);
        $regularStudent = $this->makeStudent(['is_rte' => false]);

        DiscountRule::create([
            'name' => 'RTE Waiver',
            'type' => 'rte_quota',
            'config' => ['percentage' => 100],
            'priority' => 10,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $rteResult = $engine->calculateDiscounts($rteStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $regularResult = $engine->calculateDiscounts($regularStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $rteResult);
        $this->assertEquals(2000.00, $rteResult[0]['amount']);
        $this->assertEmpty($regularResult);
    }

    public function test_early_payment_discount_applies_only_before_the_cutoff_date()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent();

        DiscountRule::create([
            'name' => 'Early Bird',
            'type' => 'early_payment',
            'config' => ['percentage' => 5, 'cutoff_date' => now()->addWeek()->toDateString()],
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(100.00, $result[0]['amount']);
    }

    public function test_early_payment_discount_does_not_apply_after_the_cutoff_date()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent();

        DiscountRule::create([
            'name' => 'Missed Early Bird',
            'type' => 'early_payment',
            'config' => ['percentage' => 5, 'cutoff_date' => now()->subWeek()->toDateString()],
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition));

        $this->assertEmpty($result);
    }

    public function test_gender_based_discount_applies_only_to_the_configured_gender()
    {
        $tuition = $this->tuition();
        $girl = $this->makeStudent(['name' => 'Girl Student', 'gender' => 'female']);
        $boy = $this->makeStudent(['name' => 'Boy Student', 'gender' => 'male']);

        DiscountRule::create([
            'name' => 'Girl Child Concession',
            'type' => 'gender_based',
            'config' => ['percentage' => 10, 'gender' => 'female'],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $girlResult = $engine->calculateDiscounts($girl, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $boyResult = $engine->calculateDiscounts($boy, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $girlResult);
        $this->assertEquals(200.00, $girlResult[0]['amount']);
        $this->assertEmpty($boyResult);
    }

    public function test_category_mapping_supports_arbitrary_school_defined_categories()
    {
        // Not just SC/ST/OBC -- any value a school types into the category
        // field can be mapped, e.g. Defence ward.
        $tuition = $this->tuition();
        $student = $this->makeStudent(['category' => 'Defence']);

        DiscountRule::create([
            'name' => 'Defence Ward Concession',
            'type' => 'category',
            'config' => ['mappings' => ['Defence' => 25]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(500.00, $result[0]['amount']);
    }

    public function test_tiered_merit_scholarship_picks_the_highest_qualifying_tier()
    {
        $tuition = $this->tuition();
        $topStudent = $this->makeStudent(['name' => 'Top Scorer']);
        $this->makeResult($topStudent->id, 96);

        $midStudent = $this->makeStudent(['name' => 'Mid Scorer']);
        $this->makeResult($midStudent->id, 91);

        $lowStudent = $this->makeStudent(['name' => 'Below All Tiers']);
        $this->makeResult($lowStudent->id, 80);

        DiscountRule::create([
            'name' => 'Tiered Merit',
            'type' => 'merit',
            'config' => ['tiers' => [
                ['threshold' => 95, 'value' => 50],
                ['threshold' => 90, 'value' => 30],
                ['threshold' => 85, 'value' => 15],
            ]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $topResult = $engine->calculateDiscounts($topStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $midResult = $engine->calculateDiscounts($midStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $lowResult = $engine->calculateDiscounts($lowStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertEquals(1000.00, $topResult[0]['amount']); // 50% tier
        $this->assertEquals(600.00, $midResult[0]['amount']); // 30% tier
        $this->assertEmpty($lowResult); // below every tier
    }

    public function test_merit_without_tiers_still_uses_the_legacy_single_threshold()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent();
        $this->makeResult($student->id, 90);

        DiscountRule::create([
            'name' => 'Legacy Single-Threshold Merit',
            'type' => 'merit',
            'config' => ['threshold_score' => 85, 'percentage' => 20],
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(400.00, $result[0]['amount']);
    }

    public function test_loyalty_discount_bands_by_years_enrolled()
    {
        $tuition = $this->tuition();
        $longTenure = $this->makeStudent(['name' => 'Long Tenure']);
        $longTenure->created_at = now()->subYears(6);
        $longTenure->save();

        $newStudent = $this->makeStudent(['name' => 'Brand New']);

        DiscountRule::create([
            'name' => 'Loyalty Discount',
            'type' => 'loyalty',
            'config' => ['tiers' => [
                ['years' => 5, 'value' => 15],
                ['years' => 3, 'value' => 10],
                ['years' => 1, 'value' => 5],
            ]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $longResult = $engine->calculateDiscounts($longTenure, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $newResult = $engine->calculateDiscounts($newStudent, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertEquals(300.00, $longResult[0]['amount']); // 15% tier
        $this->assertEmpty($newResult); // enrolled today, below every tier
    }

    public function test_referral_discount_applies_only_when_referred_by_is_set()
    {
        $tuition = $this->tuition();
        $referred = $this->makeStudent(['name' => 'Referred Student', 'referred_by_admission_no' => '1234']);
        $notReferred = $this->makeStudent(['name' => 'Direct Admission']);

        DiscountRule::create([
            'name' => 'Referral Bonus',
            'type' => 'referral',
            'config' => ['percentage' => 10],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $referredResult = $engine->calculateDiscounts($referred, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $notReferredResult = $engine->calculateDiscounts($notReferred, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $referredResult);
        $this->assertEquals(200.00, $referredResult[0]['amount']);
        $this->assertEmpty($notReferredResult);
    }

    public function test_special_needs_only_applies_to_flagged_students()
    {
        $tuition = $this->tuition();
        $flagged = $this->makeStudent(['is_special_needs' => true]);
        $regular = $this->makeStudent(['is_special_needs' => false]);

        DiscountRule::create([
            'name' => 'Special Needs Concession',
            'type' => 'special_needs',
            'config' => ['percentage' => 30],
            'priority' => 10,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $flaggedResult = $engine->calculateDiscounts($flagged, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $regularResult = $engine->calculateDiscounts($regular, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $flaggedResult);
        $this->assertEquals(600.00, $flaggedResult[0]['amount']);
        $this->assertEmpty($regularResult);
    }

    private function markAttendance(Student $student, int $presentDays, int $absentDays, string $session = '2026-2027'): void
    {
        for ($i = 0; $i < $presentDays; $i++) {
            \App\Models\Attendance::create(['student_id' => $student->id, 'date' => now()->subDays($i + 1)->toDateString(), 'status' => 'present', 'session' => $session]);
        }
        for ($i = 0; $i < $absentDays; $i++) {
            \App\Models\Attendance::create(['student_id' => $student->id, 'date' => now()->subDays($presentDays + $i + 1)->toDateString(), 'status' => 'absent', 'session' => $session]);
        }
    }

    public function test_attendance_based_tiered_discount_picks_the_highest_qualifying_tier()
    {
        $tuition = $this->tuition();
        $greatAttendance = $this->makeStudent(['name' => '98 Percent']);
        $this->markAttendance($greatAttendance, 98, 2); // 98%

        $poorAttendance = $this->makeStudent(['name' => '60 Percent']);
        $this->markAttendance($poorAttendance, 60, 40); // 60%

        DiscountRule::create([
            'name' => 'Attendance Incentive',
            'type' => 'attendance_based',
            'config' => ['tiers' => [
                ['threshold' => 95, 'value' => 10],
                ['threshold' => 90, 'value' => 5],
            ]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $greatResult = $engine->calculateDiscounts($greatAttendance, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $poorResult = $engine->calculateDiscounts($poorAttendance, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertEquals(200.00, $greatResult[0]['amount']); // 10% tier
        $this->assertEmpty($poorResult); // below every tier
    }

    public function test_applicable_classes_scoping_restricts_a_rule_to_specific_classes()
    {
        $tuition = $this->tuition();
        $eligibleClass = \App\Models\SchoolClass::create(['name' => 'Class 11', 'class_order' => 14, 'is_active' => true]);
        $otherClass = \App\Models\SchoolClass::create(['name' => 'Class 5', 'class_order' => 8, 'is_active' => true]);

        $inClass = $this->makeStudent(['name' => 'In Class 11', 'category' => 'General', 'class_id' => $eligibleClass->id, 'school_class_id' => $eligibleClass->id]);
        $outOfClass = $this->makeStudent(['name' => 'In Class 5', 'category' => 'General', 'class_id' => $otherClass->id, 'school_class_id' => $otherClass->id]);

        DiscountRule::create([
            'name' => 'Class 11 Only Discount',
            'type' => 'category',
            'config' => ['mappings' => ['GENERAL' => 20], 'applicable_classes' => ['Class 11']],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $inClassResult = $engine->calculateDiscounts($inClass, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));
        $outOfClassResult = $engine->calculateDiscounts($outOfClass, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $inClassResult);
        $this->assertEquals(400.00, $inClassResult[0]['amount']);
        $this->assertEmpty($outOfClassResult);
    }

    public function test_early_payment_tiers_picks_the_best_still_open_cutoff()
    {
        $tuition = $this->tuition();
        $student = $this->makeStudent();

        DiscountRule::create([
            'name' => 'Tiered Early Bird',
            'type' => 'early_payment',
            'config' => ['tiers' => [
                ['cutoff_date' => now()->subWeek()->toDateString(), 'value' => 10], // already expired
                ['cutoff_date' => now()->addWeek()->toDateString(), 'value' => 5],  // still open
            ]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $result = (new DiscountEngineService())->calculateDiscounts($student, 'April', '2026-2027', $this->feeItemsFor($tuition, 2000.00));

        $this->assertCount(1, $result);
        $this->assertEquals(100.00, $result[0]['amount']); // only the still-open 5% tier qualifies
    }
}
