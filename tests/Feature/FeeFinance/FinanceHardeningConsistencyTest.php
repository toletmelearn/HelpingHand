<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\FeeStructureItem;
use App\Models\FeeCollection;
use App\Models\StudentFeeLedger;
use App\Models\FeeRefund;
use App\Services\LedgerService;
use App\Services\RefundService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FinanceHardeningConsistencyTest extends TestCase
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
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('admission_no')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('fee_types', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
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
        });

        $schema->create('fee_structure_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
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
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_refunds', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('fee_collection_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('type');
            $table->text('reason')->nullable();
            $table->string('payment_mode');
            $table->unsignedBigInteger('processed_by');
            $table->timestamp('processed_at')->useCurrent();
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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('student_fee_ledgers');
        Schema::dropIfExists('fee_refunds');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('students');
        Schema::dropIfExists('users');
        Schema::dropIfExists('audit_logs');

        parent::tearDown();
    }

    /** @test */
    public function test_scenario_1_fee_assigned_and_paid_outstanding_becomes_zero()
    {
        // 1. Setup Student, Class, FeeType, Structure, Item
        $class = SchoolClass::create(['name' => 'Class X']);
        $student = Student::create(['name' => 'John Doe', 'class_id' => $class->id, 'admission_no' => 'ADM-001']);
        $feeType = FeeType::create(['name' => 'Tuition Fee']);
        
        $structure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        $item = FeeStructureItem::create([
            'fee_structure_id' => $structure->id,
            'fee_type_id' => $feeType->id,
            'amount' => 5000.00
        ]);

        // 2. Post Debit (Assigned 5000)
        LedgerService::postDebit(
            $student->id,
            '2026-06-23',
            'Monthly Tuition Fee',
            'fee_structure_item',
            $item->id,
            5000.00
        );

        // Verify outstanding is 5000
        $this->assertEquals(5000.00, LedgerService::getOutstandingBalance($student->id));

        // 3. Create Collection (Paid 5000)
        $collection = FeeCollection::create([
            'receipt_no' => 'REC-001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 5000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 5000.00,
            'payment_date' => now(),
            'payment_mode' => 'cash'
        ]);

        // Verify outstanding becomes 0
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));
        
        // Verify ledger entries exist
        $ledger = LedgerService::getLedger($student->id);
        $this->assertCount(2, $ledger);
        $this->assertEquals(5000.00, $ledger[0]->debit);
        $this->assertEquals(5000.00, $ledger[1]->credit);
    }

    /** @test */
    public function test_scenario_2_fee_assigned_paid_and_refunded_outstanding_recalculates()
    {
        $class = SchoolClass::create(['name' => 'Class X']);
        $student = Student::create(['name' => 'John Doe', 'class_id' => $class->id, 'admission_no' => 'ADM-001']);
        $feeType = FeeType::create(['name' => 'Tuition Fee']);
        
        $structure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        $item = FeeStructureItem::create([
            'fee_structure_id' => $structure->id,
            'fee_type_id' => $feeType->id,
            'amount' => 5000.00
        ]);

        // Debit 5000
        LedgerService::postDebit($student->id, '2026-06-23', 'Monthly Tuition Fee', 'fee_structure_item', $item->id, 5000.00);

        // Credit 5000 (Collection)
        $collection = FeeCollection::create([
            'receipt_no' => 'REC-001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 5000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 5000.00,
            'payment_date' => now(),
            'payment_mode' => 'cash'
        ]);

        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));

        // Issue Refund of 1000
        $refund = RefundService::issueRefund($student->id, $collection->id, 1000.00, 'cash', 'Caution money refund');

        $this->assertNotNull($refund);
        $this->assertEquals(1000.00, $refund->amount);
        $this->assertEquals('refund', $refund->type);

        // Verify outstanding is now 1000
        $this->assertEquals(1000.00, LedgerService::getOutstandingBalance($student->id));

        // Verify ledger structure: 2 original entries + 1 refund debit = 3 entries
        $ledger = LedgerService::getLedger($student->id);
        $this->assertCount(3, $ledger);
        
        // No entries were physically deleted
        $this->assertEquals(5000.00, $ledger[0]->debit);
        $this->assertEquals(5000.00, $ledger[1]->credit);
        
        // New debit entry posted
        $this->assertEquals(1000.00, $ledger[2]->debit);
        $this->assertEquals('fee_refund', $ledger[2]->reference_type);
        $this->assertEquals($refund->id, $ledger[2]->reference_id);
    }

    /** @test */
    public function test_scenario_3_fee_assigned_paid_and_reversed_outstanding_restored_without_deletes()
    {
        $class = SchoolClass::create(['name' => 'Class X']);
        $student = Student::create(['name' => 'John Doe', 'class_id' => $class->id, 'admission_no' => 'ADM-001']);
        $feeType = FeeType::create(['name' => 'Tuition Fee']);
        
        $structure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        $item = FeeStructureItem::create([
            'fee_structure_id' => $structure->id,
            'fee_type_id' => $feeType->id,
            'amount' => 5000.00
        ]);

        // Debit 5000
        LedgerService::postDebit($student->id, '2026-06-23', 'Monthly Tuition Fee', 'fee_structure_item', $item->id, 5000.00);

        // Credit 5000 (Collection)
        $collection = FeeCollection::create([
            'receipt_no' => 'REC-001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 5000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 5000.00,
            'payment_date' => now(),
            'payment_mode' => 'cash'
        ]);

        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($student->id));

        // Reverse collection (Bounced Cheque)
        $reversal = RefundService::reverseCollection($collection->id, 'Bounced Cheque');

        $this->assertNotNull($reversal);
        $this->assertEquals(5000.00, $reversal->amount);
        $this->assertEquals('reversal', $reversal->type);

        // Verify outstanding returns to 5000
        $this->assertEquals(5000.00, LedgerService::getOutstandingBalance($student->id));

        // Verify ledger: 1 original debit + 1 original credit + 1 reversing debit = 3 entries (No deletions!)
        $ledger = LedgerService::getLedger($student->id);
        $this->assertCount(3, $ledger);
        $this->assertEquals(5000.00, $ledger[0]->debit);
        $this->assertEquals(5000.00, $ledger[1]->credit);
        $this->assertEquals(5000.00, $ledger[2]->debit);
        $this->assertStringContainsString('Reversal of Payment', $ledger[2]->description);
    }

    /** @test */
    public function test_scenario_4_unknown_historical_references_resolve_to_null_tags()
    {
        $class = SchoolClass::create(['name' => 'Class X']);
        $student = Student::create(['name' => 'John Doe', 'class_id' => $class->id, 'admission_no' => 'ADM-001']);

        // Post a debit with a non-existent reference (unknown reference ID)
        $ledgerEntry = LedgerService::postDebit(
            $student->id,
            '2026-06-23',
            'Legacy Unknown Fee Charge',
            'fee_structure_item',
            99999, // non-existent fee structure item
            1200.00
        );

        $this->assertNotNull($ledgerEntry);
        
        // Assert that tags are NULL instead of being extrapolated/guessed
        $this->assertNull($ledgerEntry->academic_year);
        $this->assertNull($ledgerEntry->class_id);
        $this->assertNull($ledgerEntry->fee_type_id);
    }

    /** @test */
    public function test_scenario_5_overpayment_creates_negative_outstanding_balance()
    {
        $class = SchoolClass::create(['name' => 'Class X']);
        $student = Student::create(['name' => 'John Doe', 'class_id' => $class->id, 'admission_no' => 'ADM-001']);
        $feeType = FeeType::create(['name' => 'Tuition Fee']);
        
        $structure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly'
        ]);
        
        $item = FeeStructureItem::create([
            'fee_structure_id' => $structure->id,
            'fee_type_id' => $feeType->id,
            'amount' => 5000.00
        ]);

        // Debit 5000
        LedgerService::postDebit($student->id, '2026-06-23', 'Monthly Tuition Fee', 'fee_structure_item', $item->id, 5000.00);

        // Credit 7000 (Overpaid Collection)
        $collection = FeeCollection::create([
            'receipt_no' => 'REC-001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 5000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 7000.00, // Paid 7000!
            'payment_date' => now(),
            'payment_mode' => 'cash'
        ]);

        // Verify outstanding is -2000
        $this->assertEquals(-2000.00, LedgerService::getOutstandingBalance($student->id));

        // Query students with credit balances (simulates the overpayments controller report query)
        $overpaidStudents = Student::select('students.*')
            ->selectRaw('(SELECT SUM(debit) - SUM(credit) FROM student_fee_ledgers WHERE student_fee_ledgers.student_id = students.id) as outstanding')
            ->groupBy('students.id')
            ->having('outstanding', '<', 0)
            ->get();

        $this->assertCount(1, $overpaidStudents);
        $this->assertEquals($student->id, $overpaidStudents[0]->id);
        $this->assertEquals(-2000.00, (float)$overpaidStudents[0]->outstanding);
    }
}
