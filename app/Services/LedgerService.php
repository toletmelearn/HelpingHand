<?php

namespace App\Services;

use App\Models\StudentFeeLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LedgerService
{
    public static bool $skipAutoAllocation = false;
    public static ?array $manualAllocationsToRegister = null;

    /**
     * Post a debit entry to the student ledger (increases due).
     */
    public static function postDebit(int $studentId, string $date, string $description, string $referenceType, int $referenceId, ?float $amount): ?StudentFeeLedger
    {
        return app(self::class)->postDebitInstance($studentId, $date, $description, $referenceType, $referenceId, $amount);
    }

    public function postDebitInstance(int $studentId, string $date, string $description, string $referenceType, int $referenceId, ?float $amount): ?StudentFeeLedger
    {
        try {
            return $this->createEntryInstance($studentId, $date, $description, $referenceType, $referenceId, (float)$amount, 0.00);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Post a credit entry to the student ledger (decreases due/records payment).
     */
    public static function postCredit(int $studentId, string $date, string $description, string $referenceType, int $referenceId, ?float $amount): ?StudentFeeLedger
    {
        return app(self::class)->postCreditInstance($studentId, $date, $description, $referenceType, $referenceId, $amount);
    }

    public function postCreditInstance(int $studentId, string $date, string $description, string $referenceType, int $referenceId, ?float $amount): ?StudentFeeLedger
    {
        try {
            return $this->createEntryInstance($studentId, $date, $description, $referenceType, $referenceId, 0.00, (float)$amount);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the outstanding dues balance of a student.
     */
    public static function getOutstandingBalance(int $studentId): float
    {
        return app(self::class)->getOutstandingBalanceInstance($studentId);
    }

    public function getOutstandingBalanceInstance(int $studentId): float
    {
        try {
            // Formula: Sum(debit) - Sum(credit)
            $totals = StudentFeeLedger::where('student_id', $studentId)
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();

            $debit = $totals->total_debit ?? 0.00;
            $credit = $totals->total_credit ?? 0.00;

            return round($debit - $credit, 2);
        } catch (\Exception $e) {
            return 0.00;
        }
    }

    /**
     * Get the ledger trail for a student.
     */
    public static function getLedger(int $studentId)
    {
        return app(self::class)->getLedgerInstance($studentId);
    }

    public function getLedgerInstance(int $studentId)
    {
        try {
            return StudentFeeLedger::where('student_id', $studentId)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    /**
     * Create ledger entry with database locking and transaction.
     */
    private function createEntryInstance(int $studentId, string $date, string $description, string $referenceType, int $referenceId, float $debit, float $credit): StudentFeeLedger
    {
        return DB::transaction(function () use ($studentId, $date, $description, $referenceType, $referenceId, $debit, $credit) {
            // Lock the parent Student record to prevent concurrent first-ledger-entry inserts
            $student = \App\Models\Student::where('id', $studentId)->lockForUpdate()->first();

            // Get the last running balance with raw database lock for update
            $lastEntry = StudentFeeLedger::where('student_id', $studentId)
                ->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $previousBalance = $lastEntry ? $lastEntry->running_balance : 0.00;
            $newBalance = $previousBalance + $debit - $credit;

            // Resolve tags for the ledger entry
            $academicYear = null;
            $classId = null;
            $feeTypeId = null;

            if ($referenceType === 'fee_structure_item') {
                try {
                    $item = \App\Models\FeeStructureItem::with('feeStructure')->find($referenceId);
                    if ($item) {
                        $feeTypeId = $item->fee_type_id;
                        if ($item->feeStructure) {
                            $academicYear = $item->feeStructure->academic_year;
                            try {
                                $classVal = \App\Models\SchoolClass::where('name', $item->feeStructure->class_name)->first();
                                if ($classVal) {
                                    $classId = $classVal->id;
                                }
                            } catch (\Exception $e) {
                                // Ignore missing school_classes table
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore missing tables
                }
            } elseif ($referenceType === 'fee_collection') {
                try {
                    $collection = \App\Models\FeeCollection::with('feeStructure')->find($referenceId);
                    if ($collection) {
                        if ($collection->feeStructure) {
                            $academicYear = $collection->feeStructure->academic_year;
                            try {
                                $classVal = \App\Models\SchoolClass::where('name', $collection->feeStructure->class_name)->first();
                                if ($classVal) {
                                    $classId = $classVal->id;
                                }
                            } catch (\Exception $e) {
                                // Ignore missing school_classes table
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore missing tables
                }
            } elseif ($referenceType === 'fee_refund') {
                try {
                    $refund = \App\Models\FeeRefund::find($referenceId);
                    if ($refund && $refund->fee_collection_id) {
                        $collection = \App\Models\FeeCollection::with('feeStructure')->find($refund->fee_collection_id);
                        if ($collection && $collection->feeStructure) {
                            $academicYear = $collection->feeStructure->academic_year;
                            try {
                                $classVal = \App\Models\SchoolClass::where('name', $collection->feeStructure->class_name)->first();
                                if ($classVal) {
                                    $classId = $classVal->id;
                                }
                            } catch (\Exception $e) {
                                // Ignore missing school_classes table
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore missing fee_refunds or related tables
                }
            }

            if ($referenceType !== 'year_closing') {
                \App\Services\FinancialYearClosingService::checkIfSessionIsClosed($academicYear);
            }

            $ledgerEntry = StudentFeeLedger::create([
                'student_id' => $studentId,
                'date' => $date,
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => round($newBalance, 2),
                'academic_year' => $academicYear,
                'class_id' => $classId,
                'fee_type_id' => $feeTypeId,
                'unpaid_amount' => $debit,
            ]);

            // Allocate credit if applicable
            if ($credit > 0) {
                if (self::$manualAllocationsToRegister !== null) {
                    $this->registerManualAllocationsInstance($studentId, $ledgerEntry->id, self::$manualAllocationsToRegister);
                    self::$manualAllocationsToRegister = null;
                } else {
                    $this->allocateCreditFIFOInstance($studentId, $credit, $ledgerEntry->id);
                }
            }

            // Apply any existing unallocated credits (advance payments) to this new debit
            if ($debit > 0) {
                $this->rebuildUnpaidAmountsInstance($studentId);
            }

            return $ledgerEntry;
        });
    }

    /**
     * Register manual allocations for a payment.
     */
    public static function registerManualAllocations(int $studentId, int $creditLedgerId, array $allocations): void
    {
        app(self::class)->registerManualAllocationsInstance($studentId, $creditLedgerId, $allocations);
    }

    public function registerManualAllocationsInstance(int $studentId, int $creditLedgerId, array $allocations): void
    {
        $hasAllocationsTable = Schema::hasTable('payment_allocations');
        foreach ($allocations as $allocated) {
            $debit = $allocated['debit'];
            $amount = (float)$allocated['amount'];

            if ($amount > 0) {
                $debit->unpaid_amount = round(max(0.00, (float)$debit->unpaid_amount - $amount), 2);
                $debit->save();

                if ($hasAllocationsTable) {
                    // Create the PaymentAllocation record
                    \App\Models\PaymentAllocation::updateOrCreate(
                        [
                            'credit_ledger_id' => $creditLedgerId,
                            'debit_ledger_id' => $debit->id,
                        ],
                        [
                            'student_id' => $studentId,
                            'amount' => $amount,
                            'allocation_type' => 'manual'
                        ]
                    );
                }
            }
        }
    }

    /**
     * Allocate credit to unpaid debits in FIFO order.
     */
    public static function allocateCreditFIFO(int $studentId, float $creditAmount, ?int $creditLedgerId = null): void
    {
        app(self::class)->allocateCreditFIFOInstance($studentId, $creditAmount, $creditLedgerId);
    }

    public function allocateCreditFIFOInstance(int $studentId, float $creditAmount, ?int $creditLedgerId = null): void
    {
        if (self::$skipAutoAllocation) {
            return;
        }

        $hasAllocationsTable = Schema::hasTable('payment_allocations');

        if ($hasAllocationsTable && $creditLedgerId === null) {
            $creditLedger = StudentFeeLedger::where('student_id', $studentId)
                ->where('credit', '>', 0)
                ->orderBy('id', 'desc')
                ->first();
            $creditLedgerId = $creditLedger ? $creditLedger->id : null;
        }

        if ($hasAllocationsTable && !$creditLedgerId) {
            return;
        }

        $unpaidDebits = \App\Services\PaymentAllocationEngine::getPrioritizedDebits($studentId);

        $remainingCredit = $creditAmount;
        foreach ($unpaidDebits as $debit) {
            if ($remainingCredit <= 0) break;

            $allocation = min((float)$debit->unpaid_amount, $remainingCredit);
            if ($allocation > 0) {
                $debit->unpaid_amount = round(max(0.00, (float)$debit->unpaid_amount - $allocation), 2);
                $debit->save();

                if ($hasAllocationsTable) {
                    // Create the PaymentAllocation record
                    \App\Models\PaymentAllocation::updateOrCreate(
                        [
                            'credit_ledger_id' => $creditLedgerId,
                            'debit_ledger_id' => $debit->id,
                        ],
                        [
                            'student_id' => $studentId,
                            'amount' => $allocation,
                            'allocation_type' => 'auto'
                        ]
                    );
                }

                $remainingCredit = round($remainingCredit - $allocation, 2);
            }
        }
    }

    /**
     * Rebuild unpaid amounts for all debits of a student chronologically, preserving manual overrides.
     */
    public static function rebuildUnpaidAmounts(int $studentId): void
    {
        app(self::class)->rebuildUnpaidAmountsInstance($studentId);
    }

    public function rebuildUnpaidAmountsInstance(int $studentId): void
    {
        DB::transaction(function () use ($studentId) {
            // Find IDs of soft-deleted fee collections for this student
            $reversedCollectionIds = \App\Models\FeeCollection::onlyTrashed()
                ->where('student_id', $studentId)
                ->pluck('id')
                ->toArray();

            // Reset all debits' unpaid_amount = debit
            StudentFeeLedger::where('student_id', $studentId)
                ->where('debit', '>', 0)
                ->lockForUpdate()
                ->update(['unpaid_amount' => DB::raw('debit')]);

            // Set unpaid_amount to 0 for reversing debits linked to reversed collections
            if (!empty($reversedCollectionIds)) {
                StudentFeeLedger::where('student_id', $studentId)
                    ->where('debit', '>', 0)
                    ->where('reference_type', 'fee_collection')
                    ->whereIn('reference_id', $reversedCollectionIds)
                    ->lockForUpdate()
                    ->update(['unpaid_amount' => 0.00]);
            }

            // Find all active credits for this student
            $credits = StudentFeeLedger::where('student_id', $studentId)
                ->where('credit', '>', 0)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            if (!Schema::hasTable('payment_allocations')) {
                // FALLBACK: run legacy chronological auto-allocation
                foreach ($credits as $credit) {
                    if ($credit->reference_type === 'fee_collection' && in_array($credit->reference_id, $reversedCollectionIds)) {
                        $credit->unpaid_amount = 0.00;
                        $credit->save();
                        continue;
                    }
                    $this->allocateCreditFIFOInstance($studentId, (float)$credit->credit);
                }
                return;
            }

            // Get reversed ledger entry IDs
            $reversedLedgerIds = StudentFeeLedger::where('student_id', $studentId)
                ->where('reference_type', 'fee_collection')
                ->whereIn('reference_id', $reversedCollectionIds)
                ->pluck('id')
                ->toArray();

            // Delete allocations associated with reversed collections/credits
            if (!empty($reversedLedgerIds)) {
                \App\Models\PaymentAllocation::whereIn('credit_ledger_id', $reversedLedgerIds)
                    ->orWhereIn('debit_ledger_id', $reversedLedgerIds)
                    ->delete();
            }

            // Delete all 'auto' allocations for active credits
            $activeCreditIds = $credits->pluck('id')->toArray();
            \App\Models\PaymentAllocation::whereIn('credit_ledger_id', $activeCreditIds)
                ->where('allocation_type', 'auto')
                ->delete();

            // Apply preserved manual allocations first
            $manualAllocations = \App\Models\PaymentAllocation::whereIn('credit_ledger_id', $activeCreditIds)
                ->where('allocation_type', 'manual')
                ->get();

            foreach ($manualAllocations as $allocation) {
                $debit = StudentFeeLedger::find($allocation->debit_ledger_id);
                if ($debit) {
                    $debit->unpaid_amount = round(max(0.00, (float)$debit->unpaid_amount - (float)$allocation->amount), 2);
                    $debit->save();
                }
            }

            // Auto-allocate payments that don't have manual overrides
            foreach ($credits as $credit) {
                if (in_array($credit->id, $reversedLedgerIds)) {
                    $credit->unpaid_amount = 0.00;
                    $credit->save();
                    continue;
                }

                // Check if there are any manual allocations for this credit
                $hasManual = \App\Models\PaymentAllocation::where('credit_ledger_id', $credit->id)
                    ->where('allocation_type', 'manual')
                    ->exists();

                if (!$hasManual) {
                    $this->allocateCreditFIFOInstance($studentId, (float)$credit->credit, $credit->id);
                }
            }
        });
    }

    /**
     * Rebuild the entire ledger running balances for a student from scratch.
     * Useful for historical correction.
     */
    public static function rebuildRunningBalances(int $studentId): void
    {
        app(self::class)->rebuildRunningBalancesInstance($studentId);
    }

    public function rebuildRunningBalancesInstance(int $studentId): void
    {
        try {
            DB::transaction(function () use ($studentId) {
                $entries = StudentFeeLedger::where('student_id', $studentId)
                    ->orderBy('date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $runningBalance = 0.00;
                foreach ($entries as $entry) {
                    $runningBalance += $entry->debit - $entry->credit;
                    $entry->running_balance = round($runningBalance, 2);
                    $entry->save();
                }

                // Rebuild unpaid amounts FIFO allocations
                $this->rebuildUnpaidAmountsInstance($studentId);
            });
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Record an online/offline payment not tied to a counter-collection form:
     * creates the FeeCollection (whose model hook posts the ledger credit
     * and runs FIFO allocation via allocateCreditFIFOInstance), then derives
     * FeeCollectionItem breakdown rows from the resulting PaymentAllocation
     * records -- reusing the real allocation engine instead of re-deriving
     * FIFO order by hand. This is the shared core for both the parent
     * portal's stopgap payment recording and any future automated
     * reconciliation flow (e.g. UPI bank-statement matching).
     */
    public static function allocateOnlinePayment(int $studentId, float $amount, string $paymentMode, array $meta = []): \App\Models\FeeCollection
    {
        return app(self::class)->allocateOnlinePaymentInstance($studentId, $amount, $paymentMode, $meta);
    }

    public function allocateOnlinePaymentInstance(int $studentId, float $amount, string $paymentMode, array $meta = []): \App\Models\FeeCollection
    {
        return DB::transaction(function () use ($studentId, $amount, $paymentMode, $meta) {
            $collectorId = $meta['collected_by'] ?? (\App\Models\User::first()->id ?? 1);

            // fee_collections.fee_structure_id is NOT NULL. Every caller
            // this method originally had (ParentPaymentController::callbackSuccess())
            // always had a specific fee_structure_id from the request. A
            // UPI bank-transfer payment doesn't necessarily map to one --
            // it settles whatever dues are outstanding, possibly spanning
            // more than one structure -- so fall back to the student's
            // most recent assignment rather than requiring every caller to
            // resolve one themselves.
            $feeStructureId = $meta['fee_structure_id']
                ?? \App\Models\StudentFeeAssignment::where('student_id', $studentId)->latest('id')->value('fee_structure_id');

            $collection = \App\Models\FeeCollection::create([
                'receipt_no' => $meta['receipt_no'] ?? \App\Services\FeeReceiptNumberService::generateNextReceiptNumber(),
                'student_id' => $studentId,
                'fee_structure_id' => $feeStructureId,
                'total_amount' => $amount,
                'discount' => 0,
                'late_fine' => 0,
                'final_amount' => $amount,
                'payment_date' => now()->format('Y-m-d'),
                'payment_mode' => $paymentMode,
                'remarks' => $meta['remarks'] ?? 'Online payment',
                'collected_by' => $collectorId,
            ]);

            $creditEntry = StudentFeeLedger::where('student_id', $studentId)
                ->where('reference_type', 'fee_collection')
                ->where('reference_id', $collection->id)
                ->where('credit', '>', 0)
                ->first();

            if ($creditEntry && Schema::hasTable('payment_allocations')) {
                $allocations = \App\Models\PaymentAllocation::where('credit_ledger_id', $creditEntry->id)->get();
                foreach ($allocations as $allocation) {
                    $debit = StudentFeeLedger::find($allocation->debit_ledger_id);
                    if ($debit && $debit->fee_type_id) {
                        \App\Models\FeeCollectionItem::create([
                            'fee_collection_id' => $collection->id,
                            'fee_type_id' => $debit->fee_type_id,
                            'amount' => $allocation->amount,
                        ]);
                    }
                }
            }

            return $collection;
        });
    }

    /**
     * Get consolidated family ledger for a student and their siblings.
     */
    public static function getFamilyLedger(int $studentId)
    {
        return app(self::class)->getFamilyLedgerInstance($studentId);
    }

    public function getFamilyLedgerInstance(int $studentId)
    {
        try {
            $student = \App\Models\Student::withTrashed()->findOrFail($studentId);

            if (empty($student->father_name) || empty($student->mobile)) {
                return $this->getLedgerInstance($studentId);
            }

            $siblingsIds = \App\Models\Student::withTrashed()
                ->where('father_name', $student->father_name)
                ->where('mobile', $student->mobile)
                ->pluck('id');

            $entries = StudentFeeLedger::with('student')
                ->whereIn('student_id', $siblingsIds)
                ->orderBy('date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $runningBalance = 0.00;
            foreach ($entries as $entry) {
                $runningBalance += $entry->debit - $entry->credit;
                $entry->running_balance = round($runningBalance, 2);
            }

            return $entries;
        } catch (\Exception $e) {
            return collect();
        }
    }
}
