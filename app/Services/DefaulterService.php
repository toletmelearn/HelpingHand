<?php

namespace App\Services;

use App\Models\DefaulterStage;
use App\Models\DefaulterLog;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DefaulterService
{
    public static $stages = [
        'Reminder',
        'Phone Call',
        'Warning',
        'Principal Notice',
        'Exam Restriction',
        'Result Hold',
        'TC Hold'
    ];

    protected NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Synchronize and scan students for default statuses.
     */
    public function syncDefaulters()
    {
        // 1. Fetch outstanding balances for all students
        $ledgerDues = DB::table('student_fee_ledgers')
            ->select('student_id')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('student_id')
            ->get();

        $activeDefaulterIds = [];

        foreach ($ledgerDues as $due) {
            $outstanding = (float)($due->total_debit - $due->total_credit);
            if ($outstanding <= 0.01) {
                continue;
            }
            $studentId = $due->student_id;
            $activeDefaulterIds[] = $studentId;

            $defStage = DefaulterStage::where('student_id', $studentId)->first();

            if (!$defStage) {
                // Initialize at Reminder stage
                DefaulterStage::create([
                    'student_id' => $studentId,
                    'stage' => 'Reminder',
                    'outstanding_amount' => $outstanding,
                    'last_action_date' => null
                ]);
            } else {
                $updateData = ['outstanding_amount' => $outstanding];
                if ($defStage->stage === 'Cleared') {
                    // Reset to first stage
                    $updateData['stage'] = 'Reminder';
                }
                $defStage->update($updateData);
            }
        }

        // 2. Mark students who are fully paid as 'Cleared'
        $newlyClearedStudentIds = DefaulterStage::whereNotIn('student_id', $activeDefaulterIds)
            ->where('stage', '!=', 'Cleared')
            ->pluck('student_id');

        DefaulterStage::whereIn('student_id', $newlyClearedStudentIds)
            ->update([
                'stage' => 'Cleared',
                'outstanding_amount' => 0.00
            ]);

        // A student who was Exam Restricted (or beyond) and just cleared
        // their balance should get their admit card restored automatically.
        foreach ($newlyClearedStudentIds as $studentId) {
            \App\Services\ExamRestrictionService::syncAdmitCardsForStudent($studentId);
        }
    }

    /**
     * Get templates for defaulter stages.
     */
    public function getTemplate(string $stage, float $amount, string $studentName): string
    {
        $amtFormatted = number_format($amount, 2);
        switch ($stage) {
            case 'Reminder':
                return "Dear Parent, this is a reminder that fee dues of ₹{$amtFormatted} are pending for {$studentName}. Please clear them soon.";
            case 'Phone Call':
                return "Call logged regarding fee dues of ₹{$amtFormatted} for {$studentName}. Parent requested extension/clarified schedule.";
            case 'Warning':
                return "WARNING: Outstanding fee dues of ₹{$amtFormatted} for {$studentName} are severely overdue. Please clear immediately.";
            case 'Principal Notice':
                return "PRINCIPAL'S NOTICE: Final notice to settle outstanding fee dues of ₹{$amtFormatted} for {$studentName} to avoid academic restrictions.";
            case 'Exam Restriction':
                return "EXAM RESTRICTION: {$studentName} has been restricted from exams due to unpaid fee dues of ₹{$amtFormatted}.";
            case 'Result Hold':
                return "RESULT HOLD: Academic report card has been placed on hold for {$studentName} due to unpaid fee dues of ₹{$amtFormatted}.";
            case 'TC Hold':
                return "TC HOLD: Transfer Certificate request is blocked for {$studentName} due to unpaid fee dues of ₹{$amtFormatted}.";
            default:
                return "Fee dues of ₹{$amtFormatted} are outstanding for {$studentName}.";
        }
    }

    /**
     * Dispatch communication via channel (SMS, WhatsApp, Email, System Notification).
     */
    public function dispatchCommunication(int $studentId, string $channel, string $stage, ?int $performedBy = null): bool
    {
        $student = Student::findOrFail($studentId);
        $defStage = DefaulterStage::where('student_id', $studentId)->first();
        if (!$defStage) {
            return false;
        }

        $amount = (float)$defStage->outstanding_amount;
        $messageText = $this->getTemplate($stage, $amount, $student->name);

        $success = false;

        $recipientContact = $student->phone ?? '1234567890';
        $emailRecipient = $student->email ?? 'parent@school.com';

        switch (strtolower($channel)) {
            case 'sms':
                $success = $this->notificationService->sendSms($recipientContact, $messageText);
                break;
            case 'whatsapp':
                $success = $this->notificationService->sendWhatsApp($recipientContact, $messageText);
                break;
            case 'email':
                $success = $this->notificationService->sendEmail($emailRecipient, "Fee Dues Notice: " . $stage, $messageText);
                break;
            case 'notification':
                $success = $this->notificationService->queueNotification($recipientContact, 'system', "Defaulter Notification", $messageText);
                break;
            case 'phone call':
                $success = true; // Manual logs succeed immediately
                break;
        }

        // Log the action
        DefaulterLog::create([
            'student_id' => $studentId,
            'stage' => $stage,
            'action_type' => ucfirst($channel),
            'status' => $success ? 'Sent' : 'Failed',
            'details' => $messageText,
            'performed_by' => $performedBy ?? Auth::id()
        ]);

        if ($success) {
            $defStage->update([
                'last_action_date' => now()
            ]);
        }

        return $success;
    }

    /**
     * Promote a student to the next default stage.
     */
    public function promoteStage(int $studentId, ?int $performedBy = null): string
    {
        $defStage = DefaulterStage::where('student_id', $studentId)->first();
        if (!$defStage) {
            return 'Cleared';
        }

        $currentStage = $defStage->stage;
        if ($currentStage === 'Cleared') {
            $newStage = 'Reminder';
        } else {
            $idx = array_search($currentStage, self::$stages);
            if ($idx !== false && $idx < count(self::$stages) - 1) {
                $newStage = self::$stages[$idx + 1];
            } else {
                $newStage = $currentStage; // Remain at TC Hold
            }
        }

        if ($newStage !== $currentStage) {
            $defStage->update([
                'stage' => $newStage,
                'last_action_date' => now()
            ]);

            // Log status change
            DefaulterLog::create([
                'student_id' => $studentId,
                'stage' => $newStage,
                'action_type' => 'Status Change',
                'status' => 'Logged',
                'details' => "Stage transitioned from '{$currentStage}' to '{$newStage}'",
                'performed_by' => $performedBy ?? Auth::id()
            ]);

            \App\Services\ExamRestrictionService::syncAdmitCardsForStudent($studentId, $performedBy ?? Auth::id());
        }

        return $newStage;
    }

    /**
     * Manually override a student's stage.
     */
    public function overrideStage(int $studentId, string $newStage, ?string $remarks = null, ?int $performedBy = null): bool
    {
        $defStage = DefaulterStage::where('student_id', $studentId)->first();
        if (!$defStage) {
            return false;
        }

        $currentStage = $defStage->stage;
        $defStage->update([
            'stage' => $newStage,
            'remarks' => $remarks,
            'last_action_date' => now()
        ]);

        DefaulterLog::create([
            'student_id' => $studentId,
            'stage' => $newStage,
            'action_type' => 'Status Change',
            'status' => 'Logged',
            'details' => "Manual Override: Stage set to '{$newStage}'. Remarks: {$remarks}",
            'performed_by' => $performedBy ?? Auth::id()
        ]);

        \App\Services\ExamRestrictionService::syncAdmitCardsForStudent($studentId, $performedBy ?? Auth::id());

        return true;
    }
}
