<?php

namespace App\Services;

use App\Models\FeeReminder;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use Illuminate\Support\Facades\Log;

class ReminderEngineService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Process all outstanding fee ledger debits and generate scheduled reminders based on rules.
     */
    public function processReminders()
    {
        $debits = StudentFeeLedger::with(['student.parent', 'student.user'])
            ->where('debit', '>', 0)
            ->where('unpaid_amount', '>', 0.01)
            ->get();

        foreach ($debits as $debit) {
            $student = $debit->student;
            if (!$student) {
                continue;
            }

            $today = today();
            $dueDate = $debit->date;
            $daysDiff = $today->diffInDays($dueDate, false); // Positive if in future, negative if past due

            $rule = null;

            if ($daysDiff == 3) {
                $rule = 'Before Due Date';
            } elseif ($daysDiff == -1) {
                $rule = 'After Due Date';
            } elseif ($daysDiff <= -15) {
                $rule = 'Final Notice';
            } elseif ($daysDiff < -1) {
                $rule = 'Repeated Reminder';
            }

            if (!$rule) {
                continue;
            }

            // If Repeated Reminder is triggered, make sure Final Notice has not been sent yet
            if ($rule === 'Repeated Reminder') {
                $finalNoticeExists = FeeReminder::where('student_fee_ledger_id', $debit->id)
                    ->where('rule', 'Final Notice')
                    ->where('status', 'sent')
                    ->exists();
                if ($finalNoticeExists) {
                    continue;
                }
            }

            $channels = ['sms', 'whatsapp', 'email', 'notification'];

            foreach ($channels as $channel) {
                $recipient = $this->getRecipientForChannel($student, $channel);
                if (!$recipient && $channel !== 'notification') {
                    continue; // Skip if no contact information is available
                }

                // Avoid duplicates check
                if ($rule === 'Repeated Reminder') {
                    $lastSent = FeeReminder::where('student_fee_ledger_id', $debit->id)
                        ->where('channel', $channel)
                        ->whereIn('rule', ['After Due Date', 'Repeated Reminder'])
                        ->where('status', 'sent')
                        ->latest('sent_at')
                        ->first();

                    if ($lastSent) {
                        $daysSinceLast = abs(today()->diffInDays($lastSent->sent_at->startOfDay(), false));
                        if ($daysSinceLast < 7) {
                            continue; // Throttle repeated reminders to 7-day interval
                        }
                    } else {
                        // If no previous "After Due Date" or "Repeated Reminder", check if past due by at least 7 days
                        if (abs($daysDiff) < 7) {
                            continue;
                        }
                    }
                } else {
                    $exists = FeeReminder::where('student_fee_ledger_id', $debit->id)
                        ->where('rule', $rule)
                        ->where('channel', $channel)
                        ->whereIn('status', ['sent', 'pending'])
                        ->exists();

                    if ($exists) {
                        continue;
                    }
                }

                $message = $this->generateMessage($debit, $rule, $channel);

                $reminder = FeeReminder::create([
                    'student_fee_ledger_id' => $debit->id,
                    'student_id' => $debit->student_id,
                    'rule' => $rule,
                    'channel' => $channel,
                    'recipient' => $recipient ?? 'App User',
                    'message' => $message,
                    'status' => 'pending',
                    'retry_count' => 0
                ]);

                $this->sendReminder($reminder);
            }
        }
    }

    /**
     * Dispatch a single reminder and record result.
     */
    public function sendReminder(FeeReminder $reminder): bool
    {
        try {
            $success = false;
            switch ($reminder->channel) {
                case 'sms':
                    $success = $this->notificationService->sendSms($reminder->recipient, $reminder->message);
                    break;
                case 'whatsapp':
                    $success = $this->notificationService->sendWhatsApp($reminder->recipient, $reminder->message);
                    break;
                case 'email':
                    $success = $this->notificationService->sendEmail($reminder->recipient, "Fee Payment Reminder", $reminder->message);
                    break;
                case 'notification':
                    $success = $this->notificationService->queueNotification($reminder->student_id, 'fee_reminder', "Fee Payment Reminder", $reminder->message);
                    break;
            }

            if ($success) {
                $reminder->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'error_message' => null
                ]);
                return true;
            } else {
                $reminder->increment('retry_count');
                $reminder->update([
                    'status' => 'failed',
                    'error_message' => 'Channel dispatch returned false.'
                ]);
                return false;
            }
        } catch (\Exception $e) {
            $reminder->increment('retry_count');
            $reminder->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Retry failed notifications where retry count is below 3.
     */
    public function retryFailedReminders()
    {
        $failedReminders = FeeReminder::where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->get();

        foreach ($failedReminders as $reminder) {
            $this->sendReminder($reminder);
        }
    }

    /**
     * Retrieve channel recipient.
     */
    protected function getRecipientForChannel(Student $student, string $channel): ?string
    {
        switch ($channel) {
            case 'sms':
            case 'whatsapp':
                return $student->mobile ?: $student->phone ?: ($student->parent->phone ?? null);
            case 'email':
                return $student->parent->email ?? ($student->user->email ?? 'parent.' . $student->id . '@example.com');
            default:
                return 'App User';
        }
    }

    /**
     * Generate template warning texts.
     */
    protected function generateMessage(StudentFeeLedger $debit, string $rule, string $channel): string
    {
        $studentName = $debit->student->name;
        $amount = number_format($debit->unpaid_amount, 2);
        $dueDate = $debit->date->format('Y-m-d');

        switch ($rule) {
            case 'Before Due Date':
                return "Dear Parent, this is an upcoming reminder that the fee of ₹{$amount} for {$studentName} is due on {$dueDate}. Kindly clear it to avoid late fees.";
            case 'After Due Date':
                return "Dear Parent, the fee of ₹{$amount} for {$studentName} was due on {$dueDate} and is now overdue. Please pay immediately.";
            case 'Repeated Reminder':
                return "Dear Parent, this is a friendly repeated notice that the outstanding fee of ₹{$amount} for {$studentName} remains unpaid. Please clear it.";
            case 'Final Notice':
                return "Dear Parent, this is the FINAL NOTICE for the outstanding fee of ₹{$amount} for {$studentName}. Avoid academic restrictions by paying immediately.";
            default:
                return "Fee reminder for {$studentName}: ₹{$amount} due.";
        }
    }
}
