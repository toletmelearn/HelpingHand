<?php

namespace App\Services\BiometricDevices;

class ZKTecoDevice extends BaseBiometricDevice
{
    protected function performConnectionTest(): bool
    {
        // Placeholder implementation - would integrate with ZKTeco SDK
        return false;
    }

    protected function performDataSync(?\DateTime $fromDate = null, ?\DateTime $toDate = null): array
    {
        // Placeholder implementation - would integrate with ZKTeco SDK
        return [];
    }
}