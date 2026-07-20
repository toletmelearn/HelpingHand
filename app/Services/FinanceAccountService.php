<?php

namespace App\Services;

use App\Models\StudentFeeLedger;
use App\Models\StudentFinancialAccount;
use Illuminate\Support\Facades\DB;

class FinanceAccountService
{
    /**
     * Get summary metrics for the financial account dashboard.
     */
    public static function getSummaryCards(int $studentId): array
    {
        // 1. Opening Balance (Sum of debit - credit where reference_type is opening_balance or carry_forward)
        $opening = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['opening_balance', 'carry_forward'])
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->first();
        $openingBalance = round(($opening->total_debit ?? 0.00) - ($opening->total_credit ?? 0.00), 2);

        // 2. Total Charges (excluding late fees, refunds, and opening balance/carry forward)
        $charges = StudentFeeLedger::where('student_id', $studentId)
            ->where('debit', '>', 0)
            ->whereNotIn('reference_type', ['late_fine', 'fee_refund', 'opening_balance', 'carry_forward'])
            ->sum('debit');

        // 3. Total Discounts
        $discounts = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['discount_applied', 'discount'])
            ->sum('credit');

        // 4. Total Scholarships
        $scholarships = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['scholarship_applied', 'scholarship'])
            ->sum('credit');

        // 5. Total Late Fees
        $lateFees = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['late_fine', 'late_fee'])
            ->sum('debit');

        // 6. Total Payments
        $payments = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['fee_collection', 'payment'])
            ->sum('credit');

        // 7. Total Refunds
        $refunds = StudentFeeLedger::where('student_id', $studentId)
            ->whereIn('reference_type', ['fee_refund', 'refund'])
            ->sum('debit');

        // 8. Outstanding Balance (debit - credit)
        $outstandingBalance = self::getOutstandingBalance($studentId);

        return [
            'opening_balance' => $openingBalance,
            'total_charges' => round($charges, 2),
            'total_discounts' => round($discounts, 2),
            'total_scholarships' => round($scholarships, 2),
            'total_late_fees' => round($lateFees, 2),
            'total_payments' => round($payments, 2),
            'total_refunds' => round($refunds, 2),
            'outstanding_balance' => $outstandingBalance
        ];
    }

    /**
     * Get the outstanding balance.
     */
    public static function getOutstandingBalance(int $studentId): float
    {
        return LedgerService::getOutstandingBalance($studentId);
    }

    /**
     * Get chronological timeline list of ledger entries with filtering.
     */
    public static function getLedgerTimeline(int $studentId, array $filters = [])
    {
        $query = StudentFeeLedger::where('student_id', $studentId);

        if (!empty($filters['academic_session'])) {
            $query->where('academic_year', $filters['academic_session']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['voucher_type'])) {
            $query->where('reference_type', $filters['voucher_type']);
        }

        if (!empty($filters['fee_head'])) {
            $query->where('fee_type_id', $filters['fee_head']);
        }

        return $query->orderBy('date', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Post a manual adjustment entry (credit or debit) to the ledger.
     */
    public static function postAdjustment(int $studentId, string $type, float $amount, string $description, int $userId): ?StudentFeeLedger
    {
        $date = now()->format('Y-m-d');
        if ($type === 'debit') {
            // Debit increases outstanding balance (charge)
            return LedgerService::postDebit($studentId, $date, $description, 'adjustment', $userId, $amount);
        } else {
            // Credit decreases outstanding balance (payment/credit note)
            return LedgerService::postCredit($studentId, $date, $description, 'adjustment', $userId, $amount);
        }
    }
}
