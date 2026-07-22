<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentFeeLedger;
use App\Models\FeeReminder;
use App\Services\ReminderEngineService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReminderEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $schoolClass;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        // Real SMS delivery goes through Twilio, which isn't configured in
        // tests -- bind a mocked NotificationService so every
        // app(ReminderEngineService::class) resolution in this file gets a
        // notifier that always reports success, exercising the reminder
        // engine's own scheduling/retry logic rather than Twilio reachability.
        $mockNotificationService = $this->createMock(NotificationService::class);
        $mockNotificationService->method('sendSms')->willReturn(true);
        $mockNotificationService->method('sendWhatsApp')->willReturn(true);
        $mockNotificationService->method('sendEmail')->willReturn(true);
        $mockNotificationService->method('queueNotification')->willReturn(true);
        $this->app->instance(NotificationService::class, $mockNotificationService);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'Jane Smith',
            'admission_no' => 'ADM-2026-8888',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);
    }

    /**
     * Post an unpaid debit entry to the student's ledger, standing in for
     * the legacy Fee row the reminder engine used to read.
     */
    protected function postDebit(string $date, float $amount): StudentFeeLedger
    {
        return StudentFeeLedger::create([
            'student_id' => $this->student->id,
            'date' => $date,
            'description' => 'Tuition Fee',
            'reference_type' => 'fee_structure_item',
            'reference_id' => 1,
            'debit' => $amount,
            'credit' => 0.00,
            'running_balance' => $amount,
            'academic_year' => '2026',
            'unpaid_amount' => $amount,
        ]);
    }

    /** @test */
    public function before_due_date_rule_triggers_correctly_three_days_before()
    {
        $debit = $this->postDebit(Carbon::now()->addDays(3)->format('Y-m-d'), 3000.00);

        $service = app(ReminderEngineService::class);
        $service->processReminders();

        // 4 reminders should be created (SMS, WhatsApp, Email, App Notification)
        $this->assertEquals(4, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
        $this->assertDatabaseHas('fee_reminders', [
            'student_fee_ledger_id' => $debit->id,
            'rule' => 'Before Due Date',
            'status' => 'sent'
        ]);

        // Duplicate prevention test: running again should not add more reminders
        $service->processReminders();
        $this->assertEquals(4, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
    }

    /** @test */
    public function after_due_date_rule_triggers_correctly_one_day_after()
    {
        $debit = $this->postDebit(Carbon::now()->subDays(1)->format('Y-m-d'), 3000.00);

        $service = app(ReminderEngineService::class);
        $service->processReminders();

        $this->assertEquals(4, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
        $this->assertDatabaseHas('fee_reminders', [
            'student_fee_ledger_id' => $debit->id,
            'rule' => 'After Due Date',
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function repeated_reminders_trigger_at_7_day_intervals()
    {
        $debit = $this->postDebit(Carbon::now()->subDays(8)->format('Y-m-d'), 3000.00);

        // Mock that we already sent "After Due Date" 7 days ago
        $channels = ['sms', 'whatsapp', 'email', 'notification'];
        foreach ($channels as $channel) {
            FeeReminder::create([
                'student_fee_ledger_id' => $debit->id,
                'student_id' => $this->student->id,
                'rule' => 'After Due Date',
                'channel' => $channel,
                'recipient' => 'Test',
                'message' => 'Test Message',
                'status' => 'sent',
                'sent_at' => Carbon::now()->subDays(7),
                'retry_count' => 0
            ]);
        }

        $service = app(ReminderEngineService::class);
        $service->processReminders();

        // Should have added 4 repeated reminders (total = 8 now)
        $this->assertEquals(8, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
        $this->assertEquals(4, FeeReminder::where('student_fee_ledger_id', $debit->id)->where('rule', 'Repeated Reminder')->count());

        // Run again immediately - should NOT add duplicate repeated reminders because of the 7-day interval check
        $service->processReminders();
        $this->assertEquals(8, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
    }

    /** @test */
    public function final_notice_triggers_after_fifteen_days()
    {
        $debit = $this->postDebit(Carbon::now()->subDays(15)->format('Y-m-d'), 3000.00);

        $service = app(ReminderEngineService::class);
        $service->processReminders();

        $this->assertDatabaseHas('fee_reminders', [
            'student_fee_ledger_id' => $debit->id,
            'rule' => 'Final Notice',
            'status' => 'sent'
        ]);
    }

    /** @test */
    public function fully_paid_debits_are_not_reminded()
    {
        $debit = $this->postDebit(Carbon::now()->subDays(1)->format('Y-m-d'), 3000.00);
        $debit->update(['unpaid_amount' => 0.00]);

        $service = app(ReminderEngineService::class);
        $service->processReminders();

        $this->assertEquals(0, FeeReminder::where('student_fee_ledger_id', $debit->id)->count());
    }

    /** @test */
    public function failed_reminders_can_be_retried_up_to_three_times()
    {
        // Create a failed reminder mock
        $reminder = FeeReminder::create([
            'student_id' => $this->student->id,
            'rule' => 'Before Due Date',
            'channel' => 'sms',
            'recipient' => '9876543210',
            'message' => 'Failed Message',
            'status' => 'failed',
            'retry_count' => 1
        ]);

        // Setup mock notifications to succeed during retry
        $service = app(ReminderEngineService::class);
        $service->retryFailedReminders();

        // The reminder should be retried and marked as sent
        $reminder->refresh();
        $this->assertEquals('sent', $reminder->status);
        $this->assertNull($reminder->error_message);
    }
}
