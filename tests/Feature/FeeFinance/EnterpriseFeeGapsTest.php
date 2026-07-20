<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeStructureItemInstallment;
use App\Models\LateFeeRule;
use App\Models\StudentFeeLedger;
use App\Models\StudentFeeAssignment;
use App\Models\DiscountRule;
use App\Services\LateFeeEngineService;
use App\Services\DiscountEngineService;
use App\Services\BulkFeeAssignmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class EnterpriseFeeGapsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        // Setup base tables
        $schema->create('audit_logs', function ($table) {
            $table->id();
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->default(0);
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->default(0);
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
        });

        $schema->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mobile')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('parents', function ($table) {
            $table->id();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_types', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('category')->default('recurring');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('late_fee_rules', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('type')->nullable(); // flat, daily_incremental, slab
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->integer('grace_days')->default(0);
            $table->json('slab_config')->nullable();
            $table->decimal('max_limit', 10, 2)->nullable();
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_structure_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('billing_frequency')->nullable();
            $table->json('charge_months')->nullable();
            $table->unsignedBigInteger('late_fee_rule_id')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_structure_item_installments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_item_id')->nullable();
            $table->string('month')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamps();
        });

        $schema->create('student_fee_assignments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();
        });

        $schema->create('student_fee_ledgers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('debit', 10, 2)->default(0.00);
            $table->decimal('credit', 10, 2)->default(0.00);
            $table->decimal('running_balance', 10, 2)->default(0.00);
            $table->string('academic_year')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('unpaid_amount', 10, 2)->default(0.00);
            $table->timestamps();
        });

        $schema->create('discount_rules', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->json('config')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('student_discounts_applied', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('discount_rule_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('month')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        // Register StudentObserver
        Student::observe(\App\Observers\StudentObserver::class);
    }

    /** @test */
    public function test_late_fee_rule_calculations()
    {
        $engine = new LateFeeEngineService();

        // 1. Flat rule test
        $flatRule = LateFeeRule::create([
            'name' => 'Flat Penalty',
            'type' => 'flat',
            'amount' => 100.00,
            'grace_days' => 2
        ]);

        $due = Carbon::parse('2026-06-10');
        // Within grace period (June 11) -> Should be 0.00
        $this->assertEquals(0.00, $engine->calculatePenalty($flatRule, $due, '2026-06-11'));
        // Breach grace period (June 13) -> Should be 100.00
        $this->assertEquals(100.00, $engine->calculatePenalty($flatRule, $due, '2026-06-13'));

        // 2. Daily incremental test
        $dailyRule = LateFeeRule::create([
            'name' => 'Daily Penalty',
            'type' => 'daily_incremental',
            'amount' => 20.00,
            'grace_days' => 0,
            'max_limit' => 200.00
        ]);

        // 5 days late -> 5 * 20 = 100.00
        $this->assertEquals(100.00, $engine->calculatePenalty($dailyRule, $due, '2026-06-15'));
        // 15 days late -> capped at 200.00 max limit
        $this->assertEquals(200.00, $engine->calculatePenalty($dailyRule, $due, '2026-06-25'));

        // 3. Slab rule test
        $slabRule = LateFeeRule::create([
            'name' => 'Slab Penalty',
            'type' => 'slab',
            'grace_days' => 0,
            'slab_config' => [
                ['days' => 5, 'amount' => 50.00],
                ['days' => 15, 'amount' => 150.00]
            ]
        ]);

        // 3 days late -> matches <= 5 slab -> 50.00
        $this->assertEquals(50.00, $engine->calculatePenalty($slabRule, $due, '2026-06-13'));
        // 10 days late -> matches <= 15 slab -> 150.00
        $this->assertEquals(150.00, $engine->calculatePenalty($slabRule, $due, '2026-06-20'));
        // 20 days late -> breaches all slabs -> fallback to last slab amount -> 150.00
        $this->assertEquals(150.00, $engine->calculatePenalty($slabRule, $due, '2026-06-30'));
    }

    /** @test */
    public function test_variable_installment_amounts_are_billed_correctly()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $schoolClass = \App\Models\SchoolClass::create(['name' => 'Grade 10']);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Grade 10',
            'academic_year' => '2026-27',
            'frequency' => 'custom'
        ]);

        $item = FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 3000.00,
            'billing_frequency' => 'custom'
        ]);

        // Set installments: July = ₹1000, January = ₹2000
        FeeStructureItemInstallment::create([
            'fee_structure_item_id' => $item->id,
            'month' => 'July',
            'amount' => 1000.00
        ]);

        FeeStructureItemInstallment::create([
            'fee_structure_item_id' => $item->id,
            'month' => 'January',
            'amount' => 2000.00
        ]);

        $student = Student::create(['name' => 'Installment Student', 'class_id' => $schoolClass->id]);

        // Trigger assignment
        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);

        // Verify ledger entries exist with custom variable amounts for the custom months
        $ledgers = StudentFeeLedger::where('student_id', $student->id)->orderBy('date', 'asc')->get();
        $this->assertCount(2, $ledgers);

        $julyLedger = $ledgers->first(fn($ledger) => $ledger->date->format('Y-m-d') === '2026-07-01');
        $this->assertNotNull($julyLedger);
        $this->assertEquals(1000.00, $julyLedger->debit);

        $janLedger = $ledgers->first(fn($ledger) => $ledger->date->format('Y-m-d') === '2027-01-01');
        $this->assertNotNull($janLedger);
        $this->assertEquals(2000.00, $janLedger->debit);
    }

    /** @test */
    public function test_session_based_admission_dues_generation()
    {
        $admissionFeeType = FeeType::create(['name' => 'Admission Fee']);
        $schoolClass = \App\Models\SchoolClass::create(['name' => 'Grade 11']);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Grade 11',
            'academic_year' => '2026-27',
            'frequency' => 'custom'
        ]);

        $item = FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $admissionFeeType->id,
            'amount' => 5000.00,
            'billing_frequency' => 'session_wise_admission',
            'charge_months' => ['April']
        ]);

        $student = Student::create(['name' => 'Admission Student', 'class_id' => $schoolClass->id]);

        // First assignment -> Should bill 5000.00
        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);
        $this->assertEquals(5000.00, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));

        // Assign again -> Should NOT bill admission fee again since already billed in this session
        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);
        $this->assertEquals(5000.00, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));
    }

    /** @test */
    public function test_status_based_generation_prunes_withdrawn_student_dues()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $schoolClass = \App\Models\SchoolClass::create(['name' => 'Grade 12']);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Grade 12',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);

        $item = FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 1000.00,
            'billing_frequency' => 'monthly',
            'charge_months' => ['April', 'May', 'July', 'August', 'September', 'October']
        ]);

        $student = Student::create(['name' => 'Withdrawn Student', 'class_id' => $schoolClass->id]);

        // Assign structure -> Generates 6 debits
        BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);
        $this->assertEquals(6, StudentFeeLedger::where('student_id', $student->id)->count());

        // Soft delete the student simulating a mid-year withdrawal today (e.g. June 25, 2026)
        // This triggers StudentObserver::deleted which prunes future dues (July, Aug, Sept, Oct)
        \Carbon\Carbon::setTestNow('2026-06-25');
        try {
            $student->delete();
        } finally {
            \Carbon\Carbon::setTestNow();
        }

        // Remaining debits should only be April and May (2 debits)
        $remainingDebits = StudentFeeLedger::where('student_id', $student->id)->get();
        $this->assertCount(2, $remainingDebits);
        $this->assertEquals(2000.00, StudentFeeLedger::where('student_id', $student->id)->sum('debit'));
    }

    /** @test */
    public function test_sibling_discount_limits_applicability_to_configured_fee_types()
    {
        $tuitionType = FeeType::create(['name' => 'Tuition Fee']);
        $sportsType = FeeType::create(['name' => 'Sports Fee']);

        $s1 = Student::create(['name' => 'Sibling One', 'father_name' => 'Pater Familias', 'mobile' => '9998887776']);
        $s2 = Student::create(['name' => 'Sibling Two', 'father_name' => 'Pater Familias', 'mobile' => '9998887776']);

        // Sibling discount rule configured specifically for Tuition Fee
        $rule = DiscountRule::create([
            'name' => 'Tuition Concession',
            'type' => 'sibling',
            'config' => [
                'applicable_fee_types' => ['Tuition Fee'],
                'rates' => [0, 50] // 50% discount for second Sibling
            ],
            'priority' => 1,
            'is_active' => true
        ]);

        $feeItems = [
            ['fee_type_id' => $tuitionType->id, 'amount' => 2000.00],
            ['fee_type_id' => $sportsType->id, 'amount' => 500.00]
        ];

        $engine = new DiscountEngineService();
        $discounts = $engine->calculateDiscounts($s2, 'July', '2026-27', $feeItems);

        // Discount should be 50% of 2000 (Tuition) = 1000.00, ignoring Sports Fee amount (500)
        $this->assertCount(1, $discounts);
        $this->assertEquals(1000.00, $discounts[0]['amount']);
    }
}
