<?php

namespace Tests\Feature\FeeFinance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\FeeStructure;
use App\Models\FeeCollection;
use App\Services\ProfessionalDashboardService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ProfessionalFeePaymentDateColumnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $schema = Schema::connection('sqlite');

        $schema->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        $schema->create('school_classes', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->integer('class_order')->nullable();
            $table->timestamps();
        });

        $schema->create('students', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('roll_number')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('admission_no')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('aadhar_number')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('teachers', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        $schema->create('attendances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->date('date')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('fee_structures', function ($table) {
            $table->bigIncrements('id');
            $table->string('class_name')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('frequency')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('fee_collections', function ($table) {
            $table->bigIncrements('id');
            $table->string('receipt_no')->nullable();
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
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('exams', function ($table) {
            $table->bigIncrements('id');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('results', function ($table) {
            $table->bigIncrements('id');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        $schema->create('lesson_plans', function ($table) {
            $table->bigIncrements('id');
            $table->string('status')->nullable();
            $table->timestamp('deleted_at')->nullable();
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

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('lesson_plans');
        Schema::dropIfExists('results');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('fee_collections');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('teachers');
        Schema::dropIfExists('students');
        Schema::dropIfExists('school_classes');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    /** @test */
    public function test_professional_dashboard_source_code_uses_payment_date_not_collection_date()
    {
        $file = base_path('app/Services/ProfessionalDashboardService.php');
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('payment_date', $content);
        $this->assertStringNotContainsString('collection_date', $content);
    }

    /** @test */
    public function test_fee_collection_test_schema_has_payment_date_not_collection_date()
    {
        $this->assertTrue(Schema::hasColumn('fee_collections', 'payment_date'));
        $this->assertFalse(Schema::hasColumn('fee_collections', 'collection_date'));
    }

    /** @test */
    public function test_professional_monthly_date_report_logic_can_query_payment_date_in_isolated_sqlite()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);

        $class = SchoolClass::create([
            'name' => 'Class X',
            'class_order' => 10,
        ]);

        $student = Student::create([
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'roll_number' => '101',
            'class_id' => $class->id,
            'admission_no' => 'ADM-001',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'date_of_birth' => '2015-05-15',
            'aadhar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '1234567890',
        ]);

        $structure = FeeStructure::create([
            'class_name' => 'Class X',
            'academic_year' => '2026-27',
            'frequency' => 'monthly',
            'status' => 'active',
        ]);

        FeeCollection::create([
            'receipt_no' => 'REC-0001',
            'student_id' => $student->id,
            'fee_structure_id' => $structure->id,
            'total_amount' => 3000.00,
            'discount' => 0.00,
            'late_fine' => 0.00,
            'final_amount' => 3000.00,
            'payment_date' => now()->format('Y-m-d'),
            'payment_mode' => 'cash',
            'collected_by' => $admin->id,
        ]);

        $dashboardService = new ProfessionalDashboardService();
        $stats = $dashboardService->getAdminStatistics();
        $this->assertEquals(3000.00, $stats['finance']['monthly_collection']);
    }
}
