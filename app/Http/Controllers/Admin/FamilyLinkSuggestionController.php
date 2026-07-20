<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyLinkSuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The "sibling detected, admin confirms" queue -- Student::booted() only
 * ever queues a pending suggestion here, never sets family_id directly.
 */
class FamilyLinkSuggestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-families')->only(['index']);
        $this->middleware('permission:manage-families')->only(['confirm', 'dismiss']);
    }

    public function index()
    {
        // Eager-load class/section for the student and every possible
        // sibling-side entity (a single existing candidate, or every member
        // of an already-confirmed family) so the queue can show "who this
        // student would be linked to, and what class/section they're in"
        // without an N+1 per row.
        $suggestions = FamilyLinkSuggestion::with([
                'student.schoolClass',
                'student.section',
                'candidateStudent.schoolClass',
                'candidateStudent.section',
                'matchedFamily.students.schoolClass',
                'matchedFamily.students.section',
            ])
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return view('admin.family-link-suggestions.index', compact('suggestions'));
    }

    /**
     * matched_family_id set -> the candidate already belongs to a family,
     * just link this student to it. Otherwise (candidate_student_id set,
     * no family yet) -> create a new Family from the two students' shared
     * details, then link both.
     */
    public function confirm(Request $request, $id)
    {
        $suggestion = FamilyLinkSuggestion::findOrFail($id);

        if ($suggestion->status !== 'pending') {
            return redirect()->back()->with('error', 'This suggestion has already been resolved.');
        }

        DB::transaction(function () use ($suggestion) {
            $student = $suggestion->student;

            if ($suggestion->matched_family_id) {
                $student->update(['family_id' => $suggestion->matched_family_id]);
            } else {
                $candidate = $suggestion->candidateStudent;

                $family = Family::create([
                    'guardian_name' => $student->father_name ?: ($candidate->father_name ?? 'Guardian'),
                    'mobile' => $suggestion->matched_value,
                    'created_by' => Auth::id(),
                ]);

                $student->update(['family_id' => $family->id]);
                if ($candidate) {
                    $candidate->update(['family_id' => $family->id]);
                }
            }

            $suggestion->update([
                'status' => 'confirmed',
                'resolved_by' => Auth::id(),
                'resolved_at' => now(),
            ]);
        });

        activity()->causedBy(Auth::user())->performedOn($suggestion)->log('family_link_confirmed');

        return redirect()->back()->with('success', 'Family link confirmed.');
    }

    public function dismiss(Request $request, $id)
    {
        $suggestion = FamilyLinkSuggestion::findOrFail($id);

        if ($suggestion->status !== 'pending') {
            return redirect()->back()->with('error', 'This suggestion has already been resolved.');
        }

        $suggestion->update([
            'status' => 'dismissed',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        activity()->causedBy(Auth::user())->performedOn($suggestion)->log('family_link_dismissed');

        return redirect()->back()->with('success', 'Suggestion dismissed.');
    }
}
