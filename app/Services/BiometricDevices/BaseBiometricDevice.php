<?php

namespace App\Services\BiometricDevices;

use App\Models\BiometricDevice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

abstract class BaseBiometricDevice implements BiometricDeviceInterface
{
    protected BiometricDevice $device;
    protected array $config;

    public function __construct(BiometricDevice $device)
    {
        $this->device = $device;
        $this->config = $device->device_config ?: [];
    }

    /**
     * Test connection to the biometric device
     *
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $result = $this->performConnectionTest();
            $this->device->updateStatus($result ? 'online' : 'offline');
            return $result;
        } catch (\Exception $e) {
            Log::error('Biometric device connection test failed: ' . $e->getMessage(), [
                'device_id' => $this->device->id,
                'device_name' => $this->device->name
            ]);
            $this->device->updateStatus('error', $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronize data from the biometric device
     *
     * @param \DateTime|null $fromDate
     * @param \DateTime|null $toDate
     * @return array Array of punch records
     */
    public function syncData(?\DateTime $fromDate = null, ?\DateTime $toDate = null): array
    {
        try {
            $records = $this->performDataSync($fromDate, $toDate);
            $this->device->updateStatus('online');
            return $records;
        } catch (\Exception $e) {
            Log::error('Biometric device data sync failed: ' . $e->getMessage(), [
                'device_id' => $this->device->id,
                'device_name' => $this->device->name,
                'from_date' => $fromDate?->format('Y-m-d'),
                'to_date' => $toDate?->format('Y-m-d')
            ]);
            $this->device->updateStatus('error', $e->getMessage());
            return [];
        }
    }

    /**
     * Get device configuration requirements
     *
     * @return array
     */
    public function getConfigurationRequirements(): array
    {
        return [
            'required' => ['ip_address'],
            'optional' => ['port', 'username', 'password', 'api_key'],
            'defaults' => [
                'port' => 4370,
                'timeout' => 30
            ]
        ];
    }

    /**
     * Validate device configuration
     *
     * @param array $config
     * @return bool
     */
    public function validateConfiguration(array $config): bool
    {
        $requirements = $this->getConfigurationRequirements();
        
        foreach ($requirements['required'] as $requiredField) {
            if (empty($config[$requiredField])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get device status information
     *
     * @return array
     */
    public function getStatus(): array
    {
        return [
            'device_id' => $this->device->id,
            'name' => $this->device->name,
            'status' => $this->device->status,
            'is_active' => $this->device->is_active,
            'last_sync' => $this->device->last_sync_at,
            'next_sync' => $this->device->next_sync_at,
            'retry_count' => $this->device->retry_count,
            'connection_string' => $this->device->connection_string
        ];
    }

    /**
     * Get last sync timestamp
     *
     * @return \DateTime|null
     */
    public function getLastSyncTimestamp(): ?\DateTime
    {
        return $this->device->last_sync_at;
    }

    /**
     * Clear device logs/data
     *
     * @return bool
     */
    public function clearDeviceData(): bool
    {
        try {
            $result = $this->performClearData();
            if ($result) {
                $this->device->last_sync_at = null;
                $this->device->save();
            }
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to clear biometric device data: ' . $e->getMessage(), [
                'device_id' => $this->device->id
            ]);
            return false;
        }
    }

    /**
     * Abstract method to be implemented by concrete classes
     */
    abstract protected function performConnectionTest(): bool;

    /**
     * Abstract method to be implemented by concrete classes
     */
    abstract protected function performDataSync(?\DateTime $fromDate = null, ?\DateTime $toDate = null): array;

    /**
     * Abstract method to be implemented by concrete classes (optional)
     */
    protected function performClearData(): bool
    {
        return true; // Default implementation
    }

    /**
     * Helper method to format punch records
     */
    protected function formatPunchRecord(array $rawData): array
    {
        return [
            'device_punch_id' => $rawData['punch_id'] ?? null,
            'biometric_id' => $rawData['biometric_id'] ?? null,
            'punch_time' => $rawData['punch_time'] ?? null,
            'punch_type' => $rawData['punch_type'] ?? 'in', // in/out
            'verification_type' => $rawData['verification_type'] ?? 'fingerprint',
            'raw_data' => $rawData
        ];
    }

    /**
     * Helper method to convert device time to system time
     */
    protected function convertDeviceTime(string $deviceTime): ?Carbon
    {
        try {
            return Carbon::parse($deviceTime);
        } catch (\Exception $e) {
            Log::warning('Failed to parse device time: ' . $deviceTime, [
                'device_id' => $this->device->id
            ]);
            return null;
        }
    }
}