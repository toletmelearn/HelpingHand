<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Result;
use App\Models\Student;
use App\Services\Academic\AssessmentModeratorService;
use Illuminate\Http\Request;

class MarksModerationController extends Controller
{
    protected AssessmentModeratorService $moderatorService;

    public function __construct(AssessmentModeratorService $moderatorService)
    {
        $this->moderatorService = $moderatorService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $exams = Exam::orderBy('exam_date', 'desc')->paginate(10);
        return view('admin.exams.moderation', compact('exams'));
    }

    public function moderate(Request $request)
    {
        // Bulk write across many results (no single Result instance), same
        // ability used by ResultEntryController::processBulkEntry().
        $this->authorize('create', Result::class);

        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'subject' => 'required|string',
            'adjustment_percentage' => 'required|numeric|min:-50|max:50',
            'reason' => 'nullable|string|max:255',
        ]);

        $count = $this->moderatorService->applyModeration(
            $validated['exam_id'],
            $validated['subject'],
            $validated['adjustment_percentage'],
            auth()->id() ?: 1,
            $validated['reason'] ?? 'Flat Adjustments'
        );

        return back()->with('success', "Marks moderation applied successfully to {$count} student record(s).");
    }

    public function applyGrace(Request $request)
    {
        $this->authorize('create', Result::class);

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_year' => 'required|string',
            'max_grace_marks' => 'required|integer|min:1|max:20',
        ]);

        $count = $this->moderatorService->applyGraceMarks(
            $validated['student_id'],
            $validated['academic_year'],
            $validated['max_grace_marks']
        );

        return back()->with('success', "Automated grace marks applied successfully. {$count} subject(s) updated to Pass.");
    }
}
