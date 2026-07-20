<?php

namespace App\Services;

use App\Models\FeeCollection;
use App\Models\FeeRefund;
use App\Models\Student;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RefundService
{
    /**
     * Reverse a full collection (e.g. bounced cheque, clerk error).
     */
    public static function reverseCollection(int $collectionId, string $reason): ?FeeRefund
    {
        return DB::transaction(function () use ($collectionId, $reason) {
            $collection = FeeCollection::findOrFail($collectionId);

            $academicYear = null;
            if ($collection->fee_structure_id) {
                if (\Illuminate\Support\Facades\Schema::hasTable('fee_structures')) {
                    $academicYear = DB::table('fee_structures')
                        ->where('id', $collection->fee_structure_id)
                        ->value('academic_year');
                }
            }
            FinancialYearClosingService::checkIfSessionIsClosed($academicYear);

            // 1. Mark collection as cancelled/reversed
            // Altering the status to cancelled (which is already in the enum)
            $collection->update(['remarks' => trim($collection->remarks . ' [REVERSED: ' . $reason . ']')]);
            $collection->delete(); // Soft delete it! This triggers the deleted event on FeeCollection model, which automatically deletes its credit ledger entries!

            $processedBy = Auth::id() ?? 1;

            // 2. Create Reversal Audit Record
            $refund = FeeRefund::create([
                'student_id' => $collection->student_id,
                'fee_collection_id' => $collection->id,
                'amount' => $collection->final_amount,
                'type' => 'reversal',
                'reason' => $reason,
                'payment_mode' => $collection->payment_mode,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            return $refund;
        });
    }

    /**
     * Issue a partial or full refund to a student (e.g. caution money refund, excess fee refund).
     */
    public static function issueRefund(int $studentId, ?int $collectionId, float $amount, string $paymentMode, string $reason): ?FeeRefund
    {
        return DB::transaction(function () use ($studentId, $collectionId, $amount, $paymentMode, $reason) {
            $student = Student::withTrashed()->findOrFail($studentId);
            $processedBy = Auth::id() ?? 1;

            // 1. Create Refund Record
            $refund = FeeRefund::create([
                'student_id' => $studentId,
                'fee_collection_id' => $collectionId,
                'amount' => $amount,
                'type' => 'refund',
                'reason' => $reason,
                'payment_mode' => $paymentMode,
                'processed_by' => $processedBy,
                'processed_at' => now(),
            ]);

            // 2. Post a DEBIT entry (cash outflow from school) to the student ledger
            LedgerService::postDebit(
                $studentId,
                now()->format('Y-m-d'),
                "Refund Issued: " . ($reason ?: "Excess Fee Refund"),
                'fee_refund',
                $refund->id,
                $amount
            );

            // Rebuild the ledger running balances & FIFO open items
            LedgerService::rebuildRunningBalances($studentId);

            return $refund;
        });
    }
}
