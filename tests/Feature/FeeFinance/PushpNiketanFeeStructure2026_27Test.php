<?php

namespace Tests\Feature\FeeFinance;

use App\Models\AcademicSession;
use App\Models\AdvanceRebateRule;
use App\Models\FeeCollection;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeType;
use App\Models\LateFeeRule;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentAdvanceRebate;
use App\Models\StudentFeeLedger;
use App\Models\User;
use App\Services\LateFeeEngineService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Real-world regression test built from Pushp Niketan School, Dhampur's
 * actual "FEES STRUCTURE FOR THE SESSION: 2026-27" circular
 * (By UDIT SIR/UDIT FEES STRUCTURE FOR 2026-27.xlsx). Uses Class 3's row
 * exactly as published: Quarterly Tuition Rs.11,517, Almanac Rs.150 (Q1
 * only), Robotics Rs.350/quarter, Admission Fee Rs.5,000 (Nur-8th tier),
 * Security Deposit Rs.1,200, Late Fee Rs.100/month within the quarter,
 * 5% advance-payment discount on Tuition only. Nothing here is
 * Pushp-Niketan-specific in the engine itself (billing_frequency,
 * AdvanceRebateRule, LateFeeRule are all generic, reusable for any
 * school) -- this test just proves the generic engine reproduces this
 * real school's published numbers exactly.
 */
class PushpNiketanFeeStructure2026_27Test extends TestCase
{
    use RefreshDatabase;

    private const ACADEMIC_YEAR = '2026-2027';

    private function buildClass3Structure(): array
    {
        $class = SchoolClass::create(['name' => 'Class 3', 'class_order' => 6, 'is_active' => true]);

        $tuition = FeeType::firstOrCreate(['name' => 'Tuition'], ['status' => 'active']);
        $almanac = FeeType::firstOrCreate(['name' => 'ALMANAC'], ['status' => 'active']);
        $robotics = FeeType::firstOrCreate(['name' => 'Robotics/STEM'], ['status' => 'active']);
        $admission = FeeType::firstOrCreate(['name' => 'Admission'], ['status' => 'active']);
        $deposit = FeeType::firstOrCreate(['name' => 'Security Deposit'], ['status' => 'active']);

        $lateFeeRule = LateFeeRule::create([
            'name' => 'Quarterly Late Fee - Rs.100/month',
            'type' => 'slab',
            'amount' => 0,
            'grace_days' => 0,
            'slab_config' => [
                ['days' => 30, 'amount' => 100],
                ['days' => 60, 'amount' => 200],
            ],
            'max_limit' => 200,
        ]);

        $structure = FeeStructure::create([
            'class_name' => 'Class 3', 'academic_year' => self::ACADEMIC_YEAR,
            'frequency' => 'custom', 'status' => 'active',
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $tuition->id,
            'amount' => 11517, 'due_day' => 30, 'billing_frequency' => 'quarterly',
            'charge_months' => ['Q1', 'Q2', 'Q3', 'Q4'], 'late_fee_rule_id' => $lateFeeRule->id,
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $almanac->id,
            'amount' => 150, 'billing_frequency' => 'yearly', 'charge_months' => ['Annual'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $robotics->id,
            'amount' => 350, 'due_day' => 30, 'billing_frequency' => 'quarterly',
            'charge_months' => ['Q1', 'Q2', 'Q3', 'Q4'], 'late_fee_rule_id' => $lateFeeRule->id,
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $admission->id,
            'amount' => 5000, 'billing_frequency' => 'session_wise_admission', 'charge_months' => ['April'],
        ]);
        FeeStructureItem::create([
            'fee_structure_id' => $structure->id, 'fee_type_id' => $deposit->id,
            'amount' => 1200, 'billing_frequency' => 'one_time', 'charge_months' => ['OneTime'],
        ]);

        return compact('class', 'structure', 'tuition', 'almanac', 'robotics', 'admission', 'deposit', 'lateFeeRule');
    }

    public function test_new_admission_is_billed_exactly_per_the_published_circular()
    {
        $fixtures = $this->buildClass3Structure();
        $class = $fixtures['class'];
        $structure = $fixtures['structure'];

        $session = AcademicSession::create([
            'name' => self::ACADEMIC_YEAR, 'code' => self::ACADEMIC_YEAR,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'Class 3 New Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2018-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887799', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
            'admission_session_id' => $session->id,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        $ledger = StudentFeeLedger::where('student_id', $student->id)->get();

        // Tuition: 4 quarters x Rs.11,517 = Rs.46,068 (matches document's
        // "ANNUAL TUITION FEES" for Class 3RD).
        $this->assertEquals(46068, $ledger->where('fee_type_id', $fixtures['tuition']->id)->sum('debit'));
        $this->assertEquals(4, $ledger->where('fee_type_id', $fixtures['tuition']->id)->count());

        // Robotics: 4 quarters x Rs.350 = Rs.1,400 ("ROBOTICS FEES" column).
        $this->assertEquals(1400, $ledger->where('fee_type_id', $fixtures['robotics']->id)->sum('debit'));

        // Almanac: once, Rs.150.
        $this->assertEquals(150, $ledger->where('fee_type_id', $fixtures['almanac']->id)->sum('debit'));
        $this->assertEquals(1, $ledger->where('fee_type_id', $fixtures['almanac']->id)->count());

        // Recurring total matches the document's "TOTAL ANNUAL FEES" for
        // Class 3RD exactly: 46068 + 150 + 1400 = 47618.
        $recurringTotal = $ledger->whereIn('fee_type_id', [
            $fixtures['tuition']->id, $fixtures['almanac']->id, $fixtures['robotics']->id,
        ])->sum('debit');
        $this->assertEquals(47618, $recurringTotal);

        // One-time items, only because this is a genuinely new admission.
        $this->assertEquals(5000, $ledger->where('fee_type_id', $fixtures['admission']->id)->sum('debit'));
        $this->assertEquals(1200, $ledger->where('fee_type_id', $fixtures['deposit']->id)->sum('debit'));
    }

    public function test_continuing_student_promoted_into_the_structure_skips_admission_fee()
    {
        $fixtures = $this->buildClass3Structure();
        $class = $fixtures['class'];
        $structure = $fixtures['structure'];

        // Admitted in a PRIOR session -- not new for 2026-2027.
        $priorSession = AcademicSession::create([
            'name' => '2025-2026', 'code' => '2025-2026',
            'start_date' => '2025-04-01', 'end_date' => '2026-03-31',
            'is_current' => false, 'is_active' => true,
        ]);
        AcademicSession::create([
            'name' => self::ACADEMIC_YEAR, 'code' => self::ACADEMIC_YEAR,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'Class 3 Continuing Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2017-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887788', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
            'admission_session_id' => $priorSession->id,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        $ledger = StudentFeeLedger::where('student_id', $student->id)->get();

        // Recurring items still billed normally.
        $this->assertEquals(46068, $ledger->where('fee_type_id', $fixtures['tuition']->id)->sum('debit'));
        $this->assertEquals(150, $ledger->where('fee_type_id', $fixtures['almanac']->id)->sum('debit'));

        // Admission Fee is NOT billed -- this student isn't a new admission
        // for the 2026-2027 session.
        $this->assertEquals(0, $ledger->where('fee_type_id', $fixtures['admission']->id)->sum('debit'));
    }

    public function test_late_fee_matches_the_circulars_rs100_per_month_rule()
    {
        $fixtures = $this->buildClass3Structure();
        $engine = new LateFeeEngineService();
        $rule = $fixtures['lateFeeRule'];

        // Due 30 Jun (quarter's first-month 30th). Paid on time -> no fine.
        $this->assertEquals(0.00, $engine->calculatePenalty($rule, '2026-06-30', '2026-06-30', 11517));

        // 1 day late already breaches the slab boundary (grace_days = 0) -> Rs.100.
        $this->assertEquals(100.00, $engine->calculatePenalty($rule, '2026-06-30', '2026-07-01', 11517));

        // ~1 month late (within 31-60 days) -> still Rs.100.
        $this->assertEquals(100.00, $engine->calculatePenalty($rule, '2026-06-30', '2026-07-25', 11517));

        // ~2 months late (31-60 days elapsed... i.e. past day 60) -> Rs.200.
        $this->assertEquals(200.00, $engine->calculatePenalty($rule, '2026-06-30', '2026-08-31', 11517));
    }

    public function test_advance_rebate_gives_5_percent_off_tuition_only_not_robotics_or_almanac()
    {
        $fixtures = $this->buildClass3Structure();
        $class = $fixtures['class'];
        $structure = $fixtures['structure'];

        $session = AcademicSession::create([
            'name' => self::ACADEMIC_YEAR, 'code' => self::ACADEMIC_YEAR,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
            'is_current' => true, 'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'Class 3 Advance Payer', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2018-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => '9998887700', 'address' => 'Somewhere',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
            'admission_session_id' => $session->id,
        ]);

        \App\Services\BulkFeeAssignmentService::bulkAssign($structure, [$student->id]);

        AdvanceRebateRule::create([
            'name' => '5% Advance Payment Discount - Tuition Only',
            'type' => 'percent',
            'value' => 5,
            'applicable_fee_type_ids' => [$fixtures['tuition']->id],
            'cutoff_month_day' => '04-30',
            'min_coverage' => 'full_session',
            'is_active' => true,
        ]);

        // Mark only the Tuition debits as fully paid (Robotics/Almanac
        // remain outstanding -- shouldn't matter, rule is scoped to Tuition).
        StudentFeeLedger::where('student_id', $student->id)
            ->where('fee_type_id', $fixtures['tuition']->id)
            ->update(['unpaid_amount' => 0]);

        $collector = User::factory()->create();
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-PN-1', 'student_id' => $student->id, 'fee_structure_id' => $structure->id,
            'total_amount' => 46068.00, 'discount' => 0, 'late_fine' => 0, 'final_amount' => 46068.00,
            'payment_date' => '2026-04-15', 'payment_mode' => 'cash', 'collected_by' => $collector->id,
        ]);
        \App\Services\AdvanceRebateService::evaluateAndApply($collection->fresh());

        $snapshot = StudentAdvanceRebate::where('student_id', $student->id)->first();
        $this->assertNotNull($snapshot);
        // 5% of Rs.46,068 (Tuition only, not the Rs.1,400 Robotics or Rs.150 Almanac).
        $this->assertEquals(2303.40, $snapshot->rebate_amount);
    }
}
