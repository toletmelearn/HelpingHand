<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeCollection;
use App\Models\DiscountRule;
use App\Models\StudentDiscountApplied;
use App\Models\StudentFeeLedger;
use App\Models\StudentFeeAssignment;
use App\Models\SchoolClass;
use App\Services\DiscountEngineService;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AdvancedFeeFeaturesTest extends TestCase
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
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('mobile')->nullable();
            $table->string('phone')->nullable();
            $table->string('category')->nullable();
            $table->string('guardian_name')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('parents', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('admission_number')->nullable();
            $table->string('password')->nullable();
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

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->integer('class_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('student_fee_assignments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->string('academic_year')->nullable();
            $table->timestamps();
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

        $schema->create('fee_collections', function ($table) {
            $table->bigIncrements('id');
            $table->string('receipt_no')->unique()->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('late_fine', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Setup discount tables
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

        // Setup ledger table
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
    }

    /** @test */
    public function test_discount_engine_calculates_correct_sibling_discount()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);

        $s1 = Student::create(['name' => 'John Doe', 'father_name' => 'Mark Doe', 'mobile' => '9876543210']);
        $s2 = Student::create(['name' => 'Jane Doe', 'father_name' => 'Mark Doe', 'mobile' => '9876543210']);

        $rule = DiscountRule::create([
            'name' => 'Sibling Discount',
            'type' => 'sibling',
            'config' => [
                'fee_type' => 'Tuition Fee',
                'rates' => [0, 10, 20]
            ],
            'priority' => 10,
            'is_active' => true
        ]);

        $feeItems = [
            ['fee_type_id' => $tuitionFeeType->id, 'amount' => 1000.00]
        ];

        $engine = new DiscountEngineService();
        
        // s1 is the first child (index 0) -> Should be 0%
        $discountsS1 = $engine->calculateDiscounts($s1, 'June', '2026-27', $feeItems);
        $this->assertEmpty($discountsS1);

        // s2 is the second child (index 1) -> Should be 10% of 1000 = 100
        $discountsS2 = $engine->calculateDiscounts($s2, 'June', '2026-27', $feeItems);
        $this->assertCount(1, $discountsS2);
        $this->assertEquals(100.00, $discountsS2[0]['amount']);
    }

    /** @test */
    public function test_discount_engine_calculates_correct_category_scholarship()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);

        $student = Student::create(['name' => 'Alex ST', 'category' => 'ST']);

        $rule = DiscountRule::create([
            'name' => 'Category Scholarship',
            'type' => 'category',
            'config' => [
                'fee_type' => 'Tuition Fee',
                'mappings' => [
                    'ST' => 30,
                    'SC' => 20
                ]
            ],
            'priority' => 10,
            'is_active' => true
        ]);

        $feeItems = [
            ['fee_type_id' => $tuitionFeeType->id, 'amount' => 2000.00]
        ];

        $engine = new DiscountEngineService();
        $discounts = $engine->calculateDiscounts($student, 'June', '2026-27', $feeItems);

        $this->assertCount(1, $discounts);
        $this->assertEquals(600.00, $discounts[0]['amount']); // 30% of 2000
    }

    /** @test */
    public function test_ledger_service_calculates_correct_running_balance_and_outstanding_balance()
    {
        $student = Student::create(['name' => 'John Ledger']);

        // Post a debit of 1500
        LedgerService::postDebit($student->id, '2026-06-01', 'Monthly Tuition Fee', 'fee_structure_item', 1, 1500.00);

        // Outstanding balance should be 1500
        $this->assertEquals(1500.00, LedgerService::getOutstandingBalance($student->id));

        // Post a credit of 1000
        LedgerService::postCredit($student->id, '2026-06-05', 'Fee Payment Received', 'fee_collection', 101, 1000.00);

        // Outstanding balance should be 500
        $this->assertEquals(500.00, LedgerService::getOutstandingBalance($student->id));

        // Fetch last ledger record and verify running balance is 500
        $lastLedger = StudentFeeLedger::where('student_id', $student->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(500.00, $lastLedger->running_balance);
    }

    /** @test */
    public function test_automatic_ledger_entries_upon_collection_creation()
    {
        $student = Student::create(['name' => 'Jane Collection']);

        // Assert no ledger records initially
        $this->assertEquals(0, StudentFeeLedger::where('student_id', $student->id)->count());

        // Create a Fee Collection (which triggers booted created event)
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-ONLINE-9999',
            'student_id' => $student->id,
            'fee_structure_id' => 1,
            'total_amount' => 1200.00,
            'discount' => 100.00,
            'late_fine' => 20.00,
            'final_amount' => 1120.00,
            'payment_date' => today(),
            'payment_mode' => 'online'
        ]);

        // Booted event should have logged:
        // 1. Credit of 1120 (Payment)
        // 2. Debit of 20 (Late Fine)
        // 3. Credit of 100 (Discount)
        $ledgerEntries = StudentFeeLedger::where('student_id', $student->id)->get();
        
        $this->assertCount(3, $ledgerEntries);

        // Check Credit entry
        $creditEntry = $ledgerEntries->firstWhere('credit', 1120.00);
        $this->assertNotNull($creditEntry);
        $this->assertEquals('fee_collection', $creditEntry->reference_type);

        // Check Late Fine entry
        $fineEntry = $ledgerEntries->firstWhere('debit', 20.00);
        $this->assertNotNull($fineEntry);

        // Check Discount entry
        $discountEntry = $ledgerEntries->firstWhere('credit', 100.00);
        $this->assertNotNull($discountEntry);
    }

    /** @test */
    public function test_bulk_allocation_performance_and_completeness()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 1',
            'academic_year' => '2026-27',
            'frequency' => 'quarterly'
        ]);
        
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 1200.00
        ]);

        $s1 = Student::create(['name' => 'Student 1']);
        $s2 = Student::create(['name' => 'Student 2']);

        // Run bulk assignment
        \App\Services\BulkFeeAssignmentService::bulkAssign($feeStructure, [$s1->id, $s2->id]);

        // Verify assignment records exist
        $this->assertEquals(1, StudentFeeAssignment::where('student_id', $s1->id)->count());
        $this->assertEquals(1, StudentFeeAssignment::where('student_id', $s2->id)->count());

        // Verify ledger entries exist (quarterly has Q1, Q2, Q3, Q4 = 4 debits each)
        $this->assertEquals(4, StudentFeeLedger::where('student_id', $s1->id)->where('reference_type', 'fee_structure_item')->count());
        $this->assertEquals(4, StudentFeeLedger::where('student_id', $s2->id)->where('reference_type', 'fee_structure_item')->count());

        // Verify running balances are computed sequentially
        $ledgerEntries = StudentFeeLedger::where('student_id', $s1->id)->orderBy('id', 'asc')->get();
        $this->assertEquals(1200.00, $ledgerEntries[0]->running_balance);
        $this->assertEquals(2400.00, $ledgerEntries[1]->running_balance);
        $this->assertEquals(3600.00, $ledgerEntries[2]->running_balance);
        $this->assertEquals(4800.00, $ledgerEntries[3]->running_balance);
    }

    /** @test */
    public function test_mid_year_student_withdrawal_drops_future_dues()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 2',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 1000.00
        ]);

        $student = Student::create(['name' => 'Withdrawn Student']);
        
        // Assign structure (generates 12 monthly debits: 2026-04 to 2027-03)
        \App\Services\BulkFeeAssignmentService::bulkAssign($feeStructure, [$student->id]);

        $this->assertEquals(12, StudentFeeLedger::where('student_id', $student->id)->where('debit', '>', 0)->count());

        // Withdraw student on 2026-08-31
        $adjustmentService = new \App\Services\StructureAdjustmentService();
        $adjustmentService->withdrawStudent($student, '2026-08-31');

        // Future debits (Sept, Oct, Nov, Dec, Jan, Feb, Mar = 7 debits) should be dropped
        // Remaining debits: April, May, June, July, August = 5 debits
        $remainingDebits = StudentFeeLedger::where('student_id', $student->id)->where('debit', '>', 0)->get();
        $this->assertCount(5, $remainingDebits);

        // Verify sequential running balances recalculated correctly
        $this->assertEquals(5000.00, LedgerService::getOutstandingBalance($student->id));
        $lastEntry = StudentFeeLedger::where('student_id', $student->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(5000.00, $lastEntry->running_balance);
    }

    /** @test */
    public function test_discount_calculations_use_immutable_snapshots()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 3',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 2000.00
        ]);

        // Create siblings
        $s1 = Student::create(['name' => 'John Doe', 'father_name' => 'Mark Doe', 'mobile' => '9876543210']);
        $s2 = Student::create(['name' => 'Jane Doe', 'father_name' => 'Mark Doe', 'mobile' => '9876543210']);

        // Create sibling discount rule (10% for 2nd child)
        $rule = DiscountRule::create([
            'name' => 'Sibling Discount',
            'type' => 'sibling',
            'config' => [
                'fee_type' => 'Tuition Fee',
                'rates' => [0, 10]
            ],
            'priority' => 10,
            'is_active' => true
        ]);

        // Perform collection for s2 (triggers booted created event and records the snapshot)
        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-SNAP-1234',
            'student_id' => $s2->id,
            'fee_structure_id' => $feeStructure->id,
            'total_amount' => 2000.00,
            'discount' => 200.00, // 10% of 2000
            'late_fine' => 0.00,
            'final_amount' => 1800.00,
            'payment_date' => \Carbon\Carbon::parse('2026-06-15'),
            'payment_mode' => 'cash'
        ]);

        // Verify discount snapshot recorded in student_discounts_applied
        $this->assertEquals(1, StudentDiscountApplied::where('student_id', $s2->id)->count());
        $snapshot = StudentDiscountApplied::where('student_id', $s2->id)->first();
        $this->assertEquals(200.00, $snapshot->amount);

        // Break sibling relationship on the student (e.g. clear father name)
        $s2->update(['father_name' => '']);

        // Evaluate discounts again for the same month/year
        $engine = new DiscountEngineService();
        $feeItems = [['fee_type_id' => $tuitionFeeType->id, 'amount' => 2000.00]];
        $recalculated = $engine->calculateDiscounts($s2, 'June', '2026-27', $feeItems);

        // It should still return the snapshot value of 200.00 instead of 0.00!
        $this->assertCount(1, $recalculated);
        $this->assertEquals(200.00, $recalculated[0]['amount']);
    }

    /** @test */
    public function test_fee_structure_custom_frequency_and_months_are_saved_and_billed_correctly()
    {
        $tuitionFeeType = FeeType::create(['name' => 'Tuition Fee']);
        $schoolClass = SchoolClass::create(['name' => 'Class 4', 'class_order' => 4]);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 4',
            'academic_year' => '2026-27',
            'frequency' => 'custom'
        ]);

        // Tuition fee charged monthly except June (11 months)
        $months = ['April', 'May', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];
        FeeStructureItem::create([
            'fee_structure_id' => $feeStructure->id,
            'fee_type_id' => $tuitionFeeType->id,
            'amount' => 1500.00,
            'billing_frequency' => 'monthly',
            'charge_months' => $months
        ]);

        $student = Student::create(['name' => 'Custom Bill Student', 'class_id' => $schoolClass->id, 'is_active' => true]);

        // Trigger assignment
        $assignment = StudentFeeAssignment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'academic_year' => '2026-27'
        ]);

        // Verify ledger entries exist for only the 11 custom months (June should be absent)
        $ledgers = StudentFeeLedger::where('student_id', $student->id)->where('reference_type', 'fee_structure_item')->get();
        $this->assertCount(11, $ledgers);

        // Verify June is indeed absent
        $juneLedger = StudentFeeLedger::where('student_id', $student->id)
            ->where('description', 'like', '%June%')
            ->first();
        $this->assertNull($juneLedger);

        // Verify other months like July are present
        $julyLedger = StudentFeeLedger::where('student_id', $student->id)
            ->where('description', 'like', '%July%')
            ->first();
        $this->assertNotNull($julyLedger);
    }
}

