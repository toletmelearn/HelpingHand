<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Datesheet;
use App\Models\DatesheetEntry;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Services\DatesheetConflictChecker;
use App\Services\DatesheetPublishService;
use App\Services\Exam\ExamWriteValidator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DatesheetController extends Controller
{
    public function __construct(
        private DatesheetConflictChecker $conflictChecker,
        private DatesheetPublishService $publishService,
        private ExamWriteValidator $writeValidator,
    ) {
    }

    public function index()
    {
        $this->authorize('viewAny', Datesheet::class);

        $datesheets = Datesheet::with(['academicSession'])->latest()->paginate(15);

        return view('admin.datesheets.index', compact('datesheets'));
    }

    public function create()
    {
        $this->authorize('create', Datesheet::class);

        $sessions = AcademicSession::orderByDesc('is_current')->orderByDesc('start_date')->get();
        $classes = SchoolClass::orderBy('class_order')->get();

        return view('admin.datesheets.create', compact('sessions', 'classes'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Datesheet::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'exam_type' => 'required|string|max:100',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'exists:school_classes,id',
            'section_ids' => 'nullable|array',
        ]);

        $datesheet = Datesheet::create([
            'name' => $validated['name'],
            'exam_type' => $validated['exam_type'],
            'academic_session_id' => $validated['academic_session_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'status' => Datesheet::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        foreach ($validated['class_ids'] as $classId) {
            $sectionId = $validated['section_ids'][$classId] ?? null;
            $datesheet->classes()->create([
                'school_class_id' => $classId,
                'section_id' => $sectionId ?: null,
            ]);
        }

        return redirect()->route('admin.datesheets.show', $datesheet)
            ->with('success', 'Datesheet created as a draft. Add entries, then submit for review.');
    }

    public function show(Datesheet $datesheet)
    {
        $this->authorize('view', $datesheet);

        $datesheet->load(['entries.schoolClass', 'entries.section', 'entries.subject', 'classes.schoolClass', 'classes.section', 'academicSession', 'creator', 'approver', 'publisher']);

        $classes = SchoolClass::whereIn('id', $datesheet->classes->pluck('school_class_id'))->get();
        $subjects = Subject::orderBy('name')->get();

        return view('admin.datesheets.show', compact('datesheet', 'classes', 'subjects'));
    }

    public function addEntry(Datesheet $datesheet, Request $request)
    {
        $this->authorize('update', $datesheet);

        if (! $datesheet->isEditable()) {
            return back()->with('error', 'This datesheet is no longer a draft and cannot be edited.');
        }

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'total_marks' => 'nullable|integer|min:1',
            'passing_marks' => 'nullable|integer|min:0',
            'room' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
        ]);

        $schoolClass = SchoolClass::findOrFail($validated['school_class_id']);
        $sectionId = $validated['section_id'] ?? null;

        // Sync-audit loophole L-09: total_marks/passing_marks were never
        // cross-validated here at all -- DatesheetPublishService::publish()
        // now blocks this too (whichever entry slips through), but
        // catching it here gives the admin an immediate, specific error
        // instead of a publish-time failure days later.
        $effectiveTotal = $validated['total_marks'] ?? 100;
        $effectivePassing = $validated['passing_marks'] ?? 33;
        if (!$this->writeValidator->marksValid((float) $effectiveTotal, (float) $effectivePassing)) {
            return back()->withInput()->with('error', 'Passing marks cannot be greater than total marks.');
        }

        $error = $this->conflictChecker->check(
            $datesheet,
            $schoolClass,
            $sectionId ? (int) $sectionId : null,
            (int) $validated['subject_id'],
            $validated['exam_date'],
            $validated['start_time'],
            $validated['end_time'],
            null,
            $validated['room'] ?? null,
        );

        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        $warnings = [];
        if ($this->conflictChecker->subjectHasNoAssignedTeacher($schoolClass, $sectionId ? (int) $sectionId : null, (int) $validated['subject_id'])) {
            $warnings[] = 'no teacher is currently assigned to teach this subject for this class/section';
        }
        $teachingPeriodWarning = $this->conflictChecker->teachingPeriodWarning(
            $schoolClass, $sectionId ? (int) $sectionId : null, $validated['exam_date'], $validated['start_time'], $validated['end_time']
        );
        if ($teachingPeriodWarning) {
            $warnings[] = $teachingPeriodWarning;
        }
        $warning = $warnings ? implode(' ', array_map(fn ($w) => str_starts_with($w, 'Note:') ? $w : "Note: {$w}.", $warnings)) : null;

        $datesheet->entries()->create([
            'school_class_id' => $validated['school_class_id'],
            'section_id' => $sectionId ?: null,
            'subject_id' => $validated['subject_id'],
            'exam_date' => $validated['exam_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'total_marks' => $validated['total_marks'] ?? 100,
            'passing_marks' => $validated['passing_marks'] ?? 33,
            'room' => $validated['room'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
        ]);

        // A bare 'warning' flash key is never rendered by the admin layout
        // (only 'success'/'error' are) -- folded into 'success' so the
        // admin actually sees it, rather than a message that silently
        // never displayed.
        return back()->with('success', 'Entry added.' . ($warning ? " {$warning}" : ''));
    }

    public function removeEntry(Datesheet $datesheet, DatesheetEntry $entry)
    {
        $this->authorize('update', $datesheet);

        if ($entry->datesheet_id !== $datesheet->id) {
            abort(404);
        }

        if (! $datesheet->isEditable()) {
            return back()->with('error', 'This datesheet is no longer a draft and cannot be edited.');
        }

        $entry->delete();

        return back()->with('success', 'Entry removed.');
    }

    public function submit(Datesheet $datesheet)
    {
        $this->authorize('submit', $datesheet);

        if (! $datesheet->canTransitionTo(Datesheet::STATUS_UNDER_REVIEW)) {
            return back()->with('error', 'This datesheet cannot be submitted for review from its current status.');
        }

        if ($datesheet->entries()->count() === 0) {
            return back()->with('error', 'Add at least one entry before submitting for review.');
        }

        $datesheet->update([
            'status' => Datesheet::STATUS_UNDER_REVIEW,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Submitted for review.');
    }

    public function approve(Datesheet $datesheet)
    {
        $this->authorize('approve', $datesheet);

        if (! $datesheet->canTransitionTo(Datesheet::STATUS_APPROVED)) {
            return back()->with('error', 'This datesheet cannot be approved from its current status.');
        }

        $datesheet->update([
            'status' => Datesheet::STATUS_APPROVED,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Datesheet approved.');
    }

    public function reject(Datesheet $datesheet)
    {
        $this->authorize('reject', $datesheet);

        if (! $datesheet->canTransitionTo(Datesheet::STATUS_DRAFT)) {
            return back()->with('error', 'This datesheet cannot be sent back to draft from its current status.');
        }

        $datesheet->update([
            'status' => Datesheet::STATUS_DRAFT,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Sent back to draft.');
    }

    public function publish(Datesheet $datesheet)
    {
        $this->authorize('publish', $datesheet);

        if (! $datesheet->canTransitionTo(Datesheet::STATUS_PUBLISHED)) {
            return back()->with('error', 'This datesheet cannot be published from its current status.');
        }

        try {
            $result = $this->publishService->publish($datesheet, Auth::id());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Published. {$result['exams_created']} exam(s) created, {$result['exams_linked']} linked.");
    }

    /**
     * Confirmed decision: published datesheets are immutable. A correction
     * creates a brand-new draft datesheet (cloning the classes/entries of
     * the published one as a starting point) that must go through the
     * full draft->under_review->approved->published cycle again.
     */
    public function revise(Datesheet $datesheet)
    {
        $this->authorize('revise', $datesheet);

        if ($datesheet->status !== Datesheet::STATUS_PUBLISHED) {
            return back()->with('error', 'Only a published datesheet can be revised.');
        }

        if ($datesheet->supersededBy()->exists()) {
            return back()->with('error', 'This datesheet already has a newer revision.');
        }

        $revision = Datesheet::create([
            'name' => $datesheet->name . ' (Revision)',
            'exam_type' => $datesheet->exam_type,
            'academic_session_id' => $datesheet->academic_session_id,
            'start_date' => $datesheet->start_date,
            'end_date' => $datesheet->end_date,
            'status' => Datesheet::STATUS_DRAFT,
            'created_by' => Auth::id(),
            'revises_id' => $datesheet->id,
        ]);

        foreach ($datesheet->classes as $class) {
            $revision->classes()->create([
                'school_class_id' => $class->school_class_id,
                'section_id' => $class->section_id,
            ]);
        }

        foreach ($datesheet->entries as $entry) {
            // exam_id is deliberately carried over: publishing this
            // revision will UPDATE that same Exam row's date/time rather
            // than create a duplicate one, per DatesheetPublishService.
            $revision->entries()->create([
                'school_class_id' => $entry->school_class_id,
                'section_id' => $entry->section_id,
                'subject_id' => $entry->subject_id,
                'exam_date' => $entry->exam_date,
                'start_time' => $entry->start_time,
                'end_time' => $entry->end_time,
                'total_marks' => $entry->total_marks,
                'passing_marks' => $entry->passing_marks,
                'room' => $entry->room,
                'instructions' => $entry->instructions,
                'exam_id' => $entry->exam_id,
            ]);
        }

        return redirect()->route('admin.datesheets.show', $revision)
            ->with('success', 'Revision draft created. Edit entries, then submit for review.');
    }

    /**
     * Reuses the exact TimetableController::classPdf()/masterPdf() pattern
     * (Pdf::loadView(), same DomPDF library already used for report cards
     * and admit cards) rather than inventing a new export mechanism.
     * Optional ?class_id= scopes to one class; omitted = the full
     * datesheet ("master").
     */
    public function pdf(Datesheet $datesheet, Request $request)
    {
        $this->authorize('view', $datesheet);

        $entries = $datesheet->entries()->with(['schoolClass', 'section', 'subject'])->orderBy('exam_date')->orderBy('start_time');

        if ($request->filled('class_id')) {
            $entries->where('school_class_id', $request->integer('class_id'));
        }

        $entries = $entries->get();

        $pdf = Pdf::loadView('admin.datesheets.pdf', [
            'datesheet' => $datesheet,
            'entries' => $entries,
        ]);

        $filename = 'datesheet-' . str($datesheet->name)->slug() . ($request->filled('class_id') ? '-class-' . $request->integer('class_id') : '') . '.pdf';

        return $pdf->download($filename);
    }
}
