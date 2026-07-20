<?php

namespace App\Services\YearClosing;

use App\Models\StudentFeeLedger;
use App\Services\LedgerService;

class LedgerCarryForwardService
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Create opening balance ledger entries in the new session.
     */
    public function createOpeningBalances(int $studentId, int $closingId, string $fromSession, string $toSession, array $balances): void
    {
        $arrears = $balances['arrears'] ?? 0.00;
        $refunds = $balances['refunds'] ?? 0.00;
        $scholarships = $balances['scholarships'] ?? 0.00;
        $advances = $balances['advances'] ?? 0.00;

        if ($arrears > 0) {
            StudentFeeLedger::create([
                'student_id' => $studentId,
                'date' => today()->toDateString(),
                'description' => "Opening Balance (Arrears from Session {$fromSession})",
                'reference_type' => 'year_closing',
                'reference_id' => $closingId,
                'debit' => $arrears,
                'credit' => 0.00,
                'running_balance' => 0.00, // rebuilt below
                'academic_year' => $toSession,
                'unpaid_amount' => $arrears,
            ]);
        }

        if ($refunds > 0) {
            StudentFeeLedger::create([
                'student_id' => $studentId,
                'date' => today()->toDateString(),
                'description' => "Opening Balance (Refund from Session {$fromSession})",
                'reference_type' => 'year_closing',
                'reference_id' => $closingId,
                'debit' => $refunds,
                'credit' => 0.00,
                'running_balance' => 0.00, // rebuilt below
                'academic_year' => $toSession,
                'unpaid_amount' => $refunds,
            ]);
        }

        if ($scholarships > 0) {
            StudentFeeLedger::create([
                'student_id' => $studentId,
                'date' => today()->toDateString(),
                'description' => "Opening Balance (Scholarship from Session {$fromSession})",
                'reference_type' => 'year_closing',
                'reference_id' => $closingId,
                'debit' => 0.00,
                'credit' => $scholarships,
                'running_balance' => 0.00, // rebuilt below
                'academic_year' => $toSession,
                'unpaid_amount' => 0.00,
            ]);
        }

        if ($advances > 0) {
            StudentFeeLedger::create([
                'student_id' => $studentId,
                'date' => today()->toDateString(),
                'description' => "Opening Balance (Advance from Session {$fromSession})",
                'reference_type' => 'year_closing',
                'reference_id' => $closingId,
                'debit' => 0.00,
                'credit' => $advances,
                'running_balance' => 0.00, // rebuilt below
                'academic_year' => $toSession,
                'unpaid_amount' => 0.00,
            ]);
        }

        if ($arrears > 0 || $refunds > 0 || $scholarships > 0 || $advances > 0) {
            // Rebuild running balances & FIFO allocations for the student in the new year
            $this->ledgerService->rebuildRunningBalancesInstance($studentId);
        }
    }
}
