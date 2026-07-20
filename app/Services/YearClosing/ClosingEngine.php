<?php

namespace App\Services\YearClosing;

use App\Models\FinancialYearClosing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClosingEngine
{
    public function __construct(
        private BalanceCarryForwardService $balanceService,
        private PromotionService $promotionService,
        private LedgerCarryForwardService $ledgerCarryService
    ) {}

    /**
     * Process closing for a single student.
     */
    public function closeStudent(int $studentId, int $closingId, string $fromSession, string $toSession): array
    {
        // 1. Calculate carried forward balances
        $balances = $this->balanceService->calculateBalances($studentId, $fromSession);

        // 2. Perform opening balance carryover to new ledger
        $this->ledgerCarryService->createOpeningBalances($studentId, $closingId, $fromSession, $toSession, $balances);

        return $balances;
    }
}
