<?php

namespace Tests\Feature\FeeFinance;

use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reported directly: the parent Fee Structure page's Quarterly display mode
 * only ever checked for billing_frequency === 'monthly' -- any item that's
 * genuinely billed quarterly (e.g. Pushp Niketan's real Tuition) fell into
 * the "single flat line" fallback meant for one-time items, showing just
 * ONE quarter's amount with no multiplication and silently undercounting
 * the annual total (Nursery showed Rs.16,417 instead of the true
 * Rs.46,618). The exact same flaw existed in the default "Monthly" mode
 * too (it just wasn't visible until a school actually used it). This
 * proves both modes now expand every item into its real per-occurrence
 * charges so the displayed total always equals the true annual amount.
 */
class ParentFeeDisplayFrequencyTest extends TestCase
{
    use RefreshDatabase;

    private function invoke($feeItems, string $displayFrequency): array
    {
        $controller = new \App\Http\Controllers\Parent\ParentDashboardController();
        $method = new \ReflectionMethod($controller, 'buildFeeDisplayRows');
        $method->setAccessible(true);
        return $method->invoke($controller, $feeItems, $displayFrequency);
    }

    public function test_genuinely_quarterly_item_expands_to_all_four_quarters_with_correct_annual_total()
    {
        $tuition = FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $admission = FeeType::create(['name' => 'Admission', 'status' => 'active']);
        $deposit = FeeType::create(['name' => 'Security Deposit', 'status' => 'active']);
        $almanac = FeeType::create(['name' => 'ALMANAC', 'status' => 'active']);

        $structure = FeeStructure::create(['class_name' => 'Nursery', 'academic_year' => '2025-2026', 'frequency' => 'custom', 'status' => 'active']);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 10067, 'billing_frequency' => 'quarterly', 'charge_months' => ['Q1', 'Q2', 'Q3', 'Q4'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $admission->id,
            'amount' => 5000, 'billing_frequency' => 'session_wise_admission', 'charge_months' => ['April'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $deposit->id,
            'amount' => 1200, 'billing_frequency' => 'one_time', 'charge_months' => ['OneTime'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $almanac->id,
            'amount' => 150, 'billing_frequency' => 'yearly', 'charge_months' => ['Annual'],
        ]);

        $feeItems = $structure->fresh(['feeStructureItems.feeType'])->feeStructureItems;

        foreach (['quarterly', 'monthly'] as $displayMode) {
            $rows = $this->invoke($feeItems, $displayMode);
            $tuitionRows = collect($rows)->filter(fn ($r) => str_starts_with($r['label'], 'Tuition'));

            $this->assertCount(4, $tuitionRows, "Mode '{$displayMode}': quarterly Tuition must expand to all 4 quarters.");
            $this->assertEquals(40268, $tuitionRows->sum('amount'));
            $this->assertEquals(46618, collect($rows)->sum('amount'), "Mode '{$displayMode}': total must equal the true annual amount.");
        }
    }

    public function test_monthly_item_stays_twelve_rows_in_monthly_mode_and_groups_into_four_quarters_in_quarterly_mode()
    {
        $tuition = FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $structure = FeeStructure::create(['class_name' => 'Old Style Class', 'academic_year' => '2025-2026', 'frequency' => 'monthly', 'status' => 'active']);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 2000, 'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'],
        ]);
        $feeItems = $structure->fresh(['feeStructureItems.feeType'])->feeStructureItems;

        $monthlyRows = $this->invoke($feeItems, 'monthly');
        $this->assertCount(12, $monthlyRows);
        $this->assertEquals(24000, collect($monthlyRows)->sum('amount'));

        $quarterlyRows = $this->invoke($feeItems, 'quarterly');
        $this->assertCount(4, $quarterlyRows);
        foreach ($quarterlyRows as $row) {
            $this->assertEquals(6000, $row['amount'], 'Each quarter should sum 3 months of Rs.2,000.');
        }
        $this->assertEquals(24000, collect($quarterlyRows)->sum('amount'));
    }
}
