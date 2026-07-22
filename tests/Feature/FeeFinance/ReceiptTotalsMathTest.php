<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentDiscountApplied;
use App\Models\User;
use App\Services\FinanceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: FinanceCalculationService::getReceiptTotals() -- used
 * on every printed/emailed receipt -- hardcoded a x12/x4/x2 multiplier
 * regardless of the fee item's real charge_months, and never subtracted
 * approved discounts. Both meant a receipt's "remaining fee" could show a
 * balance that never reached zero even after the student paid everything
 * they were actually billed.
 */
class ReceiptTotalsMathTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Receipt Math Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887700', 'address' => 'Somewhere',
        ]);
    }

    public function test_totals_honor_custom_charge_months_instead_of_fixed_multiplier()
    {
        $student = $this->makeStudent();
        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Class 8', 'academic_year' => '2026-2027',
            'frequency' => 'monthly', 'status' => 'active',
        ]);
        // Only 10 charge months (school excludes a 2-month summer break) --
        // the old code would have multiplied by a fixed 12 regardless.
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 1000, 'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January'],
        ]);

        $collector = User::factory()->create();
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-MATH-001', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 1000, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 1000,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $collector->id,
        ]);
        FeeCollectionItem::create(['fee_collection_id' => $collection->id, 'fee_type_id' => $tuitionType->id, 'amount' => 1000]);

        $totals = FinanceCalculationService::getReceiptTotals($collection);

        // 10 months x 1000, not 12 x 1000.
        $this->assertEquals(10000, $totals['recurringTotal']);
        $this->assertEquals(10000, $totals['totalYearlyFee']);
    }

    public function test_totals_subtract_approved_discount_so_fully_paid_discounted_student_shows_zero_remaining()
    {
        $student = $this->makeStudent();
        $tuitionType = FeeType::create(['name' => 'Tuition Fee', 'status' => 'active']);

        $structure = FeeStructure::create([
            'class_name' => 'Class 9', 'academic_year' => '2026-2027',
            'frequency' => 'yearly', 'status' => 'active',
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 5000, 'billing_frequency' => 'one_time', 'charge_months' => ['Annual'],
        ]);

        // Student has a 1000 sibling discount approved for this year.
        $discountRule = \App\Models\DiscountRule::create(['name' => 'Sibling Discount', 'type' => 'sibling', 'is_active' => true]);
        StudentDiscountApplied::create([
            'student_id' => $student->id, 'discount_rule_id' => $discountRule->id, 'fee_type_id' => $tuitionType->id,
            'amount' => 1000, 'month' => 'Annual', 'academic_year' => '2026-2027',
        ]);

        $collector = User::factory()->create();
        // Student pays exactly the discounted amount (5000 - 1000 = 4000).
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-MATH-002', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 4000, 'discount' => 1000, 'late_fine' => 0, 'final_amount' => 4000,
            'payment_date' => today(), 'payment_mode' => 'cash', 'collected_by' => $collector->id,
        ]);
        FeeCollectionItem::create(['fee_collection_id' => $collection->id, 'fee_type_id' => $tuitionType->id, 'amount' => 4000]);

        $totals = FinanceCalculationService::getReceiptTotals($collection);

        $this->assertEquals(4000, $totals['totalYearlyFee']); // 5000 - 1000 discount
        $this->assertEquals(4000, $totals['totalPaidTillNow']);
        $this->assertEquals(0, $totals['remainingFee']);
    }
}
