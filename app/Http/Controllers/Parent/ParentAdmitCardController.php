<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\AdmitCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

/**
 * Admit Card workflow: no parent-facing admit card route existed at all
 * before this (confirmed this session's own earlier audit). Mirrors
 * StudentAdmitCardController's exact ownership+status pattern (confirmed
 * IDOR-safe) rather than inventing a new one -- $parent->student, same
 * published/locked-only visibility, same 403-on-mismatch, same
 * revoked-is-never-visible-even-by-direct-URL check.
 */
class ParentAdmitCardController extends Controller
{
    public function index()
    {
        $parent = Auth::guard('parent')->user();

        if (! $parent || ! $parent->student) {
            return redirect()->back()->with('error', 'No student associated with this parent account.');
        }

        $admitCards = AdmitCard::with(['exam', 'format'])
            ->where('student_id', $parent->student->id)
            ->whereIn('status', ['published', 'locked'])
            ->latest()
            ->get();

        return view('parent.admit-cards.index', compact('admitCards'));
    }

    public function show(AdmitCard $admitCard)
    {
        $parent = Auth::guard('parent')->user();

        if (! $parent || ! $parent->student || $admitCard->student_id !== $parent->student->id) {
            abort(403, 'Unauthorized to view this admit card.');
        }

        if (! in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available yet or has been revoked.');
        }

        return view('parent.admit-cards.show', compact('admitCard'));
    }

    public function downloadPdf(AdmitCard $admitCard)
    {
        $parent = Auth::guard('parent')->user();

        if (! $parent || ! $parent->student || $admitCard->student_id !== $parent->student->id) {
            abort(403, 'Unauthorized to download this admit card.');
        }

        if (! in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available for download or has been revoked.');
        }

        $pdf = Pdf::loadView('student.admit-cards.pdf', compact('admitCard'));

        return $pdf->download("admit-card-{$admitCard->id}.pdf");
    }
}
