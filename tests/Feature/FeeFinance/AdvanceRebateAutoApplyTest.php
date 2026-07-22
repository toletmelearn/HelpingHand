<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AdvanceRebateRule;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Models\StudentFeeAssignment;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdvanceRebateService::evaluateAndApply(), triggered from
 * FeeCollection::booted()'s created hook.
 */
class AdvanceRebateAutoApplyTest extends TestCase
{
    use RefreshDatabase;

    private ?int $collectorId = null;

    private function collectedBy(): int
    {
        return $this->collectorId ??= User::factory()->create()->id;
    }

    private function makeStudentWithFullSessionTuition(string $academicYear = '2026-2027'): array
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);

        $student = Student::create([
            'name' => 'Rebate Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);

        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => $academicYear, 'frequency' => 'yearly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => $academicYear]);
        $item = FeeStructureItem::create(['fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id, 'amount' => 12000.00]);

        // Single 12,000 debit representing the full session's tuition.
        // referenceId must be a real FeeStructureItem id -- LedgerService's
        // createEntryInstance() resolves academic_year/fee_type_id by
        // looking that row up, which AdvanceRebateService's query depends on.
        LedgerService::postDebit($student->id, "{$this->yearStart($academicYear)}-04-01", 'Tuition - Annual', 'fee_structure_item', $item->id, 12000.00);

        return [$student, $tuition, $structure];
    }

    private function yearStart(string $academicYear): string
    {
        return substr($academicYear, 0, 4);
    }

    public function test_percent_rebate_auto_applies_when_full_session_paid_before_cutoff()
    {
        [$student, $tuition, $structure] = $this->makeStudentWithFullSessionTuition();

        AdvanceRebateRule::create([
            'name' => '10% Advance Rebate',
            'type' => 'percent',
            'value' => 10,
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-1',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 12000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 12000.00,
            'payment_date' => '2026-04-10',
            'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);

        // Simulate full allocation having cleared the debit (the real
        // payment/allocation engine sets unpaid_amount; this test isolates
        // AdvanceRebateService's own eligibility logic rather than
        // exercising the full allocation engine).
        \App\Models\StudentFeeLedger::where('student_id', $student->id)->update(['unpaid_amount' => 0]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertNotNull($snapshot);
        $this->assertEquals(1200.00, $snapshot->rebate_amount);
        $this->assertEquals('applied', $snapshot->status);
    }

    public function test_fixed_rebate_is_capped_at_the_applicable_total()
    {
        [$student, $tuition, $structure] = $this->makeStudentWithFullSessionTuition();

        AdvanceRebateRule::create([
            'name' => 'Fixed Rebate Over Total',
            'type' => 'fixed',
            'value' => 50000.00, // exceeds the 12000 total on purpose
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        \App\Models\StudentFeeLedger::where('student_id', $student->id)->update(['unpaid_amount' => 0]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-2',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 12000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 12000.00,
            'payment_date' => '2026-04-10',
            'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertEquals(12000.00, $snapshot->rebate_amount);
    }

    public function test_no_rebate_when_paid_after_cutoff()
    {
        [$student, $tuition, $structure] = $this->makeStudentWithFullSessionTuition();

        AdvanceRebateRule::create([
            'name' => 'Early Bird Only',
            'type' => 'percent',
            'value' => 10,
            'cutoff_month_day' => '04-15',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        \App\Models\StudentFeeLedger::where('student_id', $student->id)->update(['unpaid_amount' => 0]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-3',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 12000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 12000.00,
            'payment_date' => '2026-05-01', // after the 04-15 cutoff
            'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $this->assertEquals(0, StudentAdvanceRebate::where('student_id', $student->id)->count());
    }

    public function test_no_rebate_when_full_session_not_fully_paid()
    {
        [$student, $tuition, $structure] = $this->makeStudentWithFullSessionTuition();

        AdvanceRebateRule::create([
            'name' => 'Full Coverage Required',
            'type' => 'percent',
            'value' => 10,
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        // unpaid_amount left non-zero -- session not fully covered.
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-4',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 6000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 6000.00,
            'payment_date' => '2026-04-10',
            'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $this->assertEquals(0, StudentAdvanceRebate::where('student_id', $student->id)->count());
    }

    public function test_rebate_only_scopes_to_applicable_fee_heads()
    {
        $tuition = FeeType::where('name', 'Tuition')->first() ?? FeeType::create(['name' => 'Tuition', 'status' => 'active']);
        $hostel = FeeType::where('name', 'Hostel')->first() ?? FeeType::create(['name' => 'Hostel', 'status' => 'active']);

        $student = Student::create([
            'name' => 'Scoped Rebate Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
        ]);
        $structure = FeeStructure::create(['class_name' => 'Class 1', 'academic_year' => '2026-2027', 'frequency' => 'yearly', 'status' => 'active']);
        StudentFeeAssignment::create(['student_id' => $student->id, 'fee_structure_id' => $structure->id, 'academic_year' => '2026-2027']);
        $tuitionItem = FeeStructureItem::create(['fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id, 'amount' => 10000.00]);
        $hostelItem = FeeStructureItem::create(['fee_structure_id' => $structure->id, 'fee_type_id' => $hostel->id, 'amount' => 20000.00]);

        LedgerService::postDebit($student->id, '2026-04-01', 'Tuition - Annual', 'fee_structure_item', $tuitionItem->id, 10000.00);
        LedgerService::postDebit($student->id, '2026-04-01', 'Hostel - Annual', 'fee_structure_item', $hostelItem->id, 20000.00);

        AdvanceRebateRule::create([
            'name' => 'Tuition Only Rebate',
            'type' => 'percent',
            'value' => 10,
            'applicable_fee_type_ids' => [$tuition->id],
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        \App\Models\StudentFeeLedger::where('student_id', $student->id)->where('fee_type_id', $tuition->id)->update(['unpaid_amount' => 0]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-5',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 10000.00,
            'discount' => 0,
            'late_fine' => 0,
            'final_amount' => 10000.00,
            'payment_date' => '2026-04-10',
            'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertNotNull($snapshot);
        // Only the 10,000 tuition debit counts -- not the 20,000 hostel debit.
        $this->assertEquals(1000.00, $snapshot->rebate_amount);
    }

    public function test_rebate_does_not_double_apply_on_a_second_payment_same_session()
    {
        [$student, $tuition, $structure] = $this->makeStudentWithFullSessionTuition();

        AdvanceRebateRule::create([
            'name' => 'Idempotent Rebate',
            'type' => 'percent',
            'value' => 10,
            'cutoff_month_day' => '06-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        \App\Models\StudentFeeLedger::where('student_id', $student->id)->update(['unpaid_amount' => 0]);

        $first = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-6A', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 12000.00, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 12000.00,
            'payment_date' => '2026-04-10', 'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($first->fresh());

        $second = FeeCollection::create([
            'receipt_no' => 'RCPT-REBATE-6B', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 500.00, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 500.00,
            'payment_date' => '2026-04-20', 'payment_mode' => 'cash', 'collected_by' => $this->collectedBy(),
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($second->fresh());

        $this->assertEquals(1, StudentAdvanceRebate::where('student_id', $student->id)->count());
    }
}
