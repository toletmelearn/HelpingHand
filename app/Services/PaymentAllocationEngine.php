<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\AdminConfiguration;
use App\Models\StudentFeeAssignment;
use Illuminate\Support\Facades\DB;

class PaymentAllocationEngine
{
    /**
     * Get prioritized unpaid debits for a student based on active config rules.
     */
    public static function getPrioritizedDebits(int $studentId)
    {
        $unpaidDebits = StudentFeeLedger::with('feeType')
            ->where('student_id', $studentId)
            ->where('debit', '>', 0)
            ->where('unpaid_amount', '>', 0)
            ->get();

        $policy = AdminConfiguration::get('fee', 'payment_allocation_policy', [
            'rules' => [
                ['name' => 'mandatory_first', 'enabled' => true, 'weight' => 1000],
                ['name' => 'current_session_first', 'enabled' => true, 'weight' => 500],
                ['name' => 'current_month_first', 'enabled' => true, 'weight' => 200],
                ['name' => 'priority_based', 'enabled' => true, 'weight' => 100],
                ['name' => 'oldest_due_first', 'enabled' => true, 'weight' => 50]
            ],
            'priority_list' => ['admission', 'tuition', 'late_fine', 'late_fee']
        ]);

        $rules = collect($policy['rules'] ?? []);
        $priorityList = $policy['priority_list'] ?? [];

        // Fetch active academic year safely
        $currentSession = '2026-27';
        try {
            $activeAssignment = StudentFeeAssignment::where('student_id', $studentId)
                ->latest()
                ->first();
            if ($activeAssignment) {
                $currentSession = $activeAssignment->academic_year;
            }
        } catch (\Exception $e) {
            // Table might not exist in some unit tests
        }

        $currentMonth = now()->format('F');

        $scoredDebits = $unpaidDebits->map(function ($debit) use ($rules, $priorityList, $currentSession, $currentMonth) {
            $score = 0.0;

            foreach ($rules as $rule) {
                if (empty($rule['enabled'])) {
                    continue;
                }

                $weight = (float)($rule['weight'] ?? 0);

                switch ($rule['name']) {
                    case 'mandatory_first':
                        // If feeType is null or not optional, it is mandatory
                        $isMandatory = true;
                        if ($debit->feeType && $debit->feeType->is_optional) {
                            $isMandatory = false;
                        }
                        if ($isMandatory) {
                            $score += $weight;
                        }
                        break;

                    case 'current_session_first':
                        if ($debit->academic_year === $currentSession) {
                            $score += $weight;
                        }
                        break;

                    case 'current_month_first':
                        if (stripos($debit->description, $currentMonth) !== false) {
                            $score += $weight;
                        }
                        break;

                    case 'priority_based':
                        foreach ($priorityList as $idx => $keyword) {
                            if (stripos($debit->description, $keyword) !== false || (isset($debit->reference_type) && stripos($debit->reference_type, $keyword) !== false)) {
                                // Higher priority (lower index) gets a higher priority score multiplier
                                $score += (count($priorityList) - $idx) * $weight;
                                break;
                            }
                        }
                        break;

                    case 'oldest_due_first':
                        // Older dates get higher score
                        $dateVal = $debit->date;
                        if ($dateVal instanceof \Carbon\Carbon || $dateVal instanceof \DateTime) {
                            $dateStr = $dateVal->format('Y-m-d');
                        } else {
                            $dateStr = is_string($dateVal) ? substr($dateVal, 0, 10) : '2020-01-01';
                        }
                        $debitTime = strtotime($dateStr);
                        $futureTime = strtotime('2035-12-31');
                        $daysDiff = max(0, ($futureTime - $debitTime) / 86400);
                        $score += $daysDiff * $weight;
                        break;
                }
            }

            $debit->sorting_score = $score;
            return $debit;
        });

        // Sort descending by score, stable fallback to chronological / ID
        $sorted = $scoredDebits->sort(function ($a, $b) {
            if (abs($a->sorting_score - $b->sorting_score) > 0.001) {
                return $b->sorting_score <=> $a->sorting_score;
            }
            $aDateStr = ($a->date instanceof \Carbon\Carbon || $a->date instanceof \DateTime) ? $a->date->format('Y-m-d') : (string)$a->date;
            $bDateStr = ($b->date instanceof \Carbon\Carbon || $b->date instanceof \DateTime) ? $b->date->format('Y-m-d') : (string)$b->date;
            if ($aDateStr != $bDateStr) {
                return $aDateStr <=> $bDateStr;
            }
            return $a->id <=> $b->id;
        })->values();

        foreach ($sorted as $debit) {
            unset($debit->sorting_score);
        }

        return $sorted;
    }

    /**
     * Allocate payment amount automatically based on configured rules.
     */
    public static function autoAllocate(int $studentId, float $amount): array
    {
        $prioritized = self::getPrioritizedDebits($studentId);
        $allocations = [];
        $remaining = $amount;

        foreach ($prioritized as $debit) {
            if ($remaining <= 0) {
                break;
            }

            $allocated = min((float)$debit->unpaid_amount, $remaining);
            if ($allocated > 0) {
                $allocations[] = [
                    'debit' => $debit,
                    'amount' => round($allocated, 2)
                ];
                $remaining = round($remaining - $allocated, 2);
            }
        }

        return [
            'allocations' => $allocations,
            'advance' => round($remaining, 2)
        ];
    }

    /**
     * Validate and process a manual payment allocation override.
     */
    public static function validateManualAllocation(int $studentId, float $totalAmount, array $manualAllocations): array
    {
        $errors = [];
        $allocations = [];
        $sumAllocated = 0.00;

        foreach ($manualAllocations as $debitId => $allocatedAmt) {
            $allocatedAmt = (float)$allocatedAmt;
            if ($allocatedAmt < 0) {
                $errors[] = "Allocation amount for charge ID {$debitId} cannot be negative.";
                continue;
            }

            if ($allocatedAmt === 0.00) {
                continue;
            }

            $debit = StudentFeeLedger::where('student_id', $studentId)
                ->where('id', $debitId)
                ->where('debit', '>', 0)
                ->first();

            if (!$debit) {
                $errors[] = "Invalid charge ID {$debitId} specified.";
                continue;
            }

            if ($allocatedAmt > (float)$debit->unpaid_amount) {
                $errors[] = "Allocation amount of ₹" . number_format($allocatedAmt, 2) . " for '{$debit->description}' exceeds its unpaid amount of ₹" . number_format($debit->unpaid_amount, 2) . ".";
                continue;
            }

            $allocations[] = [
                'debit' => $debit,
                'amount' => $allocatedAmt
            ];
            $sumAllocated += $allocatedAmt;
        }

        $sumAllocated = round($sumAllocated, 2);
        if ($sumAllocated > $totalAmount) {
            $errors[] = "Sum of manual allocations (₹" . number_format($sumAllocated, 2) . ") exceeds the total payment amount of ₹" . number_format($totalAmount, 2) . ".";
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'allocations' => $allocations,
            'advance' => round($totalAmount - $sumAllocated, 2)
        ];
    }
}
