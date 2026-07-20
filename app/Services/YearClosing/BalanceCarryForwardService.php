<?php

namespace App\Services\YearClosing;

use Illuminate\Support\Facades\DB;

class BalanceCarryForwardService
{
    /**
     * Compute carried forward balances for a student from an academic session.
     */
    public function calculateBalances(int $studentId, string $fromSession): array
    {
        // 1. Arrears (debits excluding refunds and closing entries)
        $arrears = (float) DB::table('student_fee_ledgers')
            ->where('student_id', $studentId)
            ->where('academic_year', $fromSession)
            ->where('debit', '>', 0)
            ->whereNotIn('reference_type', ['fee_refund', 'refund', 'year_closing'])
            ->sum('unpaid_amount');

        // 2. Refunds (refund debits)
        $refunds = (float) DB::table('student_fee_ledgers')
            ->where('student_id', $studentId)
            ->where('academic_year', $fromSession)
            ->where('debit', '>', 0)
            ->whereIn('reference_type', ['fee_refund', 'refund'])
            ->sum('unpaid_amount');

        // 3. Scholarships (credits of type scholarship/scholarship_applied)
        $scholarshipCredits = DB::table('student_fee_ledgers')
            ->where('student_id', $studentId)
            ->where('academic_year', $fromSession)
            ->where('credit', '>', 0)
            ->whereIn('reference_type', ['scholarship_applied', 'scholarship'])
            ->get();

        $scholarships = 0.00;
        foreach ($scholarshipCredits as $credit) {
            $allocated = (float) DB::table('payment_allocations')
                ->where('credit_ledger_id', $credit->id)
                ->sum('amount');
            $unallocated = max(0.00, (float)$credit->credit - $allocated);
            $scholarships += $unallocated;
        }

        // 4. Advances (credits of other types)
        $advanceCredits = DB::table('student_fee_ledgers')
            ->where('student_id', $studentId)
            ->where('academic_year', $fromSession)
            ->where('credit', '>', 0)
            ->whereNotIn('reference_type', ['scholarship_applied', 'scholarship'])
            ->get();

        $advances = 0.00;
        foreach ($advanceCredits as $credit) {
            $allocated = (float) DB::table('payment_allocations')
                ->where('credit_ledger_id', $credit->id)
                ->sum('amount');
            $unallocated = max(0.00, (float)$credit->credit - $allocated);
            $advances += $unallocated;
        }

        return [
            'arrears' => round($arrears, 2),
            'refunds' => round($refunds, 2),
            'scholarships' => round($scholarships, 2),
            'advances' => round($advances, 2),
        ];
    }
}
