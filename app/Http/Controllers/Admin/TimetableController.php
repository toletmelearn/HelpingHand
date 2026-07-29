<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateTimetableJob;
use App\Models\AcademicSession;
use App\Models\CombinedClassGroup;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\Timetable\FeasibilityService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TimetableController extends Controller
{
    /**
     * T4b item 3-4: ?status=draft switches the grid to the active draft
     * for the selected class (the most recent GenerateTimetableJob run
     * that hasn't been published/discarded yet) instead of the live
     * timetable. Default (no param, or status=published) is unchanged
     * from pre-T4b behaviour -- the live timetable.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $schoolClassId = $request->get('school_class_id');
        $sectionId = $request->get('section_id');
        $view = $request->get('status') === 'draft' ? 'draft' : 'published';

        $classes = SchoolClass::orderBy('class_order')->get();
        $sections = Section::all();
        $teachers = Teacher::all();
        $subjects = Subject::all();

        // Get active bell timings grouped by day of week
        $bellTimings = BellTiming::active()->orderBy('order_index')->get();

        $slots = collect();
        $activeGeneration = null;
        $hasDraft = false;

        if ($schoolClassId) {
            $baseQuery = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                })
                ->with(['subject', 'teacher', 'bellTiming']);

            if ($view === 'draft') {
                $slots = (clone $baseQuery)->draft()->get();
                $activeGeneration = $slots->isNotEmpty()
                    ? TimetableGeneration::find($slots->first()->timetable_generation_id)
                    : null;
                $hasDraft = $slots->isNotEmpty();
            } else {
                $slots = (clone $baseQuery)->published()->get();
                $hasDraft = TimetableSlot::draft()->where('school_class_id', $schoolClassId)->exists();
            }
        }

        return view('admin.timetable.grid', compact(
            'classes',
            'sections',
            'teachers',
            'subjects',
            'bellTimings',
            'slots',
            'schoolClassId',
            'sectionId',
            'view',
            'activeGeneration',
            'hasDraft'
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [
            TimetableSlot::class,
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        ]);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'room_number' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:20',
            'status' => 'nullable|in:draft,published',
        ]);

        // T4b item 4: the manual editor works on whichever grid the user
        // is looking at -- a hidden 'status' field on the form carries the
        // page's own Draft/Published toggle state through, so editing a
        // draft never touches the live timetable and vice versa.
        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // Conflict checking logic before saving, scoped to the same
        // status being edited -- a draft proposal is allowed to differ
        // from what's live, but can't conflict with itself.
        $conflictCheck = $this->checkSlotConflicts($request, $status);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message']);
        }

        // The app-level checkSlotConflicts() above is the primary guard;
        // this catch is the DB-level safety net under it (T1a) -- a race
        // between the check and the write (or any future caller that skips
        // the check) still can't create a double-booking, it just gets the
        // same friendly error instead of a 500.
        try {
            TimetableSlot::updateOrCreate(
                [
                    'school_class_id' => $validated['school_class_id'],
                    'section_id' => $validated['section_id'] ?? null,
                    'bell_timing_id' => $validated['bell_timing_id'],
                    'status' => $status,
                ],
                array_merge($validated, ['status' => $status])
            );
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this class or teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Timetable slot scheduled successfully.');
    }

    /**
     * T2b item 3: place one combined-group teaching event -- writes one
     * TimetableSlot row per member class-section, all sharing the same
     * teacher/period/subject (the group's subject). Authorization
     * requires the acting user be allowed to write EVERY member
     * class-section (reuses TimetableSlotPolicy::create per member --
     * admin always passes, a teacher must be assigned to all of them).
     *
     * Conflict checking reuses checkSlotConflicts() exactly as store()
     * does -- it scans by teacher_id + overlapping bell_timing without
     * caring whether existing rows are solo or combined-group, so it
     * already catches "teacher already committed elsewhere this period"
     * for a NEW combined placement with zero changes needed. What it
     * does NOT (and, out of scope this session, does not need to) handle
     * is re-placing/editing an EXISTING combined group's own slots --
     * only fresh placement is implemented here, matching the plan's
     * "writes one row per member class" description.
     */
    public function storeCombined(Request $request)
    {
        $validated = $request->validate([
            'combined_class_group_id' => 'required|exists:combined_class_groups,id',
            'teacher_id' => 'required|exists:teachers,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'room_number' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:20',
            'status' => 'nullable|in:draft,published',
        ]);

        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        $group = CombinedClassGroup::with('members')->findOrFail($validated['combined_class_group_id']);

        if ($group->members->count() < 2) {
            return back()->with('error', "Combined group \"{$group->name}\" has fewer than 2 member classes -- nothing to place.")->withInput();
        }

        foreach ($group->members as $member) {
            $this->authorize('create', [TimetableSlot::class, $member->school_class_id, $member->section_id]);
        }

        $conflictCheck = $this->checkSlotConflicts($request, $status);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message'])->withInput();
        }

        foreach ($group->members as $member) {
            $alreadyBooked = TimetableSlot::where('school_class_id', $member->school_class_id)
                ->where('section_id', $member->section_id)
                ->where('bell_timing_id', $validated['bell_timing_id'])
                ->where('status', $status)
                ->exists();

            if ($alreadyBooked) {
                $label = $member->schoolClass->name . ($member->section ? " {$member->section->name}" : '');
                return back()->with('error', "Scheduling conflict: {$label} already has a slot at this period.")->withInput();
            }
        }

        try {
            DB::transaction(function () use ($group, $validated, $status) {
                foreach ($group->members as $member) {
                    TimetableSlot::create([
                        'school_class_id' => $member->school_class_id,
                        'section_id' => $member->section_id,
                        'bell_timing_id' => $validated['bell_timing_id'],
                        'subject_id' => $group->subject_id,
                        'teacher_id' => $validated['teacher_id'],
                        'combined_class_group_id' => $group->id,
                        'room_number' => $validated['room_number'] ?? null,
                        'academic_year' => $validated['academic_year'] ?? null,
                        'status' => $status,
                    ]);
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this combined group could not be placed -- a member class or the teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', "Combined group \"{$group->name}\" scheduled for " . $group->members->count() . ' classes.');
    }

    /**
     * T1b: read-only feasibility report -- policy-gated the same as
     * timetable viewing (viewAny on TimetableSlot).
     */
    public function feasibility(Request $request, FeasibilityService $service)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $sessions = AcademicSession::orderByDesc('id')->get();
        $selectedSession = $request->filled('academic_session_id')
            ? $sessions->firstWhere('id', (int) $request->get('academic_session_id'))
            : $sessions->firstWhere('is_current', true);

        $academicYear = $selectedSession?->code;

        $report = $service->build($academicYear);

        return view('admin.timetable.feasibility', compact('sessions', 'selectedSession', 'report'));
    }

    /**
     * T1c: A4 landscape grid PDF for one class(-section) -- days across,
     * periods down. Policy-gated identically to timetable viewing.
     */
    public function classPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $class = SchoolClass::findOrFail($request->school_class_id);
        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $session = AcademicSession::current()->first();

        $slots = TimetableSlot::with(['bellTiming', 'subject', 'teacher'])
            ->published()
            ->where('school_class_id', $class->id)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->section_id))
            ->get();

        if ($slots->isEmpty()) {
            $label = $section ? "{$class->name} {$section->name}" : $class->name;
            return back()->with('error', "No timetable slots found for {$label} -- nothing to print yet.");
        }

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($session?->code);

        $grid = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $grid[$timing->period_name][$timing->day_of_week] = $slot;
        }

        $title = $section ? "{$class->name} - {$section->name}" : $class->name;

        $pdf = Pdf::loadView('admin.timetable.pdf.class', [
            'title' => $title,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('class', $title, $session));
    }

    /**
     * T1c: A4 landscape grid PDF for one teacher -- days across, periods
     * down, class-section + subject per cell, free periods left blank.
     */
    public function teacherPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $session = AcademicSession::current()->first();

        $slots = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section'])
            ->published()
            ->where('teacher_id', $teacher->id)
            ->get();

        if ($slots->isEmpty()) {
            return back()->with('error', "No timetable slots found for {$teacher->name} -- nothing to print yet.");
        }

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($session?->code);

        $grid = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $grid[$timing->period_name][$timing->day_of_week] = $slot;
        }

        $pdf = Pdf::loadView('admin.timetable.pdf.teacher', [
            'teacher' => $teacher,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('teacher', $teacher->name, $session));
    }

    /**
     * T1c: master timetable -- all active classes x periods, one page per
     * operating day, compact (subject/teacher-short-name per cell).
     */
    public function masterPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $session = AcademicSession::current()->first();

        $slots = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'schoolClass', 'section'])->published()->get();

        if ($slots->isEmpty()) {
            return back()->with('error', 'No timetable slots found for any class -- nothing to print yet.');
        }

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($session?->code);
        $classes = SchoolClass::active()->orderByOrder()->get();

        // [day][class_id][period_name] => slot
        $byDay = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $byDay[$timing->day_of_week][$slot->school_class_id][$timing->period_name] = $slot;
        }

        $pdf = Pdf::loadView('admin.timetable.pdf.master', [
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'classes' => $classes,
            'byDay' => $byDay,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('master', 'all-classes', $session));
    }

    /**
     * Active periods (rows) -- teaching AND non-teaching -- and the days
     * they occur on (columns), ordered consistently: periods by
     * bell_timings.order_index, days by BellTiming's own canonical
     * day_order accessor (Mon..Sun), not a hardcoded list, so this
     * doesn't assume which days the school runs.
     *
     * T2b: also returns $periodMeta[$periodName][$day] describing
     * whether that specific (period, day) cell is a teaching period --
     * non-teaching cells (assembly/prayer/break/zero/dispersal) still
     * print in the PDF grids, just shaded with a label instead of
     * waiting for a TimetableSlot that will never exist there. Looked up
     * per (period, day) rather than per period name alone, since two
     * different days can use the same period_name for different things
     * (confirmed possible by T2b item 2's finding that each bell_timings
     * row is independently keyed by day).
     */
    private function buildPeriodDayAxes(?string $academicYear): array
    {
        $activeTimings = BellTiming::active()
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->orderBy('order_index')
            ->get();

        $periods = $activeTimings->pluck('period_name')->unique()->values()->all();
        $days = $activeTimings->sortBy('day_order')->pluck('day_of_week')->unique()->values()->all();

        $periodMeta = [];
        foreach ($activeTimings as $timing) {
            $periodMeta[$timing->period_name][$timing->day_of_week] = [
                'is_teaching' => $timing->period_type === BellTiming::PERIOD_TYPE_TEACHING,
                'label' => $timing->custom_label ?: ucfirst($timing->period_type),
            ];
        }

        return [$periods, $days, $periodMeta];
    }

    private function pdfFilename(string $type, string $name, ?AcademicSession $session): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $safeSession = preg_replace('/[^A-Za-z0-9_-]+/', '_', $session->code ?? 'na');

        return "timetable_{$type}_{$safeName}_{$safeSession}.pdf";
    }

    public function destroy($id)
    {
        $slot = TimetableSlot::findOrFail($id);
        $this->authorize('delete', $slot);
        $slot->delete();

        return back()->with('success', 'Timetable slot cleared.');
    }

    /**
     * T4b item 2/3: "Generate (Beta)" -- dispatches GenerateTimetableJob
     * for one class's current academic session. The TimetableGeneration
     * row is created here, synchronously, before dispatch, so the UI has
     * an id to poll immediately regardless of queue latency (same pattern
     * as FinancialYearClosing/StageYearClosingJob).
     */
    public function generate(Request $request)
    {
        $this->authorize('generate', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
        ]);

        $session = AcademicSession::current()->first();

        $generation = TimetableGeneration::create([
            'academic_year' => $session?->code,
            'academic_session_id' => $session?->id,
            'school_class_ids' => [(int) $validated['school_class_id']],
            'status' => TimetableGeneration::STATUS_QUEUED,
            'requested_by' => Auth::id(),
        ]);

        GenerateTimetableJob::dispatch($generation->id);

        return response()->json([
            'generation_id' => $generation->id,
            'status_url' => route('timetable.generation.status', $generation),
        ]);
    }

    /**
     * T4b item 3: polled by the "Generate (Beta)" confirm-dialog flow.
     * The unplaced-lesson sentences are only included once the run has
     * actually completed, keeping the payload small while it's still
     * queued/running.
     */
    public function generationStatus(TimetableGeneration $generation)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        return response()->json([
            'id' => $generation->id,
            'status' => $generation->status,
            'placed_count' => $generation->placed_count,
            'unplaced_count' => $generation->unplaced_count,
            'placement_percent' => $generation->placement_percent,
            'error' => $generation->error,
            'unplaced' => $generation->status === TimetableGeneration::STATUS_COMPLETED
                ? ($generation->report['unplaced'] ?? [])
                : [],
        ]);
    }

    /**
     * T4b item 5: PUBLISH -- admin-only, atomic. Archives every currently-
     * published slot for the generation's class-section set (a full-class
     * regeneration replaces the whole set, not a partial patch -- matches
     * GeneratorService building its lesson list from ALL of a class's
     * assignments) and flips this generation's own draft rows to
     * published, in one transaction.
     */
    public function publishGeneration(TimetableGeneration $generation)
    {
        $this->authorize('publish', TimetableSlot::class);

        if ($generation->status !== TimetableGeneration::STATUS_COMPLETED) {
            return back()->with('error', 'Only a completed generation can be published.');
        }

        DB::transaction(function () use ($generation) {
            TimetableSlot::published()
                ->whereIn('school_class_id', $generation->school_class_ids)
                ->update(['status' => TimetableSlot::STATUS_ARCHIVED]);

            TimetableSlot::draft()
                ->where('timetable_generation_id', $generation->id)
                ->update(['status' => TimetableSlot::STATUS_PUBLISHED]);

            $generation->update(['status' => TimetableGeneration::STATUS_PUBLISHED]);
        });

        return redirect()->route('timetable.index', ['school_class_id' => $generation->school_class_ids[0] ?? null])
            ->with('success', 'Generation published -- this is now the live timetable for the affected classes.');
    }

    /**
     * T4b item 5: DISCARD -- deletes only this generation's own draft
     * rows. Never touches published/archived slots, so the live timetable
     * is byte-identical before and after (see
     * GenerateTimetableJobPublishDiscardTest::test_generate_then_discard_leaves_published_slots_byte_identical).
     */
    public function discardGeneration(TimetableGeneration $generation)
    {
        $this->authorize('publish', TimetableSlot::class);

        if ($generation->status !== TimetableGeneration::STATUS_COMPLETED) {
            return back()->with('error', 'Only a completed generation can be discarded.');
        }

        DB::transaction(function () use ($generation) {
            TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->delete();
            $generation->update(['status' => TimetableGeneration::STATUS_DISCARDED]);
        });

        return redirect()->route('timetable.index', ['school_class_id' => $generation->school_class_ids[0] ?? null])
            ->with('success', 'Draft discarded -- the live timetable is unchanged.');
    }

    public function checkConflictsApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $status = $request->get('status') === 'draft' ? TimetableSlot::STATUS_DRAFT : TimetableSlot::STATUS_PUBLISHED;
        $result = $this->checkSlotConflicts($request, $status);
        return response()->json($result);
    }

    /**
     * T4b item 4: scoped to a single status so a draft proposal is free
     * to differ from what's live, but still can't conflict with itself.
     */
    private function checkSlotConflicts(Request $request, string $status = TimetableSlot::STATUS_PUBLISHED): array
    {
        $id = $request->get('id');
        $teacherId = $request->get('teacher_id');
        $bellTimingId = $request->get('bell_timing_id');
        $roomNumber = $request->get('room_number');

        if (!$teacherId || !$bellTimingId) {
            return ['conflict' => false];
        }

        $bellTiming = BellTiming::findOrFail($bellTimingId);
        $startTime = $bellTiming->start_time->format('H:i:s');
        $endTime = $bellTiming->end_time->format('H:i:s');

        // Find overlapping bell timings on the same day_of_week
        $overlappingTimings = BellTiming::where('day_of_week', $bellTiming->day_of_week)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->pluck('id');

        // Check Teacher overlap
        $teacherConflict = TimetableSlot::whereIn('bell_timing_id', $overlappingTimings)
            ->where('teacher_id', $teacherId)
            ->where('status', $status)
            ->when($id, function ($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($teacherConflict) {
            return [
                'conflict' => true,
                'type' => 'teacher',
                'message' => "Teacher is already scheduled to teach " . ($teacherConflict->schoolClass->name ?? 'another class') . " during this period."
            ];
        }

        // Check Room overlap
        if ($roomNumber) {
            $roomConflict = TimetableSlot::whereIn('bell_timing_id', $overlappingTimings)
                ->where('room_number', $roomNumber)
                ->where('status', $status)
                ->when($id, function ($q) use ($id) {
                    $q->where('id', '!=', $id);
                })
                ->first();

            if ($roomConflict) {
                return [
                    'conflict' => true,
                    'type' => 'room',
                    'message' => "Room {$roomNumber} is already occupied by Class " . ($roomConflict->schoolClass->name ?? 'another class') . " during this period."
                ];
            }
        }

        return ['conflict' => false];
    }
}
