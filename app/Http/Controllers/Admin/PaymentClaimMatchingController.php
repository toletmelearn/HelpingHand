<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankStatementRow;
use App\Models\PaymentClaim;
use App\Notifications\PaymentClaimMatchedNotification;
use App\Services\LedgerService;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PaymentClaimMatchingController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:accountant']);
    }

    /**
     * Tabbed queue: narration/fuzzy suggestions awaiting one-click
     * approval, plus fully unmatched claims and rows for manual pairing.
     */
    public function queue(Request $request)
    {
        $suggested = PaymentClaim::where('status', 'claimed')
            ->whereNotNull('bank_statement_row_id')
            ->with(['student', 'bankStatementRow'])
            ->latest()
            ->get();

        $unmatchedClaims = PaymentClaim::where('status', 'claimed')
            ->whereNull('bank_statement_row_id')
            ->with('student')
            ->latest()
            ->paginate(20, ['*'], 'claims_page');

        $unmatchedRows = BankStatementRow::where('status', 'unmatched')
            ->latest()
            ->paginate(20, ['*'], 'rows_page');

        return view('admin.payment-claims.queue', compact('suggested', 'unmatchedClaims', 'unmatchedRows'));
    }

    /**
     * Confirms a claim -- either a pre-suggested narration/fuzzy pairing
     * (one click, no bank_statement_row_id needed in the request) or a
     * manual pairing the accountant hand-picks (bank_statement_row_id
     * supplied). Same confirm steps as the engine's exact-tier auto-match.
     */
    public function approve(Request $request, $claimId)
    {
        $claim = PaymentClaim::with(['bankStatementRow', 'student'])->findOrFail($claimId);

        if ($claim->status !== 'claimed') {
            return redirect()->back()->with('error', 'This claim has already been resolved.');
        }

        $rowId = $request->input('bank_statement_row_id');
        $row = $rowId ? BankStatementRow::findOrFail($rowId) : $claim->bankStatementRow;

        if (!$row) {
            return redirect()->back()->with('error', 'No bank statement row specified to match against.');
        }
        if ($row->status === 'matched') {
            return redirect()->back()->with('error', 'This bank statement row is already matched to another claim.');
        }

        DB::transaction(function () use ($row, $claim) {
            $collection = LedgerService::allocateOnlinePayment(
                $claim->student_id,
                (float) $claim->amount,
                'upi',
                ['remarks' => "UPI claim {$claim->reference_token}, UTR {$claim->utr} -- accountant approved"]
            );

            $claim->update([
                'status' => 'matched',
                'bank_statement_row_id' => $row->id,
                'fee_collection_id' => $collection->id,
                'match_confidence' => $claim->match_confidence ?? 'manual',
                'resolved_by' => Auth::id(),
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
            // Best-effort -- never block a confirmed payment on a notification failure.
        }

        activity()->causedBy(Auth::user())->performedOn($claim)->log('payment_claim_approved');

        return redirect()->back()->with('success', 'Payment claim matched and receipted.');
    }

    /**
     * Handles two distinct cases behind one "reject/cancel" reason-required
     * action, mirroring FeeCollectionController::reverseCollection()'s
     * pattern (reason required, validated at the controller):
     * - Rejecting a still-'claimed' claim (a suggestion was wrong, or the
     *   parent's claim doesn't check out) -- no money has moved, just
     *   marks it rejected and frees the row back to the unmatched queue.
     * - Cancelling an already-'matched' claim -- per your explicit "match
     *   cancellation requires reason + audit log" hard rule. Reverses the
     *   real FeeCollection via the existing, tested RefundService::reverseCollection()
     *   rather than reimplementing collection reversal.
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $claim = PaymentClaim::with('bankStatementRow')->findOrFail($id);

        if (!in_array($claim->status, ['claimed', 'matched'])) {
            return redirect()->back()->with('error', 'This claim has already been rejected or cancelled.');
        }

        $wasMatched = $claim->status === 'matched';

        DB::transaction(function () use ($claim, $validated, $wasMatched) {
            if ($wasMatched && $claim->fee_collection_id) {
                RefundService::reverseCollection($claim->fee_collection_id, $validated['reason']);
            }

            if ($claim->bankStatementRow) {
                $claim->bankStatementRow->update(['status' => 'unmatched', 'payment_claim_id' => null]);
            }

            $claim->update([
                'status' => $wasMatched ? 'cancelled' : 'rejected',
                'bank_statement_row_id' => null,
                'cancellation_reason' => $validated['reason'],
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);
        });

        activity()->causedBy(Auth::user())
            ->performedOn($claim)
            ->withProperties(['reason' => $validated['reason']])
            ->log($wasMatched ? 'payment_claim_cancelled' : 'payment_claim_rejected');

        return redirect()->back()->with('success', $wasMatched ? 'Match cancelled and payment reversed.' : 'Claim rejected.');
    }
}
