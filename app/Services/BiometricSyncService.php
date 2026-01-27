<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\BiometricSyncLog;
use App\Models\TeacherBiometricRecord;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class BiometricSyncService
{
    /**
     * Synchronize all active biometric devices
     *
     * @param bool $force Force sync regardless of schedule
     * @return array Sync results
     */
    public function syncAllDevices(bool $force = false): array
    {
        $devices = $force ? 
            BiometricDevice::active()->get() : 
            BiometricDevice::needsSync()->get();

        $results = [];

        foreach ($devices as $device) {
            $results[$device->id] = $this->syncDevice($device);
        }

        return $results;
    }

    /**
     * Synchronize a specific biometric device
     *
     * @param BiometricDevice $device
     * @param \DateTime|null $fromDate
     * @param \DateTime|null $toDate
     * @return array Sync result
     */
    public function syncDevice(BiometricDevice $device, ?\DateTime $fromDate = null, ?\DateTime $toDate = null): array
    {
        $syncLog = BiometricSyncLog::startLog($device->id, $fromDate || $toDate ? 'manual' : 'scheduled');
        
        try {
            // Get device driver
            $driver = $device->getDeviceDriver();
            
            // Test connection first
            if (!$driver->testConnection()) {
                throw new \Exception('Device connection failed');
            }

            // Sync data
            $rawRecords = $driver->syncData($fromDate, $toDate);
            
            if (empty($rawRecords)) {
                $syncLog->complete(0, 0, 0, 0, 0);
                return [
                    'success' => true,
                    'records_processed' => 0,
                    'records_created' => 0,
                    'records_updated' => 0,
                    'duplicates_skipped' => 0,
                    'errors' => 0
                ];
            }

            // Process records
            $result = $this->processRawRecords($rawRecords, $device, $syncLog);
            
            $syncLog->complete(
                $result['records_processed'],
                $result['records_created'],
                $result['records_updated'],
                $result['duplicates_skipped'],
                $result['errors'],
                $result['error_details'] ?? null
            );

            return $result;

        } catch (\Exception $e) {
            Log::error('Biometric device sync failed: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'device_name' => $device->name
            ]);

            $syncLog->complete(0, 0, 0, 0, 1, $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process raw punch records and save to database
     *
     * @param array $rawRecords
     * @param BiometricDevice $device
     * @param BiometricSyncLog $syncLog
     * @return array Processing result
     */
    protected function processRawRecords(array $rawRecords, BiometricDevice $device, BiometricSyncLog $syncLog): array
    {
        $recordsProcessed = 0;
        $recordsCreated = 0;
        $recordsUpdated = 0;
        $duplicatesSkipped = 0;
        $errors = 0;
        $errorDetails = [];

        DB::beginTransaction();

        try {
            foreach ($rawRecords as $rawRecord) {
                $recordsProcessed++;

                try {
                    $result = $this->processSingleRecord($rawRecord, $device, $syncLog);
                    
                    if ($result['created']) {
                        $recordsCreated++;
                    } elseif ($result['updated']) {
                        $recordsUpdated++;
                    } elseif ($result['duplicate']) {
                        $duplicatesSkipped++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $errorDetails[] = 'Record ' . ($rawRecord['device_punch_id'] ?? 'unknown') . ': ' . $e->getMessage();
                    Log::error('Failed to process biometric record: ' . $e->getMessage(), $rawRecord);
                }
            }

            DB::commit();

            return [
                'records_processed' => $recordsProcessed,
                'records_created' => $recordsCreated,
                'records_updated' => $recordsUpdated,
                'duplicates_skipped' => $duplicatesSkipped,
                'errors' => $errors,
                'error_details' => $errors > 0 ? implode('; ', $errorDetails) : null
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process a single punch record
     *
     * @param array $rawRecord
     * @param BiometricDevice $device
     * @param BiometricSyncLog $syncLog
     * @return array Processing result
     */
    protected function processSingleRecord(array $rawRecord, BiometricDevice $device, BiometricSyncLog $syncLog): array
    {
        // Find teacher by biometric ID
        $teacher = Teacher::where('biometric_id', $rawRecord['biometric_id'])->first();
        
        if (!$teacher) {
            throw new \Exception('Teacher not found for biometric ID: ' . $rawRecord['biometric_id']);
        }

        // Check for duplicates
        $existingRecord = TeacherBiometricRecord::where('teacher_id', $teacher->id)
            ->where('date', Carbon::parse($rawRecord['punch_time'])->toDateString())
            ->first();

        if ($existingRecord) {
            // Update existing record if it's an OUT punch and we don't have one yet
            if ($rawRecord['punch_type'] === 'out' && !$existingRecord->last_out_time) {
                $existingRecord->update([
                    'last_out_time' => $rawRecord['punch_time'],
                    'biometric_device_id' => $device->id,
                    'device_punch_id' => $rawRecord['device_punch_id'],
                    'sync_timestamp' => Carbon::now(),
                    'is_synced' => true,
                    'sync_log_id' => $syncLog->id
                ]);
                
                // Recalculate duration and status
                $this->recalculateRecordMetrics($existingRecord);
                
                return ['updated' => true, 'created' => false, 'duplicate' => false];
            }
            
            return ['updated' => false, 'created' => false, 'duplicate' => true];
        }

        // Create new record
        $recordData = [
            'teacher_id' => $teacher->id,
            'date' => Carbon::parse($rawRecord['punch_time'])->toDateString(),
            'biometric_device_id' => $device->id,
            'device_punch_id' => $rawRecord['device_punch_id'],
            'sync_timestamp' => Carbon::now(),
            'is_synced' => true,
            'sync_log_id' => $syncLog->id
        ];

        if ($rawRecord['punch_type'] === 'in') {
            $recordData['first_in_time'] = $rawRecord['punch_time'];
        } else {
            $recordData['last_out_time'] = $rawRecord['punch_time'];
        }

        $record = TeacherBiometricRecord::create($recordData);
        
        // Calculate metrics
        $this->recalculateRecordMetrics($record);
        
        return ['updated' => false, 'created' => true, 'duplicate' => false];
    }

    /**
     * Recalculate record metrics (duration, status, etc.)
     *
     * @param TeacherBiometricRecord $record
     * @return void
     */
    protected function recalculateRecordMetrics(TeacherBiometricRecord $record): void
    {
        if ($record->first_in_time && $record->last_out_time) {
            // Calculate working duration
            $duration = $record->last_out_time->diffInMinutes($record->first_in_time);
            $record->calculated_duration = $duration / 60.0; // Convert to hours
            
            // Calculate lateness and early departure using existing settings
            $this->calculateArrivalDepartureStatus($record);
            
            $record->save();
        }
    }

    /**
     * Calculate arrival and departure status based on configured timings
     *
     * @param TeacherBiometricRecord $record
     * @return void
     */
    protected function calculateArrivalDepartureStatus(TeacherBiometricRecord $record): void
    {
        // This would use the existing BiometricSetting and WorkingHoursConfiguration
        // For now, using simplified logic
        $settings = \App\Models\BiometricSetting::first();
        $config = \App\Models\WorkingHoursConfiguration::first();
        
        if (!$settings || !$config) {
            return;
        }

        $startOfWorkingHours = Carbon::parse($config->start_time);
        $endOfWorkingHours = Carbon::parse($config->end_time);
        
        // Calculate late arrival
        if ($record->first_in_time) {
            $arrivalDiff = $record->first_in_time->diffInMinutes($startOfWorkingHours);
            if ($arrivalDiff > $settings->grace_period_minutes) {
                $record->arrival_status = 'late';
                $record->late_minutes = max(0, $arrivalDiff - $settings->grace_period_minutes);
                $record->grace_minutes_used = $settings->grace_period_minutes;
            } else {
                $record->arrival_status = 'on_time';
                $record->grace_minutes_used = max(0, $arrivalDiff);
                $record->late_minutes = 0;
            }
        }

        // Calculate early departure
        if ($record->last_out_time) {
            $departureDiff = $endOfWorkingHours->diffInMinutes($record->last_out_time);
            if ($departureDiff > 30) { // More than 30 minutes early
                $record->departure_status = 'early_exit';
                $record->early_departure_minutes = $departureDiff;
            } elseif ($record->calculated_duration && $record->calculated_duration < 4) { // Less than 4 hours
                $record->departure_status = 'half_day';
            } else {
                $record->departure_status = 'full_day';
                $record->early_departure_minutes = 0;
            }
        }
    }

    /**
     * Get sync statistics
     *
     * @param int|null $days Number of days to look back
     * @return array
     */
    public function getSyncStatistics(?int $days = 7): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        return [
            'total_syncs' => BiometricSyncLog::recent($days)->count(),
            'successful_syncs' => BiometricSyncLog::recent($days)->successful()->count(),
            'failed_syncs' => BiometricSyncLog::recent($days)->failed()->count(),
            'total_records_processed' => BiometricSyncLog::recent($days)->sum('records_processed'),
            'total_records_created' => BiometricSyncLog::recent($days)->sum('records_created'),
            'total_errors' => BiometricSyncLog::recent($days)->sum('errors_count'),
            'success_rate' => $this->calculateSuccessRate($days)
        ];
    }

    /**
     * Calculate sync success rate
     *
     * @param int|null $days
     * @return float
     */
    protected function calculateSuccessRate(?int $days = 7): float
    {
        $totalSyncs = BiometricSyncLog::recent($days)->count();
        if ($totalSyncs === 0) {
            return 0;
        }
        
        $successfulSyncs = BiometricSyncLog::recent($days)->successful()->count();
        return round(($successfulSyncs / $totalSyncs) * 100, 2);
    }
}