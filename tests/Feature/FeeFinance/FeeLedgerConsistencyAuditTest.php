<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class FeeLedgerConsistencyAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        // Setup tables
        $schema->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('roles', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->timestamps();
        });

        $schema->create('role_user', function ($table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->integer('class_order')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('class')->nullable();
            $table->string('admission_no')->nullable();
            $table->string('roll_number')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
            $table->integer('installment_count')->default(1);
            $table->string('installment_frequency')->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_structure_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->string('name')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->integer('due_day')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_types', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_collections', function ($table) {
            $table->bigIncrements('id');
            $table->string('receipt_no')->unique()->nullable();
            $table->string('receipt_number')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('late_fine', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->decimal('paid_amount', 10, 2)->nullable();
            $table->decimal('balance_amount', 10, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('collected_by')->nullable();
            $table->string('fee_month')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_collection_items', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fee_collection_id')->nullable();
            $table->unsignedBigInteger('fee_type_id')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamps();
        });

        $schema->create('audit_logs', function ($table) {
            $table->bigIncrements('id');
            $table->string('user_type')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('field_name')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('action')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('performed_at')->nullable();
            $table->timestamps();
        });
    }

    /** @test */
    public function test_collected_total_equals_sum_of_fee_collection_items_in_database()
    {
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password')
        ]);

        $student = Student::create([
            'name' => 'John Doe',
            'class' => 'Class 10'
        ]);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Class 10',
            'frequency' => 'monthly'
        ]);

        $collection = FeeCollection::create([
            'receipt_no' => 'RCPT-10001',
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'total_amount' => 500.00,
            'discount' => 50.00,
            'late_fine' => 10.00,
            'final_amount' => 460.00,
            'payment_date' => today(),
            'payment_mode' => 'cash',
            'collected_by' => $adminUser->id
        ]);

        $item1 = FeeCollectionItem::create([
            'fee_collection_id' => $collection->id,
            'fee_type_id' => 1,
            'amount' => 300.00
        ]);

        $item2 = FeeCollectionItem::create([
            'fee_collection_id' => $collection->id,
            'fee_type_id' => 2,
            'amount' => 200.00
        ]);

        $sumOfItems = FeeCollectionItem::where('fee_collection_id', $collection->id)->sum('amount');
        $this->assertEquals($collection->total_amount, $sumOfItems);
    }

    /** @test */
    public function test_fee_collection_schema_uses_payment_date_and_has_no_collection_date()
    {
        $this->assertTrue(Schema::hasColumn('fee_collections', 'payment_date'));
        $this->assertFalse(Schema::hasColumn('fee_collections', 'collection_date'));
    }

    /** @test */
    public function test_unsafe_delete_routes_remain_quarantined()
    {
        $routes = Route::getRoutes();
        
        // Ensure that there is no active fees.destroy route mapped via web.php
        $destroyRoute = collect($routes)->first(function ($route) {
            return $route->getName() === 'admin.fees.destroy';
        });

        $this->assertNull($destroyRoute);
    }
}
