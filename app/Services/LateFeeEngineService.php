<?php

namespace App\Services;

use App\Models\LateFeeRule;
use Carbon\Carbon;

class LateFeeEngineService
{
    /**
     * Calculate late fee penalty based on rule configuration.
     *
     * @param LateFeeRule $rule
     * @param string|Carbon $dueDate
     * @param string|Carbon $paymentDate
     * @param float $baseAmount
     * @return float
     */
    public function calculatePenalty(LateFeeRule $rule, $dueDate, $paymentDate, float $baseAmount = 0.00): float
    {
        $due = Carbon::parse($dueDate)->startOfDay();
        $payment = Carbon::parse($paymentDate)->startOfDay();

        // If paid on or before due date, no late fee
        if ($payment->lte($due)) {
            return 0.00;
        }

        // Calculate total elapsed days beyond the due date
        $elapsedDays = $due->diffInDays($payment);

        // If within grace days, no late fee
        if ($elapsedDays <= $rule->grace_days) {
            return 0.00;
        }

        // Calculate days actually subject to penalty
        $penaltyDays = $elapsedDays; // Some schools count from due date if grace period is breached

        $penalty = 0.00;

        switch ($rule->type) {
            case 'flat':
                $penalty = floatval($rule->amount);
                break;

            case 'daily_incremental':
                $penalty = floatval($rule->amount) * $penaltyDays;
                if ($rule->max_limit > 0) {
                    $penalty = min($penalty, floatval($rule->max_limit));
                }
                break;

            case 'slab':
                $slabs = $rule->slab_config; // Expecting array: [['days' => X, 'amount' => Y], ...]
                if (is_array($slabs) && count($slabs) > 0) {
                    // Sort slabs by days ascending
                    usort($slabs, function ($a, $b) {
                        return intval($a['days'] ?? 0) <=> intval($b['days'] ?? 0);
                    });

                    $matchedAmount = 0.00;
                    $lastAmount = 0.00;
                    $matched = false;

                    foreach ($slabs as $slab) {
                        $daysLimit = intval($slab['days'] ?? 0);
                        $amountVal = floatval($slab['amount'] ?? 0);
                        $lastAmount = $amountVal;

                        if ($elapsedDays <= $daysLimit) {
                            $matchedAmount = $amountVal;
                            $matched = true;
                            break;
                        }
                    }

                    // If elapsed days exceeds all slabs, use the highest slab amount
                    $penalty = $matched ? $matchedAmount : $lastAmount;
                } else {
                    $penalty = floatval($rule->amount);
                }
                break;
        }

        return round($penalty, 2);
    }
}
