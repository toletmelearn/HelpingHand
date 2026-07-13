<?php

namespace App\Services;

use App\Models\BankStatementRow;
use App\Models\PaymentClaim;
use App\Notifications\PaymentClaimMatchedNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors ImportConflictResolver::detectDuplicate()'s shape (exact-key
 * short-circuit + weighted/tiered scoring) rather than inventing a new
 * matching idiom -- exact/narration/fuzzy/none tiers instead of
 * duplicate/warning/new.
 */
class PaymentClaimMatchingService
{
    /**
     * Runs automatically right after a bank-statement import's execute()
     * completes, and is safely re-runnable on demand (e.g. a "Run
     * Matching" button) -- only ever touches rows still 'unmatched' and
     * claims still 'claimed' with no row already suggested, so re-running
     * never flips an existing match or suggestion.
     */
    public static function run(?int $importSessionId = null): array
    {
        $rowsQuery = BankStatementRow::where('status', 'unmatched');
        if ($importSessionId) {
            $rowsQuery->where('import_session_id', $importSessionId);
        }
        $rows = $rowsQuery->get();

        $claims = PaymentClaim::where('status', 'claimed')
            ->whereNull('bank_statement_row_id')
            ->with('student')
            ->get();

        $stats = ['exact' => 0, 'narration' => 0, 'fuzzy' => 0, 'cash_deposit' => 0, 'unmatched' => 0];

        foreach ($rows as $row) {
            $claim = static::tryExactMatch($row, $claims);
            if ($claim) {
                static::confirmExactMatch($row, $claim);
                $stats['exact']++;
                $claims = $claims->reject(fn ($c) => $c->id === $claim->id)->values();
                continue;
            }

            $claim = static::tryNarrationMatch($row, $claims);
            if ($claim) {
                static::suggestMatch($row, $claim, 'narration');
                $stats['narration']++;
                $claims = $claims->reject(fn ($c) => $c->id === $claim->id)->values();
                continue;
            }

            $claim = static::tryFuzzyMatch($row, $claims);
            if ($claim) {
                static::suggestMatch($row, $claim, 'fuzzy');
                $stats['fuzzy']++;
                $claims = $claims->reject(fn ($c) => $c->id === $claim->id)->values();
                continue;
            }

            $claim = static::tryCashDepositMatch($row, $claims);
            if ($claim) {
                static::suggestMatch($row, $claim, 'cash_deposit');
                $stats['cash_deposit']++;
                $claims = $claims->reject(fn ($c) => $c->id === $claim->id)->values();
                continue;
            }

            $stats['unmatched']++;
        }

        return $stats;
    }

    private static function amountsMatch(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    private static function tryExactMatch(BankStatementRow $row, Collection $claims): ?PaymentClaim
    {
        if (!$row->utr) {
            return null;
        }

        return $claims->first(function (PaymentClaim $claim) use ($row) {
            return $claim->utr && $claim->utr === $row->utr && static::amountsMatch((float) $claim->amount, (float) $row->amount);
        });
    }

    private static function tryNarrationMatch(BankStatementRow $row, Collection $claims): ?PaymentClaim
    {
        if (empty($row->narration)) {
            return null;
        }

        $narration = strtolower($row->narration);

        return $claims->first(function (PaymentClaim $claim) use ($narration) {
            if ($claim->reference_token && str_contains($narration, strtolower($claim->reference_token))) {
                return true;
            }
            $admissionNo = $claim->student->admission_no ?? null;
            return $admissionNo && str_contains($narration, strtolower($admissionNo));
        });
    }

    private static function tryFuzzyMatch(BankStatementRow $row, Collection $claims): ?PaymentClaim
    {
        return $claims->first(function (PaymentClaim $claim) use ($row) {
            if (!static::amountsMatch((float) $claim->amount, (float) $row->amount)) {
                return false;
            }
            if (!$claim->submitted_at) {
                return false;
            }
            return abs(Carbon::parse($claim->submitted_at)->diffInDays($row->transaction_date)) <= 3;
        });
    }

    /**
     * Cash-deposit tier -- for claim_type='bank_cash_deposit' claims only
     * (no UTR to key an exact match on). Requires amount + branch match
     * plus the transaction date within +-1 *working* day of the claimed
     * deposit_date (Mon-Sat is the working week here; Sunday is excluded
     * from the +-1 day count). Always goes through suggestMatch() below --
     * per spec this tier is NEVER auto-confirmed, even on a clean match,
     * since there's no UTR proof, only the slip photo the accountant
     * reviews manually.
     */
    private static function tryCashDepositMatch(BankStatementRow $row, Collection $claims): ?PaymentClaim
    {
        return $claims->first(function (PaymentClaim $claim) use ($row) {
            if ($claim->claim_type !== 'bank_cash_deposit') {
                return false;
            }
            if (!static::amountsMatch((float) $claim->amount, (float) $row->amount)) {
                return false;
            }
            if (!$claim->branch || !$row->branch || strcasecmp(trim($claim->branch), trim($row->branch)) !== 0) {
                return false;
            }
            if (!$claim->deposit_date) {
                return false;
            }
            return static::withinWorkingDays($claim->deposit_date, $row->transaction_date, 1);
        });
    }

    /**
     * Counts calendar days between two dates but skips Sundays when
     * measuring the distance, so a Friday deposit matched against a
     * Monday statement row (1 working day apart, skipping Sunday) still
     * counts as within range.
     */
    private static function withinWorkingDays(Carbon $a, Carbon $b, int $maxWorkingDays): bool
    {
        [$start, $end] = $a->lte($b) ? [$a->copy(), $b->copy()] : [$b->copy(), $a->copy()];

        $workingDays = 0;
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
            $cursor->addDay();
            if (!$cursor->isSunday()) {
                $workingDays++;
            }
        }

        return $workingDays <= $maxWorkingDays;
    }

    /**
     * Exact tier -- the only one that auto-confirms. Creates the real
     * FeeCollection via the same allocateOnlinePayment() Phase 1 built
     * specifically as "the shared target a real online payment method
     * will post to."
     */
    private static function confirmExactMatch(BankStatementRow $row, PaymentClaim $claim): void
    {
        DB::transaction(function () use ($row, $claim) {
            $collection = LedgerService::allocateOnlinePayment(
                $claim->student_id,
                (float) $claim->amount,
                'upi',
                ['remarks' => "UPI claim {$claim->reference_token}, UTR {$claim->utr}"]
            );

            $claim->update([
                'status' => 'matched',
                'bank_statement_row_id' => $row->id,
                'fee_collection_id' => $collection->id,
                'match_confidence' => 'exact',
                'resolved_at' => now(),
            ]);

            $row->update([
                'status' => 'matched',
                'payment_claim_id' => $claim->id,
            ]);
        });

        try {
            $parent = $claim->student->parent ?? null;
            if ($parent) {
                $parent->notify(new PaymentClaimMatchedNotification($claim));
            }
        } catch (\Throwable $e) {
            // Best-effort -- a notification failure must never undo or
            // block a payment that has already been receipted.
            Log::warning("Failed to notify parent for payment claim {$claim->id}: " . $e->getMessage());
        }
    }

    /**
     * Narration/fuzzy tiers -- pairs the row and claim as a suggestion for
     * the accountant review queue, per spec explicitly NOT auto-confirmed.
     * claim.status stays 'claimed' (still awaiting resolution); row.status
     * becomes 'suggested' so it's excluded from being suggested again
     * elsewhere and distinguishable from a fully unmatched row needing
     * manual assignment.
     */
    private static function suggestMatch(BankStatementRow $row, PaymentClaim $claim, string $confidence): void
    {
        DB::transaction(function () use ($row, $claim, $confidence) {
            $row->update([
                'status' => 'suggested',
                'payment_claim_id' => $claim->id,
            ]);

            $claim->update([
                'bank_statement_row_id' => $row->id,
                'match_confidence' => $confidence,
            ]);
        });
    }
}
