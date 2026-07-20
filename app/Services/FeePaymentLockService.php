<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class FeePaymentLockService
{
    /**
     * Generate a deterministic fingerprint for a payment request.
     */
    public function generateFingerprint(array $data, int $userId): string
    {
        $studentId = $data['student_id'] ?? null;
        
        // Resolve amount dynamically from possible formats
        $amount = $data['total_amount'] ?? $data['amount_paid'] ?? null;
        if ($amount === null && isset($data['amount'])) {
            if (is_array($data['amount'])) {
                $amount = array_sum(array_filter($data['amount'], fn($v) => is_numeric($v) && $v > 0));
            } else {
                $amount = $data['amount'];
            }
        }

        // Resolve fee types or installments
        $feeTypes = $data['fee_types'] ?? $data['fee_type_id'] ?? null;
        if ($feeTypes === null && isset($data['installment_number'])) {
            $feeTypes = 'inst_' . $data['installment_number'] . '_struct_' . ($data['fee_structure_id'] ?? '');
        }

        if (is_array($feeTypes)) {
            $sortedTypes = $feeTypes;
            sort($sortedTypes);
            $feeTypes = $sortedTypes;
        }

        // Resolve payment date or fallback to today
        $paymentDate = $data['payment_date'] ?? today()->format('Y-m-d');

        // Resolve payment mode
        $paymentMode = $data['payment_mode'] ?? null;

        $payload = [
            'user_id' => $userId,
            'student_id' => $studentId,
            'amount' => (float) $amount,
            'fee_types' => $feeTypes,
            'payment_date' => $paymentDate,
            'payment_mode' => $paymentMode,
        ];

        return md5(json_encode($payload));
    }

    /**
     * Attempt to acquire lock for a submission token.
     * Returns true if lock acquired, false otherwise.
     */
    public function acquireTokenLock(string $token, int $ttlSeconds = 60): bool
    {
        return Cache::add('lock:fee_token:' . $token, true, $ttlSeconds);
    }

    /**
     * Release a submission token lock.
     */
    public function releaseTokenLock(string $token): void
    {
        Cache::forget('lock:fee_token:' . $token);
    }

    /**
     * Attempt to acquire lock for a payload fingerprint.
     * Returns true if lock acquired, false otherwise.
     */
    public function acquireFingerprintLock(string $fingerprint, int $ttlSeconds = 10): bool
    {
        return Cache::add('lock:fee_fingerprint:' . $fingerprint, true, $ttlSeconds);
    }

    /**
     * Release a payload fingerprint lock.
     */
    public function releaseFingerprintLock(string $fingerprint): void
    {
        Cache::forget('lock:fee_fingerprint:' . $fingerprint);
    }
}
