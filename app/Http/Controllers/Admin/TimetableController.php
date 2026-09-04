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
use App\Services\Timetable\TimetableAutoFixService;
use App\Services\Timetable\TimetableConflictResolver;
use App\Services\Timetable\TimetableRebalanceService;
use App\Services\Timetable\TimetableSuggestionService;
use App\Services\Timetable\TimetableSwapService;
use App\Exports\ClassTimetableExport;
use App\Exports\MasterTimetableExport;
use App\Exports\RoomTimetableExport;
use App\Exports\TeacherTimetableExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

        return view('admin.timetable.grid', $this->buildGridViewData($request));
    }

    /**
     * Shared by index() and workspace() -- the exact same class/section
     * grid data, so the Timetable Workspace's "Review & Edit" section
     * shows byte-identical data to the standalone grid page rather than a
     * second, drifting copy of this lookup.
     */
    private function buildGridViewData(Request $request): array
    {
        $schoolClassId = $request->get('school_class_id');
        $sectionId = $request->get('section_id');
        $view = $request->get('status') === 'draft' ? 'draft' : 'published';

        // Pilot-hardening (workspace authorization): index()/workspace()
        // previously only checked viewAny (role), so any teacher-role
        // account could view -- or, via ?status=draft, see the in-progress
        // draft of -- ANY class's grid by supplying an arbitrary
        // school_class_id. Reuses the exact same viewClassTimetable
        // ability already applied to classPdf/classExcelExport, which
        // itself reuses the write side's teacherAssignedToClassSection()
        // check -- no new authorization system, no schema/role change.
        // Applies identically regardless of draft/published, so drafts
        // get the same boundary as published, never a weaker one.
        if ($schoolClassId) {
            $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $schoolClassId, $sectionId ? (int) $sectionId : null]);
        }

        $classes = SchoolClass::orderBy('class_order')->get();
        $sections = Section::all();
        $teachers = Teacher::all();
        $subjects = Subject::all();

        $slots = collect();
        $activeGeneration = null;
        $hasDraft = false;

        // The grid's row/column structure must come from the SAME academic
        // year as the slots being displayed -- otherwise, once more than
        // one academic year has active bell timings at once (e.g. next
        // year's grid being set up while this year's is still live), rows
        // sharing a common period_name/day (like "Period 1" on Monday)
        // silently collide across years: the view groups bell timings by
        // period_name+day only (see the partial below), so a slot placed
        // against this year's "Period 1" can vanish behind whichever other
        // year's "Period 1" happens to be picked first.
        $academicYear = null;
        if ($schoolClassId) {
            $academicYear = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, fn ($q) => $q->where('section_id', $sectionId))
                ->when($view === 'draft', fn ($q) => $q->draft(), fn ($q) => $q->published())
                ->value('academic_year');
        }
        $academicYear = $academicYear ?? AcademicSession::current()->first()?->code;

        // Get active bell timings grouped by day of week, scoped to that
        // academic year -- the exact same scoping GeneratorService itself
        // uses, so the grid always reflects the bell-timing set the slots
        // were actually generated against.
        //
        // UAT Test 15 fix: also scoped to the selected class's own
        // BellTiming::class_section (or the "All Classes" null rows),
        // mirroring GeneratorService's own class_section matching -- the
        // Schedule Class Period modal previously offered every class's
        // periods with no way to tell them apart. This is a UI convenience
        // only; store()/update() independently re-validate ownership
        // server-side regardless of what this query returns.
        $selectedClass = $schoolClassId ? $classes->firstWhere('id', (int) $schoolClassId) : null;

        $bellTimings = BellTiming::active()->orderBy('order_index')
            ->when($academicYear, fn ($q) => $q->where('academic_year', $academicYear))
            ->when($selectedClass, fn ($q) => $q->where(function ($q2) use ($selectedClass) {
                $q2->whereNull('class_section')->orWhere('class_section', $selectedClass->name);
            }))
            ->get();

        if ($schoolClassId) {
            $baseQuery = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                })
                ->with(['subject', 'teacher', 'coTeacher', 'bellTiming', 'combinedClassGroup']);

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

        return compact(
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
        );
    }

    /**
     * Timetable Editor Phase B: the single consolidated workspace the
     * sidebar's "Timetable Editor" entry now opens. Structural shell only
     * -- every section reuses the SAME services/controllers/routes the
     * standalone pages already use (FeasibilityService for readiness,
     * buildGridViewData() for Review & Edit, the existing generate/wizard/
     * publish routes for their actions). No new business rules, no
     * duplicated conflict/suggestion/generation logic.
     */
    public function workspace(Request $request, FeasibilityService $service)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $currentSession = AcademicSession::current()->first();
        $academicYear = $currentSession?->code;

        $report = $service->build($academicYear);

        $timetableStatus = TimetableSlot::published()->exists()
            ? 'published'
            : (TimetableSlot::draft()->exists() ? 'draft' : 'none');

        $counts = [
            'classes' => SchoolClass::active()->count(),
            'sections' => Section::count(),
            'teachers' => Teacher::count(),
            'subjects' => Subject::count(),
            'bell_timings' => BellTiming::active()->count(),
        ];

        $readinessIssueCount = count($report['conflicts'] ?? [])
            + count($report['class_teacher_readiness'] ?? []);
        // Production hardening: an empty grid_capacity here means there's
        // nothing to evaluate yet (no active classes), not a genuine
        // scheduling conflict -- previously both cases collapsed onto the
        // same 'blocked' state, so the Home tab showed a red "Blocked"
        // badge next to the literally contradictory "0 conflict(s), 0
        // readiness note(s)" text. Kept as its own distinct state so the
        // badge and explanation can accurately say why.
        $readiness = $readinessIssueCount === 0
            ? (empty($report['grid_capacity']) ? 'no_classes' : 'ready')
            : (count($report['conflicts'] ?? []) > 0 ? 'blocked' : 'warning');

        $gridData = $this->buildGridViewData($request);

        return view('admin.timetable.workspace', array_merge($gridData, [
            'currentSession' => $currentSession,
            'academicYear' => $academicYear,
            'timetableStatus' => $timetableStatus,
            'counts' => $counts,
            'report' => $report,
            'readiness' => $readiness,
        ]));
    }

    /**
     * UAT Test 15 fix: BellTiming.class_section is a bare string with no
     * FK to SchoolClass, so `exists:bell_timings,id` validation alone
     * never proves the chosen period actually belongs to the class being
     * scheduled. A null class_section is BellTiming's own established
     * "All Classes" / general-schedule semantics and is valid for every
     * class -- everything else must match the selected class's name
     * exactly, the same string GeneratorService already matches on.
     */
    private function bellTimingOwnershipError(BellTiming $bellTiming, SchoolClass $schoolClass): ?string
    {
        if ($bellTiming->class_section !== null && $bellTiming->class_section !== $schoolClass->name) {
            return "This period slot belongs to \"{$bellTiming->class_section}\", not \"{$schoolClass->name}\" -- choose a period that belongs to the selected class.";
        }

        return null;
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
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
        ]);

        // UAT Test 15 fix: `exists:bell_timings,id` above only proves the
        // row exists, never that it belongs to the class being scheduled --
        // BellTiming.class_section has no FK to SchoolClass, so nothing
        // upstream (the grid's dropdown scoping, TimetableConflictResolver)
        // can be trusted alone to prevent a wrong-class bell_timing_id from
        // being submitted directly. This is the actual safety net.
        $ownershipError = $this->bellTimingOwnershipError(
            BellTiming::findOrFail($validated['bell_timing_id']),
            SchoolClass::findOrFail($validated['school_class_id'])
        );
        if ($ownershipError) {
            return back()->withInput()->with('error', $ownershipError);
        }

        // T4b item 4: the manual editor works on whichever grid the user
        // is looking at -- a hidden 'status' field on the form carries the
        // page's own Draft/Published toggle state through, so editing a
        // draft never touches the live timetable and vice versa.
        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // academic_year is never client-supplied -- it's resolved the same
        // way every other timetable read/write in this controller resolves
        // "the current year" (AcademicSession::current(), the same source
        // GenerateTimetableJob stamps onto generator-created slots), so a
        // manually-placed slot is tagged consistently with generated ones
        // instead of silently landing on null and becoming invisible to
        // any future academic-year-scoped check.
        $validated['academic_year'] = AcademicSession::current()->first()?->code;

        // Conflict checking logic before saving, scoped to the same
        // status being edited -- a draft proposal is allowed to differ
        // from what's live, but can't conflict with itself.
        $conflictCheck = $this->checkSlotConflicts($request, $status);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message']);
        }

        // Timetable Hardening pass: unlike update() (which edits a specific
        // row by id and already rejects a locked/combined-group target),
        // this upserts by NATURAL KEY (class, section, bell_timing, status)
        // -- and TimetableConflictResolver deliberately does NOT flag an
        // existing row at that exact key as a conflict (it's auto-resolved
        // as the row being "updated in place", see resolveSelfId()). That
        // means a locked or combined-group row sitting at this exact cell
        // would otherwise be silently overwritten by updateOrCreate() below
        // with zero warning -- the one lifecycle rule the earlier Lock
        // Integrity audit's sweep of update()/destroy()/lockSlot()/swap/
        // rebalance/Auto-Fix missed, because store() has no route through
        // any of those. Checked explicitly here, mirroring update()'s own
        // rejection messages exactly.
        $naturalKeyOccupant = TimetableSlot::where('school_class_id', $validated['school_class_id'])
            ->where('section_id', $validated['section_id'] ?? null)
            ->where('bell_timing_id', $validated['bell_timing_id'])
            ->where('status', $status)
            ->first();

        if ($naturalKeyOccupant && $naturalKeyOccupant->is_locked) {
            return back()->with('error', 'This lesson is locked -- unlock it first to edit it.')->withInput();
        }

        if ($naturalKeyOccupant && $naturalKeyOccupant->combined_class_group_id) {
            return back()->with('error', 'This is a combined-group lesson -- it can\'t be edited from a single cell. Clear it and re-place it via Combined Groups instead.')->withInput();
        }

        // The app-level checkSlotConflicts() above is the primary guard;
        // this catch is the DB-level safety net under it (T1a) -- a race
        // between the check and the write (or any future caller that skips
        // the check) still can't create a double-booking, it just gets the
        // same friendly error instead of a 500.
        try {
            $slot = DB::transaction(function () use ($validated, $status) {
                $slot = TimetableSlot::updateOrCreate(
                    [
                        'school_class_id' => $validated['school_class_id'],
                        'section_id' => $validated['section_id'] ?? null,
                        'bell_timing_id' => $validated['bell_timing_id'],
                        'status' => $status,
                    ],
                    array_merge($validated, ['status' => $status])
                );

                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status])
                    ->log($slot->wasRecentlyCreated ? 'timetable_slot_created' : 'timetable_slot_updated');

                return $slot;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this class or teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Timetable slot scheduled successfully.');
    }

    /**
     * Timetable Editor Slice 1: edit an ALREADY-PLACED lesson in place, by
     * row id -- deliberately NOT store()'s updateOrCreate-by-natural-key,
     * because that upserts on (school_class_id, section_id, bell_timing_id,
     * status): changing the class, section, or period (the whole point of
     * an edit) changes that natural key, so updateOrCreate would silently
     * CREATE a second row and leave the original sitting there, duplicating
     * the lesson instead of moving it. Editing by $slot->update() touches
     * only the columns this action explicitly sets -- status, academic_year,
     * timetable_generation_id, and combined_class_group_id are never part
     * of that array, so they survive an edit completely untouched, exactly
     * preserving which draft/generation/version this row belongs to.
     *
     * Combined-group rows are rejected outright: a combined placement is
     * several rows (one per member class) that must change together, and
     * synchronising that is out of scope for this slice (existing,
     * documented pattern for a combined-group move is clear-then-
     * storeCombined() again, unchanged). Archived rows are historical
     * snapshots from a past PUBLISH and are never meant to change after
     * the fact -- rejected the same way. Locked rows (Phase 5) are
     * rejected the same way too -- a lock's entire purpose is to survive
     * everything except an explicit unlock, and this was one of the write
     * paths that didn't actually enforce that yet (Generator and chain
     * Auto-Fix already did; this closes the gap found by the Lock
     * Integrity audit).
     *
     * Authorization is two-fold, mirroring store()/storeCombined(): 'update'
     * on the slot itself (TimetableSlotPolicy -- admin, or a teacher
     * assigned to the slot's CURRENT class-section) guards against touching
     * a row you have no claim over at all; 'create' against the requested
     * DESTINATION class/section guards against a tampered school_class_id
     * moving the lesson into a class/section you have no claim over
     * either -- both checks run against real, server-resolved data, never
     * whatever the client merely claims.
     *
     * TimetableConflictResolver is the only rule engine consulted (no
     * parallel validation here or in JS): the full proposed row -- as it
     * would exist after the edit -- is checked with ignore_slot_id set to
     * this row's own id, so it never conflicts with the very occurrence
     * being replaced. A rejected edit returns the resolver's full
     * conflicts[] plus TimetableSuggestionService's already-validated
     * alternative periods for the SAME proposed placement -- no separate
     * suggestion logic, no unvalidated alternatives.
     *
     * The update + activity log are one DB transaction: if anything throws
     * after the row has been written but before the log entry commits (or
     * vice versa), the whole thing rolls back and the row is left exactly
     * as it was before this request, never partially changed.
     */
    public function update(Request $request, TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        if ($slot->combined_class_group_id) {
            return back()->with('error', 'This is a combined-group lesson -- it can\'t be edited from a single cell. Clear it and re-place it via Combined Groups instead.');
        }

        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            return back()->with('error', 'This slot is archived history from a past publish -- it can no longer be edited.');
        }

        if ($slot->is_locked) {
            return back()->with('error', 'This lesson is locked -- unlock it first to edit it.');
        }

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'room_number' => 'nullable|string|max:50',
        ]);

        $destinationClassId = (int) $validated['school_class_id'];
        $destinationSectionId = ! empty($validated['section_id']) ? (int) $validated['section_id'] : null;
        $destinationCoTeacherId = ! empty($validated['co_teacher_id']) ? (int) $validated['co_teacher_id'] : null;

        $this->authorize('create', [TimetableSlot::class, $destinationClassId, $destinationSectionId]);

        // UAT Test 15 fix: same ownership check as store() -- an edit can
        // move a lesson to a different class/period, so this must be
        // re-verified here too, not assumed from the slot's prior state.
        $ownershipError = $this->bellTimingOwnershipError(
            BellTiming::findOrFail($validated['bell_timing_id']),
            SchoolClass::findOrFail($destinationClassId)
        );
        if ($ownershipError) {
            return back()->withInput()->with('error', $ownershipError);
        }

        $proposedPlacement = [
            'school_class_id' => $destinationClassId,
            'section_id' => $destinationSectionId,
            'bell_timing_id' => (int) $validated['bell_timing_id'],
            'subject_id' => (int) $validated['subject_id'],
            'teacher_id' => (int) $validated['teacher_id'],
            'co_teacher_id' => $destinationCoTeacherId,
            'room_number' => $validated['room_number'] ?? null,
            // Preserved, never taken from the request -- an edit can move
            // WHERE/WHO/WHAT a lesson is, never which draft/version or
            // academic year it belongs to.
            'status' => $slot->status,
            'academic_year' => $slot->academic_year,
            'ignore_slot_id' => $slot->id,
        ];

        $conflictCheck = (new TimetableConflictResolver())->check($proposedPlacement);

        if ($conflictCheck['conflict']) {
            $suggestions = (new TimetableSuggestionService())->suggestForNewPlacement($proposedPlacement);

            // suggestForNewPlacement() has no concept of "this row's own
            // CURRENT period" -- for a fresh add that never arises, but for
            // an edit its own pre-edit period is naturally conflict-free
            // against itself (see resolveSelfId()) and would otherwise be
            // offered back as a no-op "alternative": move it to exactly
            // where it already is. Filtered out here, at the orchestration
            // layer -- TimetableSuggestionService itself is untouched.
            $suggestions = array_values(array_filter(
                $suggestions,
                fn ($s) => (int) $s['bell_timing_id'] !== (int) $slot->bell_timing_id
            ));

            return back()->withInput()->with('error', 'Scheduling conflict: ' . $conflictCheck['message'])->with('edit_conflict', [
                'slot_id' => $slot->id,
                'message' => $conflictCheck['message'],
                'conflicts' => $conflictCheck['conflicts'],
                'suggestions' => $suggestions,
            ]);
        }

        $before = $slot->only(['school_class_id', 'section_id', 'bell_timing_id', 'subject_id', 'teacher_id', 'co_teacher_id', 'room_number']);
        $after = [
            'school_class_id' => $proposedPlacement['school_class_id'],
            'section_id' => $proposedPlacement['section_id'],
            'bell_timing_id' => $proposedPlacement['bell_timing_id'],
            'subject_id' => $proposedPlacement['subject_id'],
            'teacher_id' => $proposedPlacement['teacher_id'],
            'co_teacher_id' => $proposedPlacement['co_teacher_id'],
            'room_number' => $proposedPlacement['room_number'],
        ];

        try {
            DB::transaction(function () use ($slot, $after, $before) {
                $slot->update($after);

                activity()->causedBy(Auth::user())->performedOn($slot)
                    ->withProperties(['before' => $before, 'after' => $after, 'status' => $slot->status, 'timetable_generation_id' => $slot->timetable_generation_id])
                    ->log('timetable_slot_edited');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Scheduling conflict: this class or teacher already has a slot at this period.')->withInput();
            }

            throw $e;
        }

        return back()->with('success', 'Lesson updated.');
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
            'status' => 'nullable|in:draft,published',
        ]);

        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;

        // Same reasoning as store(): academic_year is never client-supplied.
        $validated['academic_year'] = AcademicSession::current()->first()?->code;

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

                activity()->causedBy(Auth::user())->performedOn($group)
                    ->withProperties(['bell_timing_id' => $validated['bell_timing_id'], 'teacher_id' => $validated['teacher_id'], 'member_count' => $group->members->count()])
                    ->log('combined_group_placed');
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
        $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $request->school_class_id, $request->filled('section_id') ? (int) $request->section_id : null]);

        $class = SchoolClass::findOrFail($request->school_class_id);
        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher'])
            ->published()
            ->where('school_class_id', $class->id)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->section_id));

        if ((clone $slotsQuery)->doesntExist()) {
            $label = $section ? "{$class->name} {$section->name}" : $class->name;
            return back()->with('error', "No timetable slots found for {$label} -- nothing to print yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        $title = $section ? "{$class->name} - {$section->name}" : $class->name;

        $pdf = Pdf::loadView('admin.timetable.pdf.class', [
            'title' => $title,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
            'lastTeachingPeriod' => $class->last_teaching_period,
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
        $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $session = AcademicSession::current()->first();

        // T6 item 4: a co-teacher's own PDF must show their team-taught
        // periods too, not just periods where they're the primary teacher.
        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
            ->published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id));

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for {$teacher->name} -- nothing to print yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

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

        $data = $this->masterTimetableData();
        if ($data === null) {
            return back()->with('error', 'No timetable slots found for any class -- nothing to print yet.');
        }
        if (empty($data['days'])) {
            return back()->with('error', 'No active periods are configured for the current academic year -- nothing to print yet. Set up Bell Timings first.');
        }

        $pdf = Pdf::loadView('admin.timetable.pdf.master', $data);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('master', 'all-classes', $data['session']));
    }

    /**
     * Shared by masterPdf() and the Excel master export (Phase 5) -- same
     * query, same [day][class_id][period_name] aggregation (distinct from
     * buildTimetableGrid()'s [period][day] shape, since the master view
     * groups by day first to paginate one page per operating day), one
     * definition either consumer reads from.
     *
     * Returns null only when there are literally no published slots at
     * all. It deliberately does NOT also guard "zero active bell timings
     * for this academic year" (published slots can exist while every
     * BellTiming is deactivated or tagged for a different year) -- both
     * callers check `empty($data['days'])` themselves right after calling
     * this, with their own format-specific message, since "nothing to
     * print" and "nothing to export" already need their own wording for
     * the zero-slots case too.
     *
     * production audit: masterExcelExport() previously called
     * Excel::download() unconditionally once $data was non-null, which
     * crashed with an uncaught PhpSpreadsheet exception when $data['days']
     * was empty (WithMultipleSheets::sheets() returned no sheets at all,
     * and the writer's setActiveSheetIndex(0) has nothing to index into).
     * masterPdf() didn't crash but silently rendered a blank PDF instead
     * -- the @foreach($days as $day) loop that owns the entire page body
     * simply ran zero times. Both are guarded by the same check now.
     *
     * @return ?array{session: ?AcademicSession, periods: array, days: array, periodMeta: array, classes: \Illuminate\Support\Collection, byDay: array}
     */
    private function masterTimetableData(): ?array
    {
        $session = AcademicSession::current()->first();

        $slots = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])->published()->get();

        if ($slots->isEmpty()) {
            return null;
        }

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($session?->code);
        $classes = SchoolClass::active()->orderByOrder()->get();

        $byDay = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $byDay[$timing->day_of_week][$slot->school_class_id][$timing->period_name] = $slot;
        }

        return compact('session', 'periods', 'days', 'periodMeta', 'classes', 'byDay');
    }

    /**
     * Phase 5: Class timetable Excel export -- identical query/grid to
     * classPdf() above (buildTimetableGrid()), just a different renderer.
     */
    public function classExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);
        $this->authorize('viewClassTimetable', [TimetableSlot::class, (int) $request->school_class_id, $request->filled('section_id') ? (int) $request->section_id : null]);

        $class = SchoolClass::findOrFail($request->school_class_id);
        $section = $request->filled('section_id') ? Section::find($request->section_id) : null;
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher'])
            ->published()
            ->where('school_class_id', $class->id)
            ->when($request->filled('section_id'), fn ($q) => $q->where('section_id', $request->section_id));

        if ((clone $slotsQuery)->doesntExist()) {
            $label = $section ? "{$class->name} {$section->name}" : $class->name;
            return back()->with('error', "No timetable slots found for {$label} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        $title = $section ? "{$class->name} - {$section->name}" : $class->name;

        return Excel::download(
            new ClassTimetableExport($title, $session, $periods, $days, $periodMeta, $grid, $class->last_teaching_period),
            $this->excelFilename('class', $title, $session)
        );
    }

    /**
     * Phase 5: Teacher timetable Excel export -- identical query/grid to
     * teacherPdf() above (primary teacher OR co-teacher, published only).
     */
    public function teacherExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate(['teacher_id' => 'required|exists:teachers,id']);
        $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);

        $teacher = Teacher::findOrFail($request->teacher_id);
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
            ->published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id));

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for {$teacher->name} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        return Excel::download(
            new TeacherTimetableExport($teacher->name, $session, $periods, $days, $periodMeta, $grid),
            $this->excelFilename('teacher', $teacher->name, $session)
        );
    }

    /**
     * Phase 5: Master timetable Excel export -- identical data to
     * masterPdf() above (masterTimetableData()), one sheet per day.
     */
    public function masterExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $data = $this->masterTimetableData();
        if ($data === null) {
            return back()->with('error', 'No timetable slots found for any class -- nothing to export yet.');
        }
        if (empty($data['days'])) {
            return back()->with('error', 'No active periods are configured for the current academic year -- nothing to export yet. Set up Bell Timings first.');
        }

        return Excel::download(new MasterTimetableExport($data), $this->excelFilename('master', 'all-classes', $data['session']));
    }

    /**
     * Phase 5: Room timetable Excel export -- identical query/grid to
     * roomView() above.
     */
    public function roomExcelExport(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate(['room' => 'required|string']);
        $room = $request->string('room')->toString();
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])
            ->published()
            ->where('room_number', $room);

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for Room {$room} -- nothing to export yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        return Excel::download(
            new RoomTimetableExport($room, $session, $periods, $days, $periodMeta, $grid),
            $this->excelFilename('room', $room, $session)
        );
    }

    /**
     * Item 6: the one grid PDF export class/teacher/master already had that
     * room never got -- same buildTimetableGrid()-built data roomExcelExport()
     * above already uses, so the PDF, Excel, and interactive roomView() can
     * never structurally drift apart. Gated identically to roomView()/
     * roomExcelExport() (viewAny only -- rooms have no ownership concept in
     * this codebase, per TimetableSlotPolicy's own documented decision).
     */
    public function roomPdf(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $request->validate(['room' => 'required|string']);
        $room = $request->string('room')->toString();
        $session = AcademicSession::current()->first();

        $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])
            ->published()
            ->where('room_number', $room);

        if ((clone $slotsQuery)->doesntExist()) {
            return back()->with('error', "No timetable slots found for Room {$room} -- nothing to print yet.");
        }

        [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);

        $pdf = Pdf::loadView('admin.timetable.pdf.room', [
            'room' => $room,
            'session' => $session,
            'periods' => $periods,
            'days' => $days,
            'periodMeta' => $periodMeta,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download($this->pdfFilename('room', $room, $session));
    }

    private function excelFilename(string $type, string $name, ?AcademicSession $session): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $safeSession = preg_replace('/[^A-Za-z0-9_-]+/', '_', $session->code ?? 'na');

        return "timetable_{$type}_{$safeName}_{$safeSession}.xlsx";
    }

    /**
     * Phase 5: interactive, read-only Teacher timetable -- reuses
     * buildTimetableGrid() (the SAME query shape teacherPdf() already
     * uses: primary teacher OR co-teacher, published only) so the
     * interactive view and the PDF/Excel exports can never drift apart.
     * Search/filter is the teacher picker itself; day/subject filtering
     * happens client-side over the already-rendered grid (small, fixed-
     * size dataset -- a week's timetable -- no server round-trip needed).
     */
    public function teacherView(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $teachers = Teacher::orderBy('name')->get();
        $selectedTeacher = null;
        $periods = $days = $periodMeta = $grid = [];
        $session = AcademicSession::current()->first();

        if ($request->filled('teacher_id')) {
            $request->validate(['teacher_id' => 'exists:teachers,id']);
            $this->authorize('viewTeacherTimetable', [TimetableSlot::class, (int) $request->teacher_id]);
            $selectedTeacher = Teacher::find($request->teacher_id);

            $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'schoolClass', 'section', 'teacher', 'coTeacher'])
                ->published()
                ->where(fn ($q) => $q->where('teacher_id', $selectedTeacher->id)->orWhere('co_teacher_id', $selectedTeacher->id));

            [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        }

        return view('admin.timetable.teacher-view', compact('teachers', 'selectedTeacher', 'periods', 'days', 'periodMeta', 'grid', 'session'));
    }

    /**
     * Phase 5: interactive, read-only Room timetable -- "basic" because
     * rooms aren't a modeled entity yet (room_number is a free-text field
     * on TimetableSlot, per the earlier module audit's documented gap);
     * the room picker is simply every DISTINCT room_number value already
     * in use. Same buildTimetableGrid() reuse as the teacher view above.
     */
    public function roomView(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $rooms = TimetableSlot::published()->whereNotNull('room_number')->distinct()->orderBy('room_number')->pluck('room_number');
        $selectedRoom = null;
        $periods = $days = $periodMeta = $grid = [];
        $session = AcademicSession::current()->first();

        if ($request->filled('room')) {
            $selectedRoom = $request->string('room')->toString();

            $slotsQuery = TimetableSlot::with(['bellTiming', 'subject', 'teacher', 'coTeacher', 'schoolClass', 'section'])
                ->published()
                ->where('room_number', $selectedRoom);

            [, $periods, $days, $periodMeta, $grid] = $this->buildTimetableGrid($slotsQuery, $session?->code);
        }

        return view('admin.timetable.room-view', compact('rooms', 'selectedRoom', 'periods', 'days', 'periodMeta', 'grid', 'session'));
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
                'order_index' => (int) $timing->order_index,
            ];
        }

        return [$periods, $days, $periodMeta];
    }

    /**
     * Shared by classPdf(), teacherPdf(), the new interactive Teacher/
     * Class/Room views, and their Excel exports (Phase 5) -- runs the
     * given (already-scoped) query, then indexes every returned slot into
     * a [period_name][day_of_week] grid, exactly the shape every one of
     * those consumers renders. One definition of "fetch + grid" instead of
     * a copy in each caller.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array, 2: array, 3: array, 4: array}
     */
    private function buildTimetableGrid(\Illuminate\Database\Eloquent\Builder $slotsQuery, ?string $academicYear): array
    {
        $slots = $slotsQuery->get();

        [$periods, $days, $periodMeta] = $this->buildPeriodDayAxes($academicYear);

        $grid = [];
        foreach ($slots as $slot) {
            $timing = $slot->bellTiming;
            if (!$timing) {
                continue;
            }
            $grid[$timing->period_name][$timing->day_of_week] = $slot;
        }

        return [$slots, $periods, $days, $periodMeta, $grid];
    }

    private function pdfFilename(string $type, string $name, ?AcademicSession $session): string
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $name);
        $safeSession = preg_replace('/[^A-Za-z0-9_-]+/', '_', $session->code ?? 'na');

        return "timetable_{$type}_{$safeName}_{$safeSession}.pdf";
    }

    /**
     * A combined-group placement is one shared teaching event written as
     * one TimetableSlot row per member class (T2b item 3, storeCombined()
     * above). Clearing only the clicked row silently orphaned every other
     * member's row, leaving an inconsistent partial placement -- this was
     * combined groups' documented "not editable once placed" gap. Now:
     * clearing any one member's cell clears every sibling row for this
     * exact occurrence (same group + same period + same draft/published
     * status -- a group meeting several times a week has one row-set per
     * occurrence, and only THIS one should go). "Moving" a placed group is
     * then clear-then-storeCombined() at the new period, which already
     * works once the old rows are gone.
     *
     * Locked rows (Phase 5) are rejected outright too -- lockSlot() itself
     * already refuses to lock a combined-group row, so the sibling check
     * below is defense-in-depth rather than a reachable-today case, but a
     * lock's whole point is "nothing deletes this but an explicit unlock,"
     * so both the clicked row and every sibling are checked before any
     * write happens (found by the Lock Integrity audit: this endpoint
     * previously deleted a locked slot with no resistance at all).
     */
    /**
     * UAT Test 17 fix: publishes a class/section's MANUAL draft slots
     * (timetable_generation_id IS NULL) -- completely separate from
     * publishGeneration(), which only ever touches generation-tagged
     * rows, and was the only Publish action that existed before this.
     *
     * Deliberately narrower than publishGeneration()'s whole-class
     * archive: only the SPECIFIC published rows occupying the SAME
     * (class, section, bell_timing) as a slot in this manual draft are
     * archived, never the class/section's entire published timetable --
     * a manual draft is usually just a handful of periods, not a full
     * regeneration, and archiving unrelated already-published periods
     * for this same class/section would be a real data-safety
     * regression, not a "replace the timetable" operation like Generate
     * intentionally is.
     *
     * All-or-nothing: a locked published occupant, or a DB-level
     * conflict (e.g. the teacher is already published elsewhere at that
     * bell timing), aborts the whole publish before anything is written.
     */
    public function publishManualDraft(Request $request)
    {
        $this->authorize('publish', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $schoolClassId = (int) $validated['school_class_id'];
        $sectionId = ! empty($validated['section_id']) ? (int) $validated['section_id'] : null;
        $sectionNorm = $sectionId ?? 0;

        $draftSlots = TimetableSlot::with('bellTiming')
            ->where('school_class_id', $schoolClassId)
            ->where('section_id_norm', $sectionNorm)
            ->whereNull('timetable_generation_id')
            ->draft()
            ->get();

        if ($draftSlots->isEmpty()) {
            return back()->with('error', 'No manual draft exists for this class/section to publish.');
        }

        $schoolClass = SchoolClass::findOrFail($schoolClassId);

        foreach ($draftSlots as $draftSlot) {
            $ownershipError = $this->bellTimingOwnershipError($draftSlot->bellTiming, $schoolClass);
            if ($ownershipError) {
                return back()->with('error', "Cannot publish: {$ownershipError}");
            }
        }

        try {
            $publishedCount = DB::transaction(function () use ($draftSlots, $schoolClassId, $sectionNorm) {
                $count = 0;

                foreach ($draftSlots as $draftSlot) {
                    $existingPublished = TimetableSlot::where('school_class_id', $schoolClassId)
                        ->where('section_id_norm', $sectionNorm)
                        ->where('bell_timing_id', $draftSlot->bell_timing_id)
                        ->published()
                        ->first();

                    if ($existingPublished) {
                        if ($existingPublished->is_locked) {
                            throw new \RuntimeException(
                                "\"{$draftSlot->bellTiming->period_name}\" ({$draftSlot->bellTiming->day_of_week}) is currently locked in the published timetable -- unlock it first."
                            );
                        }

                        $existingPublished->update(['status' => TimetableSlot::STATUS_ARCHIVED]);
                    }

                    $draftSlot->update(['status' => TimetableSlot::STATUS_PUBLISHED]);
                    $count++;
                }

                return $count;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Cannot publish: ' . $e->getMessage());
        } catch (\Illuminate\Database\QueryException $e) {
            if ((int) $e->errorInfo[1] === 1062) {
                return back()->with('error', 'Cannot publish: one of these periods conflicts with an existing published lesson elsewhere (e.g. the teacher is already published at the same time in another class).');
            }

            throw $e;
        }

        activity()->causedBy(Auth::user())->performedOn($schoolClass)
            ->withProperties(['school_class_id' => $schoolClassId, 'section_id' => $sectionId, 'published_count' => $publishedCount])
            ->log('timetable_manual_draft_published');

        $this->notifyAffectedTeachers($draftSlots->load('schoolClass'));

        return back()->with('success', "Manual draft published -- {$publishedCount} period(s) are now live for {$schoolClass->name}.");
    }

    /**
     * Phase 2.6: notify every teacher (primary or co-teacher) who has a
     * slot in what was just published -- nothing did this before. One
     * notification per teacher, summarizing every class they're affected
     * by and how many of their own periods changed (not the total
     * published count, which may include other teachers' periods too).
     * $slots must have the schoolClass relation already loaded.
     */
    private function notifyAffectedTeachers(\Illuminate\Support\Collection $slots): void
    {
        $byTeacher = [];
        foreach ($slots as $slot) {
            foreach (array_filter([$slot->teacher_id, $slot->co_teacher_id]) as $teacherId) {
                $byTeacher[$teacherId]['count'] = ($byTeacher[$teacherId]['count'] ?? 0) + 1;
                $byTeacher[$teacherId]['classes'][$slot->schoolClass->name ?? 'Unknown'] = true;
            }
        }

        if (empty($byTeacher)) {
            return;
        }

        $logins = \App\Models\TeacherLogin::whereIn('teacher_id', array_keys($byTeacher))->get()->keyBy('teacher_id');

        foreach ($byTeacher as $teacherId => $data) {
            $login = $logins->get($teacherId);
            if ($login) {
                $login->notify(new \App\Notifications\TimetablePublishedNotification(array_keys($data['classes']), $data['count']));
            }
        }
    }

    public function destroy($id)
    {
        $slot = TimetableSlot::findOrFail($id);
        $this->authorize('delete', $slot);

        if ($slot->is_locked) {
            return back()->with('error', 'This lesson is locked -- unlock it first to clear it.');
        }

        if ($slot->combined_class_group_id) {
            $siblings = TimetableSlot::where('combined_class_group_id', $slot->combined_class_group_id)
                ->where('bell_timing_id', $slot->bell_timing_id)
                ->where('status', $slot->status)
                ->get();

            foreach ($siblings as $sibling) {
                $this->authorize('delete', $sibling);
                if ($sibling->is_locked) {
                    return back()->with('error', 'This lesson is locked -- unlock it first to clear it.');
                }
            }

            DB::transaction(function () use ($siblings) {
                foreach ($siblings as $sibling) {
                    $sibling->delete();
                }
            });

            activity()->causedBy(Auth::user())->performedOn($slot->combinedClassGroup ?? $slot)
                ->withProperties(['bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status, 'member_count' => $siblings->count()])
                ->log('combined_group_cleared');

            return back()->with('success', 'Combined group slot cleared for all ' . $siblings->count() . ' member classes.');
        }

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id, 'status' => $slot->status])
            ->log('timetable_slot_cleared');

        $slot->delete();

        return back()->with('success', 'Timetable slot cleared.');
    }

    /**
     * Phase 5 (Locked Lessons): pins a slot so Auto-Fix's chain search will
     * never select it as a blocker to relocate, and the generator will
     * carry it forward unchanged into the next draft it builds for this
     * class rather than treating its period as available. Gated the same
     * as editing the slot (TimetableSlotPolicy::update()) -- locking is a
     * property of the row, not a new administrative capability; whoever
     * could already move this lesson can also decide it shouldn't move.
     * Combined-group and archived rows are rejected the same way update()
     * already rejects them -- neither makes sense to lock.
     */
    public function lockSlot(TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        if ($slot->combined_class_group_id) {
            return back()->with('error', 'This is a combined-group lesson -- it can\'t be locked from a single cell.');
        }

        if ($slot->status === TimetableSlot::STATUS_ARCHIVED) {
            return back()->with('error', 'This slot is archived history from a past publish -- it can no longer be locked.');
        }

        $slot->update(['is_locked' => true]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id])
            ->log('timetable_slot_locked');

        return back()->with('success', 'Lesson locked -- Auto-Fix and future Rebalance will never move it, and it will be carried forward when this class is regenerated.');
    }

    /**
     * Deliberately has NEITHER of lockSlot()'s two guards -- this is not an
     * oversight. lockSlot() guards against CREATING a new combined/archived
     * + locked row; unlockSlot() is the recovery path for one that already
     * exists, however it got there (e.g. publishGeneration()'s bulk
     * archive of a class's previously-published slots has no is_locked
     * check the way the single-slot publish() path does, so an
     * archived+locked row is a real, reachable state, not just a
     * hypothetical one). update()/destroy()/publish() all tell the caller
     * to "unlock it first" when they hit a locked row -- blocking unlock
     * for ANY status would turn that message into a permanent dead end for
     * whichever row reached that combination, for zero benefit: is_locked
     * has no effect on an archived row's behaviour anywhere in the
     * codebase (BellTimingDependencyChecker/TimetableAutoFixService/
     * TimetableRebalanceService all exclude STATUS_ARCHIVED independently
     * of is_locked, not conditioned on it), and a combined-group row can
     * never actually reach is_locked=true through lockSlot() itself.
     */
    public function unlockSlot(TimetableSlot $slot)
    {
        $this->authorize('update', $slot);

        $slot->update(['is_locked' => false]);

        activity()->causedBy(Auth::user())->performedOn($slot)
            ->withProperties(['school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id, 'bell_timing_id' => $slot->bell_timing_id])
            ->log('timetable_slot_unlocked');

        return back()->with('success', 'Lesson unlocked.');
    }

    /**
     * "Generate (Beta)" -- dispatches GenerateTimetableJob for one or more
     * classes' current academic session in a single solver run (a teacher
     * who teaches several of the selected classes gets one globally-
     * consistent schedule, not several independently-solved ones that
     * could silently double-book them). The TimetableGeneration row is
     * created here, synchronously, before dispatch, so the UI has an id to
     * poll immediately regardless of queue latency (same pattern as
     * FinancialYearClosing/StageYearClosingJob). Called from both the
     * per-class grid's Generate button (a one-element array) and the
     * whole-school scope-selection screen (many) -- same endpoint, same
     * validation, no duplicated generation-row logic between the two.
     */
    public function generate(Request $request)
    {
        $this->authorize('generate', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_ids' => 'required|array|min:1',
            'school_class_ids.*' => 'integer|exists:school_classes,id',
            'style' => 'nullable|in:rotating,fixed_daily',
        ]);

        $session = AcademicSession::current()->first();

        $generation = TimetableGeneration::create([
            'academic_year' => $session?->code,
            'academic_session_id' => $session?->id,
            'school_class_ids' => array_values(array_unique(array_map('intval', $validated['school_class_ids']))),
            'style' => $validated['style'] ?? TimetableGeneration::STYLE_ROTATING,
            'status' => TimetableGeneration::STATUS_QUEUED,
            'requested_by' => Auth::id(),
        ]);

        GenerateTimetableJob::dispatch($generation->id);

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids, 'style' => $generation->style])
            ->log('timetable_generation_requested');

        return response()->json([
            'generation_id' => $generation->id,
            'status_url' => route('timetable.generation.status', $generation),
            'review_url' => route('timetable.generation.review', $generation),
        ]);
    }

    /**
     * Scope-selection screen for a whole-school (or any multi-class)
     * generation -- the single-class Generate button on the grid page
     * skips straight to generate(); this is the entry point for picking
     * more than one class at a time.
     */
    public function showGenerateForm()
    {
        $this->authorize('generate', TimetableSlot::class);

        $classes = SchoolClass::active()->orderByOrder()->get();

        return view('admin.timetable.generate', compact('classes'));
    }

    /**
     * Batch review for a (possibly multi-class) generation: overall stats,
     * a per-class placed/unplaced breakdown (from the generation's own
     * stored report -- no new queries needed), and all unplaced-lesson
     * sentences grouped in one place, since a whole-school run's cell-
     * level detail still lives on each class's own draft grid
     * (?school_class_id=X&status=draft), not here.
     */
    public function generationReview(TimetableGeneration $generation)
    {
        $this->authorize('viewAny', TimetableSlot::class);
        $this->authorize('viewGenerationReview', [TimetableSlot::class, $generation]);

        $classes = SchoolClass::whereIn('id', $generation->school_class_ids)->orderByOrder()->get()->keyBy('id');

        $placementsByClass = collect($generation->report['placements'] ?? [])->groupBy('school_class_id');
        $unplacedByClass = collect($generation->report['unplaced'] ?? [])
            ->flatMap(fn ($u) => collect($u['class_ids'] ?? [])->map(fn ($classId) => ['class_id' => $classId, 'reason' => $u['reason']]))
            ->groupBy('class_id');

        $perClass = collect($generation->school_class_ids)->map(function ($classId) use ($classes, $placementsByClass, $unplacedByClass) {
            return [
                'class_id' => $classId,
                'class_name' => $classes->get($classId)?->name ?? "Class #{$classId}",
                'placed' => $placementsByClass->get($classId, collect())->count(),
                'unplaced' => $unplacedByClass->get($classId, collect())->count(),
            ];
        })->sortBy('class_name')->values();

        $unplacedSentences = collect($generation->report['unplaced'] ?? [])->pluck('reason')->values();

        // T6 items 1-2: class-teacher clashes and fixed-daily-incompatible
        // assignments are reported here, not as ordinary unplaced lessons.
        $warnings = collect($generation->report['warnings'] ?? [])->values();

        return view('admin.timetable.generation-review', compact('generation', 'perClass', 'unplacedSentences', 'warnings'));
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
        $this->authorize('viewGenerationReview', [TimetableSlot::class, $generation]);

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
            'warnings' => $generation->status === TimetableGeneration::STATUS_COMPLETED
                ? ($generation->report['warnings'] ?? [])
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

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids])
            ->log('timetable_generation_published');

        $publishedSlots = TimetableSlot::published()
            ->where('timetable_generation_id', $generation->id)
            ->with('schoolClass')
            ->get();
        $this->notifyAffectedTeachers($publishedSlots);

        return $this->redirectAfterGenerationAction($generation)
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

        activity()->causedBy(Auth::user())->performedOn($generation)
            ->withProperties(['school_class_ids' => $generation->school_class_ids])
            ->log('timetable_generation_discarded');

        return $this->redirectAfterGenerationAction($generation)
            ->with('success', 'Draft discarded -- the live timetable is unchanged.');
    }

    /**
     * A single-class generation goes straight back to that class's grid
     * (unchanged behaviour from before whole-school generation existed);
     * a multi-class one goes to the review page, which still shows the
     * final per-class outcome after publish/discard.
     */
    private function redirectAfterGenerationAction(TimetableGeneration $generation)
    {
        if (count($generation->school_class_ids) === 1) {
            return redirect()->route('timetable.index', ['school_class_id' => $generation->school_class_ids[0]]);
        }

        return redirect()->route('timetable.generation.review', $generation);
    }

    /**
     * Phase 3 (Smart Suggestions): a conflict is never returned bare --
     * when one is found, this also asks TimetableSuggestionService for
     * concrete, already-validated alternatives (move the new lesson, or
     * move whatever's blocking it), so the manual editor's conflict alert
     * can offer real options instead of just naming the problem.
     */
    public function checkConflictsApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $status = $request->get('status') === 'draft' ? TimetableSlot::STATUS_DRAFT : TimetableSlot::STATUS_PUBLISHED;
        $result = $this->checkSlotConflicts($request, $status);

        if ($result['conflict']) {
            // Deliberately no ignore_slot_id here -- unchanged from before:
            // a suggestion trial is evaluated against a DIFFERENT
            // bell_timing_id each time, a different natural key, so
            // whatever row the initial check excluded doesn't apply and
            // must be independently re-resolved per candidate (both
            // suggestion methods already do this internally).
            //
            // academic_year IS included, resolved once here rather than
            // inside TimetableConflictResolver::check() on every single
            // candidate evaluation -- it's invariant for the whole
            // request (same AcademicSession::current() value regardless
            // of which candidate period is being tried), so this removes
            // one repeated, identical query per candidate without
            // changing what value is ever used.
            $placement = [
                'school_class_id' => $request->get('school_class_id'),
                'section_id' => $request->get('section_id') ?: null,
                'bell_timing_id' => $request->get('bell_timing_id'),
                'teacher_id' => $request->get('teacher_id'),
                'co_teacher_id' => $request->get('co_teacher_id') ?: null,
                'subject_id' => $request->get('subject_id'),
                'room_number' => $request->get('room_number'),
                'status' => $status,
                'academic_year' => AcademicSession::current()->first()?->code,
            ];

            $suggestions = new TimetableSuggestionService();
            $result['suggestions'] = [
                'move_lesson' => $suggestions->suggestForNewPlacement($placement),
                'relocate_blocker' => $suggestions->suggestBlockerRelocation($placement),
            ];
        }

        return response()->json($result);
    }

    /**
     * Phase 4 (Auto-Fix): applies one relocate_blocker suggestion from
     * checkConflictsApi as a single atomic action. Admin-only (moves a
     * class-section the caller may have no claim over); re-validates
     * everything against live data before writing anything -- see
     * TimetableAutoFixService.
     */
    public function autoFixRelocateBlocker(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
            'blocking_slot_id' => 'required|integer|exists:timetable_slots,id',
            'blocker_new_bell_timing_id' => 'required|integer|exists:bell_timings,id',
        ]);

        $newPlacement = collect($validated)->except(['blocking_slot_id', 'blocker_new_bell_timing_id'])->all();

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement,
            $validated['blocking_slot_id'],
            $validated['blocker_new_bell_timing_id']
        );

        return response()->json($result, $result['applied'] ? 200 : 422);
    }

    /**
     * Phase 4 (Auto-Fix, chain repair): read-only preview of a multi-step
     * fix -- gated the same as the live conflict-check/relocate-blocker
     * preview (viewAny), since nothing is mutated here; applyAutoFixChain()
     * below is where real authorization is enforced. academic_year is
     * never client-supplied, matching store()/checkSlotConflicts() -- this
     * preview must reflect the exact year a real placement would use.
     */
    public function autoFixPreviewChain(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
            // Issue #14: the draft review grid's own active generation
            // (present only when reviewing a specific generation's draft --
            // never invented for the plain published-grid Auto-Fix case).
            // Never trusted on the strength of the client alone: it must
            // both exist and genuinely cover the requested class, the same
            // ownership check viewGenerationReview() already applies to
            // reviewing a generation at all.
            'timetable_generation_id' => ['nullable', 'integer', 'exists:timetable_generations,id', $this->generationCoversClassRule($request)],
        ]);

        $newPlacement = array_merge($this->withoutNullGenerationId($validated), [
            'academic_year' => AcademicSession::current()->first()?->code,
        ]);

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        return response()->json($result);
    }

    /**
     * Phase 4 (Auto-Fix, chain repair): applies a chain previously shown
     * by autoFixPreviewChain() -- admin-only (moves class-sections the
     * caller may have no claim over, same reasoning as autoFix() on
     * TimetableSlotPolicy already documents for the single-hop case).
     * Re-validates everything against live data before writing anything --
     * see TimetableAutoFixService::applyChainFix().
     */
    public function autoFixApplyChain(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'teacher_id' => 'required|exists:teachers,id',
            'co_teacher_id' => 'nullable|exists:teachers,id|different:teacher_id',
            'subject_id' => 'nullable|exists:subjects,id',
            'room_number' => 'nullable|string|max:50',
            'status' => 'nullable|in:draft,published',
            'steps' => 'present|array',
            'steps.*.slot_id' => 'required_with:steps|integer|exists:timetable_slots,id',
            'steps.*.to_bell_timing_id' => 'required_with:steps|integer|exists:bell_timings,id',
            // Issue #14: see autoFixPreviewChain() -- carries the draft
            // review's own generation id through so the slot this creates
            // is captured by publishGeneration()'s generation-scoped
            // promote sweep, instead of staying an orphaned draft forever.
            'timetable_generation_id' => ['nullable', 'integer', 'exists:timetable_generations,id', $this->generationCoversClassRule($request)],
        ]);

        $newPlacement = array_merge(
            collect($this->withoutNullGenerationId($validated))->except('steps')->all(),
            ['academic_year' => AcademicSession::current()->first()?->code]
        );

        $result = (new TimetableAutoFixService())->applyChainFix($newPlacement, $validated['steps']);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }

    /**
     * Issue #14 wiring: a client-supplied timetable_generation_id is never
     * trusted on its own -- it must name a generation that genuinely
     * covers the class this Auto-Fix request is placing a lesson into,
     * reusing the exact same TimetableGeneration::school_class_ids
     * membership check TimetableSlotPolicy::viewGenerationReview() already
     * applies to reviewing a generation at all, rather than inventing a
     * new authorization rule.
     */
    private function generationCoversClassRule(Request $request): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
            $generation = TimetableGeneration::find($value);
            $schoolClassId = (int) $request->input('school_class_id');

            if (!$generation || !in_array($schoolClassId, $generation->school_class_ids ?? [], true)) {
                $fail('This generation does not cover the selected class.');
            }
        };
    }

    /**
     * A client that has no active generation to review (the ordinary
     * published-grid Auto-Fix case) sends timetable_generation_id as an
     * explicit JSON null, not an absent key -- dropping it here keeps that
     * indistinguishable from "never supplied" by the time it reaches
     * TimetableAutoFixService, so an updateOrCreate() match against an
     * existing row can never have its real generation_id overwritten with
     * a null the client only sent because it had nothing to report.
     */
    private function withoutNullGenerationId(array $validated): array
    {
        if (($validated['timetable_generation_id'] ?? null) === null) {
            unset($validated['timetable_generation_id']);
        }

        return $validated;
    }

    /**
     * Rebalancing Engine (read-only preview): scores the selected
     * class-section's current lesson placement and proposes a small,
     * bounded set of legal swaps/relocations that would improve it.
     * Writes nothing -- gated the same as every other read-only
     * preview/scan in this controller (viewAny). academic_year is never
     * client-supplied, matching every other write/preview path.
     */
    public function rebalancePreview(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'status' => 'nullable|in:draft,published',
        ]);

        $status = $validated['status'] ?? TimetableSlot::STATUS_PUBLISHED;
        $academicYear = AcademicSession::current()->first()?->code;

        $result = (new TimetableRebalanceService())->analyze(
            (int) $validated['school_class_id'],
            $validated['section_id'] ?? null,
            $academicYear,
            $status
        );

        return response()->json($result);
    }

    /**
     * Rebalancing Engine (apply): admin-only, same reasoning as autoFix()
     * on TimetableSlotPolicy -- a rebalance can move a lesson taught by a
     * teacher the caller has no particular claim over. Re-validates every
     * movement against live data before writing anything (see
     * TimetableRebalanceService::apply()); the whole batch is one
     * transaction, so a stale movement aborts everything, nothing partial.
     */
    public function rebalanceApply(Request $request)
    {
        $this->authorize('autoFix', TimetableSlot::class);

        // Production hardening: previously only 'movements' itself was
        // validated (present, non-empty array) -- an invalid/stale
        // to_bell_timing_id for a relocate movement was silently treated
        // by TimetableConflictResolver::check() as "no conflict" (a
        // not-found BellTiming short-circuits to an empty conflict list),
        // so TimetableRebalanceService::applyRelocation() would reach
        // $slot->update(['bell_timing_id' => $bogusId]) and let the
        // database's own foreign-key constraint reject it as an uncaught
        // QueryException (a 500), instead of the clean 422 every other
        // rejection in this class produces. Mirrors autoFixApplyChain()'s
        // own 'steps.*' validation discipline immediately above.
        $validated = $request->validate([
            'movements' => 'required|array|min:1',
            'movements.*.type' => 'required|in:swap,relocate',
            'movements.*.slot_id' => 'required_if:movements.*.type,relocate|integer|exists:timetable_slots,id',
            'movements.*.to_bell_timing_id' => 'required_if:movements.*.type,relocate|integer|exists:bell_timings,id',
            'movements.*.slot_a_id' => 'required_if:movements.*.type,swap|integer|exists:timetable_slots,id',
            'movements.*.slot_b_id' => 'required_if:movements.*.type,swap|integer|exists:timetable_slots,id',
        ]);

        $academicYear = AcademicSession::current()->first()?->code;

        $result = (new TimetableRebalanceService())->apply($validated['movements'], $academicYear);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }

    /**
     * Swap Engine (read-only preview): validates trading two lessons'
     * periods without writing anything -- TimetableSwapService::preview()
     * delegates straight to TimetableSuggestionService::checkSwap(), the
     * one authoritative TimetableConflictResolver underneath it all.
     * Gated the same as the live conflict-check AJAX endpoint (viewAny)
     * since nothing is mutated here; the apply action below is where real
     * per-slot authorization is enforced.
     */
    public function swapPreviewApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $validated = $request->validate([
            'slot_a_id' => 'required|integer|exists:timetable_slots,id',
            'slot_b_id' => 'required|integer|exists:timetable_slots,id',
        ]);

        $result = (new TimetableSwapService())->preview((int) $validated['slot_a_id'], (int) $validated['slot_b_id']);

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'conflicts' => $result['conflicts'],
            'slot_a' => $result['slot_a'] ? $this->describeSlotForSwap($result['slot_a']) : null,
            'slot_b' => $result['slot_b'] ? $this->describeSlotForSwap($result['slot_b']) : null,
        ]);
    }

    /**
     * Swap Engine (apply): a swap is, from an authorization standpoint, an
     * update to both rows at once -- the same TimetableSlotPolicy::update()
     * gate a normal edit requires must pass for EACH side individually
     * (an admin always passes both; a teacher must be assigned to both
     * slots' class-sections). Re-validated from scratch against live,
     * row-locked data inside TimetableSwapService -- the preview above is
     * only ever a hint, never trusted for the actual write.
     */
    public function swapSlots(Request $request)
    {
        $validated = $request->validate([
            'slot_a_id' => 'required|integer|exists:timetable_slots,id',
            'slot_b_id' => 'required|integer|exists:timetable_slots,id',
        ]);

        $slotA = TimetableSlot::findOrFail($validated['slot_a_id']);
        $slotB = TimetableSlot::findOrFail($validated['slot_b_id']);

        $this->authorize('update', $slotA);
        $this->authorize('update', $slotB);

        $result = (new TimetableSwapService())->apply($slotA->id, $slotB->id);

        return response()->json($result, $result['applied'] ? 200 : 422);
    }

    /** Small, swap-preview-only view model -- avoids leaking full model internals into the JSON response. */
    private function describeSlotForSwap(TimetableSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'class' => $slot->schoolClass->name ?? null,
            'section' => $slot->section->name ?? null,
            'subject' => $slot->subject->name ?? null,
            'teacher' => $slot->teacher->name ?? null,
            'co_teacher' => $slot->coTeacher->name ?? null,
            'room_number' => $slot->room_number,
            'day_of_week' => $slot->bellTiming->day_of_week ?? null,
            'period_name' => $slot->bellTiming->period_name ?? null,
            'bell_timing_id' => $slot->bell_timing_id,
        ];
    }

    /**
     * T4b item 4: scoped to a single status so a draft proposal is free
     * to differ from what's live, but still can't conflict with itself.
     *
     * Phase 2 (Conflict Resolver): this is now a thin adapter from the
     * HTTP request shape to TimetableConflictResolver -- the actual rule
     * evaluation lives there so manual editing, the check-conflicts AJAX
     * endpoint, and any future caller (drag/drop, Auto-Fix, Rebalance,
     * API) all go through the same one authoritative implementation
     * instead of each re-deriving their own conflict logic.
     */
    private function checkSlotConflicts(Request $request, string $status = TimetableSlot::STATUS_PUBLISHED): array
    {
        return (new TimetableConflictResolver())->check([
            'school_class_id' => $request->get('school_class_id'),
            'section_id' => $request->get('section_id') ?: null,
            'bell_timing_id' => $request->get('bell_timing_id'),
            'teacher_id' => $request->get('teacher_id'),
            'co_teacher_id' => $request->get('co_teacher_id') ?: null,
            'subject_id' => $request->get('subject_id'),
            'room_number' => $request->get('room_number'),
            'status' => $status,
            // Never client-supplied, same reasoning as store()/storeCombined():
            // this is also what actually gets persisted (see store()), so the
            // preview must be checked against the exact year that will be
            // saved, not whatever the client happens to send.
            'academic_year' => AcademicSession::current()->first()?->code,
            'ignore_slot_id' => $request->get('id'),
        ]);
    }
}
