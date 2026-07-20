<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\LateFeeRule;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FeeStructureWizardTest extends TestCase
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
            $table->unsignedBigInteger('class_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_types', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('category')->default('recurring');
            $table->string('status')->default('active');
            $table->string('default_frequency')->default('monthly');
            $table->json('default_charge_months')->nullable();
            $table->unsignedBigInteger('default_late_fee_rule_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('late_fee_rules', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->integer('grace_days')->default(0);
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_structure_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->integer('due_day')->nullable();
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

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /** @test */
    public function test_fee_types_store_and_expose_default_billing_templates()
    {
        $lateRule = LateFeeRule::create([
            'name' => 'Grace Penalty',
            'type' => 'flat',
            'amount' => 50.00
        ]);

        $feeType = FeeType::create([
            'name' => 'Tuition Fee',
            'category' => 'recurring',
            'default_frequency' => 'monthly',
            'default_charge_months' => ['April', 'May', 'July'],
            'default_late_fee_rule_id' => $lateRule->id
        ]);

        $this->assertEquals('monthly', $feeType->default_frequency);
        $this->assertContains('April', $feeType->default_charge_months);
        $this->assertEquals($lateRule->id, $feeType->default_late_fee_rule_id);
    }

    /** @test */
    public function test_fee_structure_copy_utility_duplicates_structure_items_and_installments()
    {
        // 1. Setup Source Structure
        $tuition = FeeType::create(['name' => 'Tuition Fee']);
        $source = FeeStructure::create([
            'class_name' => 'Grade 1',
            'academic_year' => '2026-27',
            'frequency' => 'custom'
        ]);

        $item = FeeStructureItem::create([
            'fee_structure_id' => $source->id,
            'fee_type_id' => $tuition->id,
            'amount' => 3000.00,
            'billing_frequency' => 'custom',
            'charge_months' => ['July', 'January']
        ]);

        // Add installments: July = 1000, January = 2000
        $item->installments()->create(['month' => 'July', 'amount' => 1000.00]);
        $item->installments()->create(['month' => 'January', 'amount' => 2000.00]);

        // Create target class
        $schoolClass = SchoolClass::create(['name' => 'Grade 2']);
        $student = Student::create(['name' => 'Copy Test Student', 'class_id' => $schoolClass->id, 'is_active' => true]);

        // 2. Perform Copy Action (No increase)
        $controller = new \App\Http\Controllers\Admin\FeeStructureController();
        $request = new \Illuminate\Http\Request([
            'source_structure_id' => $source->id,
            'target_class_name' => 'Grade 2',
            'target_academic_year' => '2026-27',
            'percentage_increase' => 0
        ]);

        $response = $controller->copyStructure($request);

        // Verify Target Structure
        $target = FeeStructure::where('class_name', 'Grade 2')->where('academic_year', '2026-27')->first();
        $this->assertNotNull($target);
        $this->assertEquals('custom', $target->frequency);

        $targetItems = $target->feeStructureItems;
        $this->assertCount(1, $targetItems);
        
        $targetItem = $targetItems->first();
        $this->assertEquals(3000.00, $targetItem->amount);
        $this->assertContains('July', $targetItem->charge_months);

        $targetInsts = $targetItem->installments;
        $this->assertCount(2, $targetInsts);
        $this->assertEquals(1000.00, $targetInsts->firstWhere('month', 'July')->amount);
        $this->assertEquals(2000.00, $targetInsts->firstWhere('month', 'January')->amount);
    }

    /** @test */
    public function test_fee_structure_copy_with_percentage_increase_adjusts_amounts()
    {
        // 1. Setup Source Structure
        $tuition = FeeType::create(['name' => 'Tuition Fee']);
        $source = FeeStructure::create([
            'class_name' => 'Grade 3',
            'academic_year' => '2026-27',
            'frequency' => 'custom'
        ]);

        $item = FeeStructureItem::create([
            'fee_structure_id' => $source->id,
            'fee_type_id' => $tuition->id,
            'amount' => 1000.00,
            'billing_frequency' => 'custom',
            'charge_months' => ['July', 'January']
        ]);

        $item->installments()->create(['month' => 'July', 'amount' => 400.00]);
        $item->installments()->create(['month' => 'January', 'amount' => 600.00]);

        // Create target class
        $schoolClass = SchoolClass::create(['name' => 'Grade 4']);

        // 2. Perform Copy Action with 10% increase
        $controller = new \App\Http\Controllers\Admin\FeeStructureController();
        $request = new \Illuminate\Http\Request([
            'source_structure_id' => $source->id,
            'target_class_name' => 'Grade 4',
            'target_academic_year' => '2026-27',
            'percentage_increase' => 10.00
        ]);

        $response = $controller->copyStructure($request);

        // Verify Target Structure amounts increased by 10%
        $target = FeeStructure::where('class_name', 'Grade 4')->where('academic_year', '2026-27')->first();
        $targetItem = $target->feeStructureItems->first();
        
        $this->assertEquals(1100.00, $targetItem->amount); // 1000 * 1.1 = 1100
        $this->assertEquals(440.00, $targetItem->installments->firstWhere('month', 'July')->amount); // 400 * 1.1 = 440
        $this->assertEquals(660.00, $targetItem->installments->firstWhere('month', 'January')->amount); // 600 * 1.1 = 660
    }
}
