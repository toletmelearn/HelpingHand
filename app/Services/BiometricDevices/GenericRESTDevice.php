<?php

namespace App\Services\BiometricDevices;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenericRESTDevice extends BaseBiometricDevice
{
    protected function performConnectionTest(): bool
    {
        if (empty($this->device->api_url)) {
            throw new \Exception('API URL is required for REST device');
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . ($this->device->api_key ?? ''),
                    'Accept' => 'application/json',
                ])
                ->get(rtrim($this->device->api_url, '/') . '/health');

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('REST device connection test failed: ' . $e->getMessage());
            return false;
        }
    }

    protected function performDataSync(?\DateTime $fromDate = null, ?\DateTime $toDate = null): array
    {
        if (empty($this->device->api_url)) {
            throw new \Exception('API URL is required for REST device');
        }

        $endpoint = rtrim($this->device->api_url, '/') . '/punch-records';
        $params = [];

        if ($fromDate) {
            $params['from_date'] = $fromDate->format('Y-m-d');
        }

        if ($toDate) {
            $params['to_date'] = $toDate->format('Y-m-d');
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . ($this->device->api_key ?? ''),
                    'Accept' => 'application/json',
                ])
                ->get($endpoint, $params);

            if (!$response->successful()) {
                throw new \Exception('API request failed with status: ' . $response->status());
            }

            $data = $response->json();
            $records = [];

            foreach ($data['records'] ?? [] as $record) {
                $records[] = $this->formatPunchRecord($record);
            }

            return $records;
        } catch (\Exception $e) {
            Log::error('REST device data sync failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getConfigurationRequirements(): array
    {
        return [
            'required' => ['api_url'],
            'optional' => ['api_key', 'username', 'password'],
            'defaults' => [
                'timeout' => 30
            ]
        ];
    }

    public function validateConfiguration(array $config): bool
    {
        if (!parent::validateConfiguration($config)) {
            return false;
        }

        // Validate API URL format
        if (!filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
            return false;
        }

        return true;
    }

    protected function formatPunchRecord(array $rawData): array
    {
        $formatted = parent::formatPunchRecord($rawData);
        
        // Map common REST API fields
        $formatted['biometric_id'] = $rawData['employee_id'] ?? $rawData['user_id'] ?? null;
        $formatted['punch_time'] = $this->convertDeviceTime($rawData['timestamp'] ?? $rawData['punch_time'] ?? '');
        $formatted['punch_type'] = strtolower($rawData['type'] ?? $rawData['punch_type'] ?? 'in');
        $formatted['verification_type'] = $rawData['verification_method'] ?? $rawData['auth_type'] ?? 'unknown';

        return $formatted;
    }
}