<?php

namespace App\Services;

use App\Models\FeeCollection;
use App\Models\Student;

class FinanceCalculationService
{
    /**
     * Get receipt totals including recurring, one-time, total yearly, total paid till now, and remaining fees.
     */
    public static function getReceiptTotals(FeeCollection $feeCollection): array
    {
        $student = $feeCollection->student;
        $feeStructure = $feeCollection->feeStructure;
        $academicYear = $feeStructure ? $feeStructure->academic_year : null;

        $recurringTotal = 0.00;
        $oneTimeTotal = 0.00;

        if ($feeStructure) {
            foreach ($feeStructure->feeStructureItems as $item) {
                $freq = strtolower($item->billing_frequency ?? $feeStructure->frequency ?? 'monthly');

                // This used to always multiply by a fixed 12/4/2 for
                // monthly/quarterly/half-yearly, regardless of the item's
                // actual charge_months -- the same array
                // BulkFeeAssignmentService/StructureAdjustmentService use to
                // generate the real debits. A school billing 10 months
                // (excluding a summer break) or any other custom schedule
                // had its yearly total overstated here, so "remaining fee"
                // on the printed receipt never reached zero even after
                // every actually-billed installment was paid in full.
                // Honor the real schedule when it's set; only fall back to
                // the frequency-based default (matching the same fallback
                // used when generating debits) when it isn't.
                $months = [];
                if (!empty($item->charge_months)) {
                    $months = is_array($item->charge_months) ? $item->charge_months : json_decode($item->charge_months, true);
                }

                if (!empty($months)) {
                    $multiplier = count($months);
                } elseif ($freq === 'monthly') {
                    $multiplier = 12;
                } elseif ($freq === 'quarterly') {
                    $multiplier = 4;
                } elseif ($freq === 'half_yearly' || $freq === 'half-yearly') {
                    $multiplier = 2;
                } else {
                    $multiplier = 1;
                }

                if ($freq === 'monthly') {
                    $recurringTotal += (float) ($item->amount * $multiplier);
                } else {
                    $oneTimeTotal += (float) ($item->amount * $multiplier);
                }
            }
        }

        $totalYearlyFee = $recurringTotal + $oneTimeTotal;

        // Also never subtracted approved discounts -- a fully-paid,
        // discounted student's receipt always showed a lingering
        // "remaining" balance equal to the discount, even though the ledger
        // (which does account for discounts) correctly showed zero.
        $totalDiscount = 0.00;
        if ($student && \Illuminate\Support\Facades\Schema::hasTable('student_discounts_applied')) {
            $discountQuery = \App\Models\StudentDiscountApplied::where('student_id', $student->id);
            if ($academicYear) {
                $discountQuery->where('academic_year', $academicYear);
            }
            $totalDiscount = (float) $discountQuery->sum('amount');
        }
        $totalYearlyFee = max(0.00, $totalYearlyFee - $totalDiscount);

        // Calculate total paid till now for this academic year
        $totalPaidTillNow = 0.00;
        if ($student) {
            $query = $student->feeCollections();
            if ($academicYear) {
                $query->whereHas('feeStructure', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                });
            }
            $totalPaidTillNow = (float) $query->sum('final_amount');
        }

        // Enforce explicit formula: remainingFee = totalYearlyFee - totalPaidTillNow
        $remainingFee = $totalYearlyFee - $totalPaidTillNow;

        // Calculate breakdown of paid to recurring vs one-time
        $recurringPaid = 0.00;
        $oneTimePaid = 0.00;

        if ($student) {
            $query = $student->feeCollections()->with('feeCollectionItems.feeType');
            if ($academicYear) {
                $query->whereHas('feeStructure', function ($q) use ($academicYear) {
                    $q->where('academic_year', $academicYear);
                });
            }
            $collections = $query->get();

            foreach ($collections as $col) {
                foreach ($col->feeCollectionItems as $colItem) {
                    $structItem = null;
                    if ($feeStructure) {
                        $structItem = $feeStructure->feeStructureItems()
                            ->where('fee_type_id', $colItem->fee_type_id)
                            ->first();
                    }
                    
                    $isRecurring = false;
                    if ($structItem) {
                        $freq = strtolower($structItem->billing_frequency ?? $feeStructure->frequency ?? 'monthly');
                        $isRecurring = ($freq === 'monthly');
                    } else {
                        $isRecurring = (stripos($colItem->feeType->name, 'tuition') !== false);
                    }

                    if ($isRecurring) {
                        $recurringPaid += (float) $colItem->amount;
                    } else {
                        $oneTimePaid += (float) $colItem->amount;
                    }
                }
            }
        }

        $remainingRecurring = max(0.00, $recurringTotal - $recurringPaid);
        $remainingOneTime = max(0.00, $oneTimeTotal - $oneTimePaid);

        return [
            'recurringTotal' => $recurringTotal,
            'oneTimeTotal' => $oneTimeTotal,
            'totalYearlyFee' => $totalYearlyFee,
            'totalPaidTillNow' => $totalPaidTillNow,
            'remainingFee' => $remainingFee,
            'remainingRecurring' => $remainingRecurring,
            'remainingOneTime' => $remainingOneTime,
        ];
    }
}
