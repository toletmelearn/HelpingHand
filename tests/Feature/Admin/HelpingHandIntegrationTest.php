<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicSession;
use App\Models\StudentFeeLedger;
use App\Models\Fee;
use App\Models\FinancialYearClosing;
use App\Services\LedgerService;
use App\Services\FinancialYearClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

class HelpingHandIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;
    protected $schoolClass;
    protected $section;
    protected $sessionOld;
    protected $sessionNew;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('PRAGMA foreign_keys = OFF;');

        config([
            'features.new_ledger_sync' => true,
            'features.year_closing_queue' => true,
            'queue.default' => 'sync',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10,
            'is_active' => true
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'John Doe',
            'admission_no' => 'ADM-2026-7777',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);

        $this->sessionOld = AcademicSession::create([
            'name' => '2024-25',
            'code' => '2024-25',
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_current' => false,
            'is_active' => true
        ]);

        $this->sessionNew = AcademicSession::create([
            'name' => '2025-26',
            'code' => '2025-26',
            'start_date' => '2025-04-01',
            'end_date' => '2026-03-31',
            'is_current' => true,
            'is_active' => true
        ]);

        // Seed tables to satisfy foreign key constraints
        DB::table('fee_structures')->insert([
            'id' => 1,
            'class_name' => 'Class 10',
            'academic_year' => '2024-25',
            'frequency' => 'monthly',
            'status' => 'active',
            'created_by' => $this->adminUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @test */
    public function allocate_online_payment_creates_collection_and_posts_ledger_credit()
    {
        // 1. Check current outstanding balance is 0
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($this->student->id));

        // 2. Post a debit item in old session (unpaid) to simulate dues
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-10',
            'description' => 'Tuition Fee May 24',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1500.00,
            'credit' => 0.00,
            'running_balance' => 1500.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 1500.00
        ]);

        $this->assertEquals(1500.00, LedgerService::getOutstandingBalance($this->student->id));

        // 3. Record the payment via the shared online-payment allocator
        // (replaces the retired Stripe webhook -> PaymentCompleted event ->
        // PostLedgerCreditListener bridge -- that path was never reachable
        // through the live UI and is superseded by this direct call).
        LedgerService::allocateOnlinePayment(
            $this->student->id,
            1500.00,
            'online',
            ['fee_structure_id' => 1]
        );

        // 4. Assert FeeCollection was created in database
        $this->assertDatabaseHas('fee_collections', [
            'student_id' => $this->student->id,
            'total_amount' => 1500.00,
            'payment_mode' => 'online',
        ]);

        // 5. Assert outstanding balance was cleared
        $this->assertEquals(0.00, LedgerService::getOutstandingBalance($this->student->id));
    }

    /** @test */
    public function queued_year_closing_job_stages_process_in_chunks_and_updates_progress_percent()
    {
        // 1. Post a debit item in old session (unpaid) to simulate dues
        StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => '2024-05-10',
            'description' => 'Tuition Fee May 24',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => 1500.00,
            'credit' => 0.00,
            'running_balance' => 1500.00,
            'academic_year' => '2024-25',
            'unpaid_amount' => 1500.00
        ]);

        // 2. Stage year closing (this will dispatch StageYearClosingJob synchronously)
        $closing = FinancialYearClosingService::stageClosing(
            '2024-25',
            '2025-26',
            'Queued E2E Staging Test',
            $this->accountantUser->id
        );

        // 3. Assert closing record has progress percentage and status staged
        if ($closing->fresh()->status === 'failed') {
            $this->fail("Closing failed: " . $closing->fresh()->error_message);
        }

        $this->assertDatabaseHas('financial_year_closings', [
            'id' => $closing->id,
            'status' => 'staged',
            'progress_percent' => 100.00,
            'total_students_processed' => 1,
            'total_balance_carried' => 1500.00,
        ]);

        // 4. Assert opening balance is posted in new year
        $this->assertDatabaseHas('student_fee_ledgers', [
            'student_id' => $this->student->id,
            'academic_year' => '2025-26',
            'reference_type' => 'year_closing',
            'debit' => 1500.00,
        ]);
    }
}
