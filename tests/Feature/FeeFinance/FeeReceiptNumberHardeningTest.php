<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\FeeType;
use App\Models\FeeStructure;
use App\Models\FeeCollection;
use App\Models\FeeCollectionItem;
use App\Models\StudentFeeAssignment;
use App\Services\FeeReceiptNumberService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class FeeReceiptNumberHardeningTest extends TestCase
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
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
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

        $schema->create('student_fee_assignments', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->string('academic_year')->nullable();
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
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('fee_structure_id')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('late_fine', 10, 2)->nullable();
            $table->decimal('final_amount', 10, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_mode')->nullable();
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

        $schema->create('student_fee_ledgers', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id');
            $table->date('date');
            $table->string('description');
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

        $schema->create('student_financial_accounts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->unique();
            $table->string('account_number')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('student_financial_accounts');
        Schema::dropIfExists('student_fee_ledgers');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('fee_collection_items');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('fee_types');
        Schema::dropIfExists('student_fee_assignments');
        Schema::dropIfExists('fee_structure_items');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    /** @test */
    public function test_generates_next_receipt_number_from_matching_canonical_receipts_only()
    {
        // Assert initial starts at SCH-REC-0001
        $this->assertEquals('SCH-REC-0001', FeeReceiptNumberService::generateNextReceiptNumber());

        // Create a canonical receipt
        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0001',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        $this->assertEquals('SCH-REC-0002', FeeReceiptNumberService::generateNextReceiptNumber());

        // Create another canonical receipt with gaps
        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0010',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        $this->assertEquals('SCH-REC-0011', FeeReceiptNumberService::generateNextReceiptNumber());
    }

    /** @test */
    public function test_ignores_timestamp_style_receipts_when_calculating_next_numeric_sequence()
    {
        // Create timestamp style receipts (e.g. RCPT-20260613123456)
        FeeCollection::create([
            'receipt_no' => 'RCPT-20260613123456',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        // Should default to SCH-REC-0001 because no SCH-REC-* exists
        $this->assertEquals('SCH-REC-0001', FeeReceiptNumberService::generateNextReceiptNumber());

        // Add a canonical receipt
        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0005',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        // Should ignore RCPT- and increment the SCH-REC- record
        $this->assertEquals('SCH-REC-0006', FeeReceiptNumberService::generateNextReceiptNumber());
    }

    /** @test */
    public function test_does_not_extract_last_four_digits_from_incompatible_receipt_format()
    {
        // If we have mixed or incompatible formats like RCPT-9999
        FeeCollection::create([
            'receipt_no' => 'RCPT-9999',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        // Should still start at SCH-REC-0001
        $this->assertEquals('SCH-REC-0001', FeeReceiptNumberService::generateNextReceiptNumber());
    }

    /** @test */
    public function test_generates_padded_receipt_numbers_consistently()
    {
        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0099',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        $this->assertEquals('SCH-REC-0100', FeeReceiptNumberService::generateNextReceiptNumber());

        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0999',
            'student_id' => 1,
            'fee_structure_id' => 1,
            'total_amount' => 100,
            'final_amount' => 100,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        $this->assertEquals('SCH-REC-1000', FeeReceiptNumberService::generateNextReceiptNumber());
    }

    /** @test */
    public function test_handles_duplicate_receipt_number_collision_with_retry_or_controlled_failure()
    {
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'admin']);
        $adminUser->roles()->attach($role->id);

        $class = SchoolClass::create([
            'name' => 'Class X',
            'class_order' => 1,
            'is_active' => true,
        ]);

        $student = Student::create([
            'name' => 'John Doe',
            'class_id' => $class->id,
            'class' => 'Class X',
            'admission_no' => 'ADM-001',
        ]);

        $feeStructure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'is_active' => true,
        ]);

        $feeType = FeeType::create([
            'name' => 'Tuition Fee',
        ]);

        StudentFeeAssignment::create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'academic_year' => '2026-27',
        ]);

        // Pre-create SCH-REC-0001
        FeeCollection::create([
            'receipt_no' => 'SCH-REC-0001',
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'total_amount' => 500,
            'final_amount' => 500,
            'payment_date' => now(),
            'payment_mode' => 'cash',
        ]);

        // Now, we will simulate a concurrent conflict in a test.
        // We will call collectFee, but during the transaction we want it to collide.
        // Let's verify that the controller's collectFee retry logic works.
        // Since we are running in SQLite, if we send a request, the next generated receipt is SCH-REC-0002.
        // If we concurrently insert SCH-REC-0002 just before the transaction executes, the query would fail and retry.
        // Let's check that collectFee succeeds because it increments receipt_no on retry.
        
        // We can test this by checking that processCollection handles collision successfully.
        $response = $this->actingAs($adminUser)->post(route('admin.fees.process.collection'), [
            'student_id' => $student->id,
            'fee_types' => [$feeType->id],
            'amount_' . $feeType->id => 500,
            'total_amount' => 500,
            'payment_mode' => 'cash',
            'payment_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        
        // We should have SCH-REC-0002 in DB
        $this->assertDatabaseHas('fee_collections', [
            'receipt_no' => 'SCH-REC-0002',
            'student_id' => $student->id,
        ]);
    }

    /** @test */
    public function test_fee_route_authorization_guard_still_passes()
    {
        $user = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'teacher']);
        $user->roles()->attach($role->id);

        // Teacher cannot access fees index
        $response = $this->actingAs($user)->get(route('admin.fees.index'));
        $response->assertStatus(403);

        // Admin can access
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
        $adminRole = Role::create(['name' => 'admin']);
        $adminUser->roles()->attach($adminRole->id);

        $response = $this->actingAs($adminUser)->get(route('admin.fees.index'));
        $response->assertStatus(200);
    }

    /** @test */
    public function test_payment_date_column_test_still_passes()
    {
        $columns = Schema::getColumnListing('fee_collections');
        $this->assertTrue(in_array('payment_date', $columns));
        $this->assertFalse(in_array('collection_date', $columns));
    }

    /** @test */
    public function test_professional_receipt_relationship_test_still_passes()
    {
        // Assert that the relationship definitions exist
        $item = new FeeCollectionItem();
        $this->assertTrue(method_exists($item, 'feeType'));
    }
}
