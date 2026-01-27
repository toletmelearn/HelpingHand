<?php

namespace App\Services\BiometricDevices;

interface BiometricDeviceInterface
{
    /**
     * Test connection to the biometric device
     *
     * @return bool
     */
    public function testConnection(): bool;

    /**
     * Synchronize data from the biometric device
     *
     * @param \DateTime|null $fromDate
     * @param \DateTime|null $toDate
     * @return array Array of punch records
     */
    public function syncData(?\DateTime $fromDate = null, ?\DateTime $toDate = null): array;

    /**
     * Get device configuration requirements
     *
     * @return array
     */
    public function getConfigurationRequirements(): array;

    /**
     * Validate device configuration
     *
     * @param array $config
     * @return bool
     */
    public function validateConfiguration(array $config): bool;

    /**
     * Get device status information
     *
     * @return array
     */
    public function getStatus(): array;

    /**
     * Get last sync timestamp
     *
     * @return \DateTime|null
     */
    public function getLastSyncTimestamp(): ?\DateTime;

    /**
     * Clear device logs/data
     *
     * @return bool
     */
    public function clearDeviceData(): bool;
}