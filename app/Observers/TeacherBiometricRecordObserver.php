<?php

namespace App\Observers;

use App\Models\TeacherBiometricRecord;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TeacherBiometricRecordObserver
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the TeacherBiometricRecord "created" event.
     */
    public function created(TeacherBiometricRecord $record)
    {
        $this->checkAndTriggerEvents($record);
    }

    /**
     * Handle the TeacherBiometricRecord "updated" event.
     */
    public function updated(TeacherBiometricRecord $record)
    {
        $this->checkAndTriggerEvents($record);
    }

    /**
     * Check for triggering events based on the biometric record
     */
    private function checkAndTriggerEvents(TeacherBiometricRecord $record)
    {
        $teacher = $record->teacher;
        if (!$teacher || !$teacher->working_hours_config_id) {
            return;
        }

        $scheduledStart = $teacher->getScheduledStartTime();
        $scheduledEnd = $teacher->getScheduledEndTime();

        // Check for late arrival
        if ($record->punch_type === 'in' && $scheduledStart) {
            $this->checkLateArrival($record, $teacher, $scheduledStart);
        }

        // Check for early departure
        if ($record->punch_type === 'out' && $scheduledEnd) {
            $this->checkEarlyDeparture($record, $teacher, $scheduledEnd);
        }

        // Check for attendance completeness
        $this->checkAttendanceCompleteness($record, $teacher);
    }

    /**
     * Check for late arrival and trigger notification
     */
    private function checkLateArrival($record, $teacher, $scheduledStart)
    {
        if ($record->punch_time && $record->punch_time->gt($scheduledStart)) {
            $lateMinutes = $record->punch_time->diffInMinutes($scheduledStart);
            
            // Only trigger for significant lateness (more than 5 minutes)
            if ($lateMinutes > 5) {
                $this->notificationService->sendLateArrivalNotification(
                    $teacher->id, 
                    $lateMinutes
                );
            }
        }
    }

    /**
     * Check for early departure and trigger notification
     */
    private function checkEarlyDeparture($record, $teacher, $scheduledEnd)
    {
        if ($record->punch_time && $record->punch_time->lt($scheduledEnd)) {
            $earlyMinutes = $scheduledEnd->diffInMinutes($record->punch_time);
            
            // Only trigger for significant early departure (more than 10 minutes)
            if ($earlyMinutes > 10) {
                $this->notificationService->sendEarlyDepartureNotification(
                    $teacher->id, 
                    $earlyMinutes
                );
            }
        }
    }

    /**
     * Check for attendance completeness and trigger monthly summary
     */
    private function checkAttendanceCompleteness($record, $teacher)
    {
        // Check if it's the last day of the month
        $today = Carbon::today();
        $lastDayOfMonth = $today->copy()->endOfMonth();
        
        if ($today->isSameDay($lastDayOfMonth)) {
            // Check if we haven't sent the monthly summary yet today
            $lastSummarySent = cache()->get("monthly_summary_sent_{$teacher->id}_{$today->format('Y-m')}");
            
            if (!$lastSummarySent) {
                $this->notificationService->sendMonthlySummary(
                    $teacher->id,
                    $today->month,
                    $today->year
                );
                
                // Cache to prevent multiple sends on the same day
                cache()->put("monthly_summary_sent_{$teacher->id}_{$today->format('Y-m')}", true, 86400);
            }
        }
    }

    /**
     * Handle the TeacherBiometricRecord "deleted" event.
     */
    public function deleted(TeacherBiometricRecord $record)
    {
        // Log deletion for audit purposes
        Log::info('Teacher biometric record deleted', [
            'record_id' => $record->id,
            'teacher_id' => $record->teacher_id,
            'punch_time' => $record->punch_time,
            'deleted_at' => Carbon::now()
        ]);
    }

    /**
     * Handle the TeacherBiometricRecord "restored" event.
     */
    public function restored(TeacherBiometricRecord $record)
    {
        // Log restoration for audit purposes
        Log::info('Teacher biometric record restored', [
            'record_id' => $record->id,
            'teacher_id' => $record->teacher_id,
            'punch_time' => $record->punch_time,
            'restored_at' => Carbon::now()
        ]);
    }

    /**
     * Handle the TeacherBiometricRecord "force deleted" event.
     */
    public function forceDeleted(TeacherBiometricRecord $record)
    {
        // Log permanent deletion for audit purposes
        Log::warning('Teacher biometric record permanently deleted', [
            'record_id' => $record->id,
            'teacher_id' => $record->teacher_id,
            'punch_time' => $record->punch_time,
            'force_deleted_at' => Carbon::now()
        ]);
    }
}