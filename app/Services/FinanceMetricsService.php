<?php

namespace App\Services;

use App\Models\FeeCollection;
use App\Models\FeeRefund;
use App\Models\StudentFeeLedger;
use Carbon\Carbon;

class FinanceMetricsService
{
    /**
     * Get Gross Collection amount.
     */
    public function getGrossCollection(?string $startDate = null, ?string $endDate = null, ?int $classId = null): float
    {
        $query = FeeCollection::query();

        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('payment_date', '>=', $startDate);
        }

        if ($classId) {
            $query->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        return (float) $query->sum('final_amount');
    }

    /**
     * Get Refund Total amount.
     */
    public function getRefundTotal(?string $startDate = null, ?string $endDate = null, ?int $classId = null): float
    {
        $query = FeeRefund::where('type', 'refund');

        if ($startDate && $endDate) {
            $query->whereBetween('processed_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('processed_at', '>=', $startDate);
        }

        if ($classId) {
            $query->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        return (float) $query->sum('amount');
    }

    /**
     * Get Net Collection amount.
     */
    public function getNetCollection(?string $startDate = null, ?string $endDate = null, ?int $classId = null): float
    {
        return $this->getGrossCollection($startDate, $endDate, $classId) - $this->getRefundTotal($startDate, $endDate, $classId);
    }

    /**
     * Get Outstanding Amount.
     */
    public function getOutstandingAmount(): float
    {
        $totals = StudentFeeLedger::selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')->first();
        $debit = $totals->total_debit ?? 0.00;
        $credit = $totals->total_credit ?? 0.00;

        return round($debit - $credit, 2);
    }
}
