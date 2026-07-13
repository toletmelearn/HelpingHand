<?php

namespace Tests\Feature\FeeFinance;

use App\Models\DiscountRule;
use App\Models\Family;
use App\Models\FeeType;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatus;
use App\Services\DiscountEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FamilySiblingDiscountTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(array $overrides = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Family Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ], $overrides));
    }

    private function feeItemsFor(FeeType $tuition, float $amount = 2000.00): array
    {
        return [['fee_type_id' => $tuition->id, 'amount' => $amount]];
    }

    public function test_rank_by_age_gives_elder_full_price_and_younger_the_discount()
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $family = Family::create(['guardian_name' => 'Age Rank Guardian', 'mobile' => '9100000001']);

        $elder = $this->makeStudent(['name' => 'Elder', 'date_of_birth' => '2012-01-01', 'family_id' => $family->id]);
        $younger = $this->makeStudent(['name' => 'Younger', 'date_of_birth' => '2016-01-01', 'family_id' => $family->id]);

        DiscountRule::create([
            'name' => 'Family Sibling Age Rank',
            'type' => 'family_sibling',
            'config' => ['rank_by' => 'age', 'rates' => [0, 25]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $elderDiscounts = $engine->calculateDiscounts($elder, 'April', '2026-2027', $this->feeItemsFor($tuition));
        $youngerDiscounts = $engine->calculateDiscounts($younger, 'April', '2026-2027', $this->feeItemsFor($tuition));

        $this->assertEmpty($elderDiscounts);
        $this->assertCount(1, $youngerDiscounts);
        $this->assertEquals(500.00, $youngerDiscounts[0]['amount']);
    }

    public function test_rank_by_class_gives_senior_class_full_price_and_junior_class_the_discount()
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $family = Family::create(['guardian_name' => 'Class Rank Guardian', 'mobile' => '9100000002']);

        $seniorClass = SchoolClass::create(['name' => 'Class 8', 'class_order' => 8, 'is_active' => true]);
        $juniorClass = SchoolClass::create(['name' => 'Class 2', 'class_order' => 2, 'is_active' => true]);

        $seniorStudent = $this->makeStudent(['name' => 'Senior', 'family_id' => $family->id, 'school_class_id' => $seniorClass->id, 'class_id' => $seniorClass->id]);
        $juniorStudent = $this->makeStudent(['name' => 'Junior', 'family_id' => $family->id, 'school_class_id' => $juniorClass->id, 'class_id' => $juniorClass->id]);

        DiscountRule::create([
            'name' => 'Family Sibling Class Rank',
            'type' => 'family_sibling',
            'config' => ['rank_by' => 'class', 'rates' => [0, 30]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $seniorDiscounts = $engine->calculateDiscounts($seniorStudent, 'April', '2026-2027', $this->feeItemsFor($tuition));
        $juniorDiscounts = $engine->calculateDiscounts($juniorStudent, 'April', '2026-2027', $this->feeItemsFor($tuition));

        $this->assertEmpty($seniorDiscounts);
        $this->assertCount(1, $juniorDiscounts);
        $this->assertEquals(600.00, $juniorDiscounts[0]['amount']);
    }

    public function test_youngest_child_only_mode_discounts_only_the_last_ranked_sibling()
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $family = Family::create(['guardian_name' => 'Youngest Rule Guardian', 'mobile' => '9100000003']);

        $first = $this->makeStudent(['name' => 'First', 'date_of_birth' => '2010-01-01', 'family_id' => $family->id]);
        $second = $this->makeStudent(['name' => 'Second', 'date_of_birth' => '2013-01-01', 'family_id' => $family->id]);
        $third = $this->makeStudent(['name' => 'Third', 'date_of_birth' => '2017-01-01', 'family_id' => $family->id]);

        DiscountRule::create([
            'name' => 'Youngest Child Rule',
            'type' => 'family_sibling',
            'config' => ['rank_by' => 'age', 'youngest_child_only' => true, 'percentage' => 50],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();
        $this->assertEmpty($engine->calculateDiscounts($first, 'April', '2026-2027', $this->feeItemsFor($tuition)));
        $this->assertEmpty($engine->calculateDiscounts($second, 'April', '2026-2027', $this->feeItemsFor($tuition)));

        $thirdDiscounts = $engine->calculateDiscounts($third, 'April', '2026-2027', $this->feeItemsFor($tuition));
        $this->assertCount(1, $thirdDiscounts);
        $this->assertEquals(1000.00, $thirdDiscounts[0]['amount']);
    }

    public function test_tcd_sibling_is_excluded_from_live_ranking()
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $family = Family::create(['guardian_name' => 'TC Exclusion Guardian', 'mobile' => '9100000004']);

        $elder = $this->makeStudent(['name' => 'TCd Elder', 'date_of_birth' => '2011-01-01', 'family_id' => $family->id]);
        $middle = $this->makeStudent(['name' => 'Remaining Middle', 'date_of_birth' => '2014-01-01', 'family_id' => $family->id]);
        $youngest = $this->makeStudent(['name' => 'Remaining Youngest', 'date_of_birth' => '2018-01-01', 'family_id' => $family->id]);

        StudentStatus::create([
            'student_id' => $elder->id,
            'status' => 'tc_issued',
            'status_date' => now()->toDateString(),
        ]);

        DiscountRule::create([
            'name' => 'Family Sibling With TC Exclusion',
            'type' => 'family_sibling',
            'config' => ['rank_by' => 'age', 'rates' => [0, 20, 40]],
            'priority' => 5,
            'is_active' => true,
        ]);

        $engine = new DiscountEngineService();

        // With the TC'd elder excluded, $middle becomes rank 1 (0%) and
        // $youngest becomes rank 2 (20%) -- not rank 3 (40%), which would
        // only apply if the TC'd sibling were still counted.
        $this->assertEmpty($engine->calculateDiscounts($middle, 'April', '2026-2027', $this->feeItemsFor($tuition)));

        $youngestDiscounts = $engine->calculateDiscounts($youngest, 'April', '2026-2027', $this->feeItemsFor($tuition));
        $this->assertCount(1, $youngestDiscounts);
        $this->assertEquals(400.00, $youngestDiscounts[0]['amount']);
    }
}
