<?php

namespace App\Http\Controllers;

use App\Models\BellTiming;
use App\Models\Student;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\Timetable\BellTimingDependencyChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class BellTimingController extends Controller
{
    public function __construct(private BellTimingDependencyChecker $dependencyChecker)
    {
    }

    /**
     * Display a listing of the bell timings.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $query = BellTiming::with('createdBy');
        
        // Filter by day of week
        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }
        
        // Filter by class section
        if ($request->filled('class_section')) {
            $query->where('class_section', $request->class_section);
        }
        
        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        // Filter by academic year
        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        
        $bellTimings = $query->orderBy('day_of_week')
                            ->orderBy('order_index')
                            ->paginate(20);
        
        // Get unique values for filters
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter();
        
        return view('bell-timing.index', compact('bellTimings', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Show the form for creating a new bell timing.
     */
    public function create()
    {
        $this->authorize('create', BellTiming::class);
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];
        
        return view('bell-timing.create', compact('daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Store a newly created bell timing in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', BellTiming::class);
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts. Same fix as bulkCreate(): a strict-
        // inequality overlap test (existing.start < new.end AND
        // existing.end > new.start) instead of the previous inclusive
        // whereBetween, so a period whose start_time exactly equals
        // another period's end_time (a genuine back-to-back schedule) is
        // no longer flagged as a false-positive conflict.
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where('start_time', '<', $request->end_time)
                              ->where('end_time', '>', $request->start_time)
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' .
                                     $conflicts->first()->period_name . ' (' .
                                     $conflicts->first()->start_time . ' - ' .
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming = new BellTiming();
        $bellTiming->fill($request->all());
        $bellTiming->created_by = Auth::id(); // Current authenticated user
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing created successfully!');
    }

    /**
     * Display the specified bell timing.
     */
    public function show(BellTiming $bellTiming)
    {
        $this->authorize('view', $bellTiming);
        $bellTiming->load('createdBy');
        return view('bell-timing.show', compact('bellTiming'));
    }

    /**
     * Show the form for editing the specified bell timing.
     */
    public function edit(BellTiming $bellTiming)
    {
        $this->authorize('update', $bellTiming);
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        // Sourced from BellTiming/SchoolClass (not Student::class) so the
        // dropdown always contains this row's own class_section, whatever
        // it is -- otherwise the select falls back to its first option
        // ("All Classes") and saving silently blanks class_section.
        $classSections = \App\Models\SchoolClass::active()->orderByOrder()->pluck('name')
            ->merge(BellTiming::whereNotNull('class_section')->distinct()->pluck('class_section'))
            ->unique()
            ->sort()
            ->values();
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];

        return view('bell-timing.edit', compact('bellTiming', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Update the specified bell timing in storage.
     */
    public function update(Request $request, BellTiming $bellTiming)
    {
        $this->authorize('update', $bellTiming);
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts (excluding current record). Same
        // strict-inequality overlap fix as store()/bulkCreate().
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where('id', '!=', $bellTiming->id)
                              ->where('start_time', '<', $request->end_time)
                              ->where('end_time', '>', $request->start_time)
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' .
                                     $conflicts->first()->period_name . ' (' .
                                     $conflicts->first()->start_time . ' - ' .
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming->fill($request->all());
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing updated successfully!');
    }

    /**
     * Delete-confirmation screen. Read-only: runs the exact same
     * dependency check destroy() re-runs before actually deleting, so
     * what the admin sees here is never stale by the time they click
     * through -- if it somehow changes in between, destroy()'s own
     * re-check catches it rather than trusting this page's snapshot.
     */
    public function confirmDelete(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);

        $dependencies = $this->dependencyChecker->check($bellTiming->id);

        return view('bell-timing.confirm-delete', [
            'bellTiming' => $bellTiming,
            'dependencies' => $dependencies,
            'blocked' => $this->dependencyChecker->isBlocked($dependencies),
        ]);
    }

    /**
     * Dependency Resolution Phase A: read-only detail for exactly which
     * records are blocking this Bell Timing's deletion -- not just "1
     * draft timetable slot", but which class/subject/teacher/date. Purely
     * additive to confirmDelete(): same authorization, same dependency
     * checker, just describe() instead of check(), so this screen can
     * never disagree with what confirmDelete()/destroy() see. No writes,
     * no Reassign/Deactivate actions yet -- those are later phases.
     */
    public function dependencyDetail(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);

        $dependencies = $this->dependencyChecker->check($bellTiming->id);
        $detail = $this->dependencyChecker->describe([$bellTiming->id])[$bellTiming->id]
            ?? ['timetable_slots' => [], 'teacher_substitutions' => [], 'teacher_availabilities' => []];

        return view('bell-timing.dependency-detail', [
            'bellTiming' => $bellTiming,
            'blocked' => $this->dependencyChecker->isBlocked($dependencies),
            'detail' => $detail,
        ]);
    }

    /**
     * Phase B: read-only reassignment form for one blocking timetable
     * slot. Deliberately does NOT write anything itself -- the form this
     * renders submits directly to Admin\TimetableController::update()
     * (route timetable.update), completely unmodified, so every
     * validation rule, conflict check, transaction, and authorization
     * check that endpoint already enforces applies here exactly as it
     * does everywhere else it's used. This method's only two jobs: (1)
     * make sure the admin can only reach a slot that actually belongs to
     * this Bell Timing, and (2) once update() redirects back here on
     * success, re-run the dependency check live so nothing is ever
     * inferred from a stale browser-side count.
     */
    public function reassignSlotForm(BellTiming $bellTiming, TimetableSlot $slot)
    {
        $this->authorize('delete', $bellTiming);

        // The slot id comes from the URL -- never trust it belongs to
        // this Bell Timing just because both ids were supplied together.
        abort_unless((int) $slot->bell_timing_id === $bellTiming->id, 404);

        $reassignable = $slot->status !== TimetableSlot::STATUS_ARCHIVED && ! $slot->is_locked;

        if (! $reassignable) {
            return redirect()->route('bell-timing.dependencies', $bellTiming)
                ->with('error', 'This timetable slot is archived or locked and cannot be reassigned from here.');
        }

        $recheck = null;
        if (session('success')) {
            // Admin\TimetableController::update() redirects back() on
            // success, which returns here since this page was the
            // referrer. Re-fetch and re-check fresh rather than trusting
            // anything remembered from before the reassignment.
            $bellTiming->refresh();
            $dependencies = $this->dependencyChecker->check($bellTiming->id);
            $recheck = [
                'blocked' => $this->dependencyChecker->isBlocked($dependencies),
                'summary' => $this->dependencyChecker->summarize($dependencies),
            ];
        }

        $targets = BellTiming::active()
            ->where('id', '!=', $bellTiming->id)
            ->orderBy('day_of_week')
            ->orderBy('order_index')
            ->get();

        return view('bell-timing.reassign-slot', [
            'bellTiming' => $bellTiming,
            'slot' => $slot->fresh(['schoolClass', 'section', 'subject', 'teacher', 'coTeacher']),
            'targets' => $targets,
            'recheck' => $recheck,
        ]);
    }

    /**
     * Phase B: read-only reassignment form for one blocking teacher
     * substitution. Same discipline as reassignSlotForm() -- the form
     * submits straight to Admin\TeacherSubstitutionController::update()
     * (route admin.teacher-substitutions.update), completely unmodified. That
     * endpoint redirects to admin.teacher-substitutions.index on success
     * (its own existing behavior, left untouched), not back here, so
     * unlike reassignSlotForm() there is no same-page live recheck to
     * show -- the form links back to the dependency screen instead,
     * which always re-checks fresh whenever it's opened.
     */
    public function reassignSubstitutionForm(BellTiming $bellTiming, TeacherSubstitution $substitution)
    {
        $this->authorize('delete', $bellTiming);

        abort_unless((int) $substitution->bell_timing_id === $bellTiming->id, 404);

        $targets = BellTiming::active()
            ->where('id', '!=', $bellTiming->id)
            ->orderBy('day_of_week')
            ->orderBy('order_index')
            ->get();

        return view('bell-timing.reassign-substitution', [
            'bellTiming' => $bellTiming,
            'substitution' => $substitution->fresh(['absentTeacher', 'class', 'section', 'subject']),
            'targets' => $targets,
        ]);
    }

    /**
     * Phase C: read-only confirmation screen before deactivating a Bell
     * Timing. Same authorization and same live dependency lookup as
     * dependencyDetail() -- shown so the admin sees exactly what they're
     * choosing to leave alone (not delete) before confirming.
     */
    public function deactivateConfirm(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);

        $dependencies = $this->dependencyChecker->check($bellTiming->id);
        $detail = $this->dependencyChecker->describe([$bellTiming->id])[$bellTiming->id]
            ?? ['timetable_slots' => [], 'teacher_substitutions' => [], 'teacher_availabilities' => []];

        return view('bell-timing.deactivate-confirm', [
            'bellTiming' => $bellTiming,
            'blocked' => $this->dependencyChecker->isBlocked($dependencies),
            'detail' => $detail,
        ]);
    }

    /**
     * Phase C: deactivate (never delete) a Bell Timing -- an alternative
     * for when the admin has dependencies they don't want to touch.
     * Deliberately its own small, tightly-scoped write rather than routed
     * through the general update() action above: that endpoint's policy
     * allows admin OR teacher (it's the everyday single-record edit
     * form), which would let a teacher deactivate through the back door.
     * This reuses the same 'delete' ability every other action in this
     * dependency-resolution flow already uses -- admin-only, without
     * inventing a new ability. Idempotent: an already-inactive Bell
     * Timing is a no-op, not an error, so a stale confirmation page or a
     * doubled submission is always safe.
     */
    public function deactivate(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);

        if (! $bellTiming->is_active) {
            return redirect()->route('bell-timing.index')
                ->with('success', 'This Bell Timing is already inactive.');
        }

        DB::transaction(function () use ($bellTiming) {
            $bellTiming->update(['is_active' => false]);
        });

        return redirect()->route('bell-timing.index')
            ->with('success', 'Bell Timing deactivated -- hidden from new schedules, but existing timetable/history records are unchanged.');
    }

    /**
     * Remove the specified bell timing from storage.
     *
     * Never blindly cascades: timetable_slots.bell_timing_id and
     * teacher_availabilities.bell_timing_id both use ON DELETE CASCADE,
     * so an unguarded delete() here would silently destroy a published
     * timetable slot or a teacher availability block. teacher_substitutions
     * has no cascade at all (default RESTRICT), so it would instead throw
     * a raw, unhandled QueryException straight to the admin as a 500.
     * The dependency check below is re-run here -- independent of
     * confirmDelete()'s own check -- specifically so a dependency created
     * between viewing the confirmation screen and submitting it is still
     * caught immediately before the delete, not after.
     */
    public function destroy(BellTiming $bellTiming)
    {
        $this->authorize('delete', $bellTiming);

        $dependencies = $this->dependencyChecker->check($bellTiming->id);

        if ($this->dependencyChecker->isBlocked($dependencies)) {
            return redirect()->route('bell-timing.delete.confirm', $bellTiming)
                ->with('error', 'Cannot delete this Bell Timing -- it is currently used by ' .
                    $this->dependencyChecker->summarize($dependencies) . '. Resolve these dependencies before deleting.');
        }

        DB::transaction(function () use ($bellTiming) {
            $bellTiming->delete();
        });

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing deleted successfully!');
    }

    /**
     * Bulk Delete step 1: pick which (class_section, day_of_week,
     * academic_year, semester) schedules to delete. Grouped, not a flat
     * per-record list -- an admin should never have to select hundreds
     * of individual periods one at a time. Never hard-codes a period
     * count; whatever exists is whatever's counted.
     */
    public function bulkDeleteForm()
    {
        $this->authorize('bulkManage', BellTiming::class);

        $groups = $this->bulkDeleteGroups();

        return view('bell-timing.bulk-delete', compact('groups'));
    }

    /**
     * Bulk Delete step 2: resolve the selected schedules to real
     * BellTiming rows (server-side, from the tuple keys -- never from a
     * client-supplied id list) and split them into safe/blocked via the
     * shared dependency checker, batched (one query per dependency table
     * regardless of selection size). No writes happen here.
     *
     * Phase D: blocked entries also carry describe()'s per-record detail
     * (the same data dependencyDetail() already renders) so the preview
     * can show WHICH class/subject/teacher/date is blocking each one --
     * not just a count -- and so the view can link straight to the
     * existing Phase A/B/C screens (View Dependencies / Reassign /
     * Deactivate) for each one. Pure addition to what was already being
     * computed here; checkEach()/isBlocked()/summarize() calls and the
     * safe/blocked split itself are unchanged.
     */
    public function bulkDeletePreview(Request $request)
    {
        $this->authorize('bulkManage', BellTiming::class);

        $selections = $this->extractSelectedGroups($request);

        if (empty($selections)) {
            return redirect()->route('bell-timing.bulk-delete')
                ->with('error', 'Please select at least one class/day schedule.');
        }

        $rows = $this->resolveSelectedBellTimings($selections);

        if ($rows->isEmpty()) {
            return redirect()->route('bell-timing.bulk-delete')
                ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
        }

        $dependencies = $this->dependencyChecker->checkEach($rows->pluck('id')->all());

        $safe = [];
        $blocked = [];
        foreach ($rows as $row) {
            $dep = $dependencies[$row->id] ?? [];
            if ($this->dependencyChecker->isBlocked($dep)) {
                $blocked[] = ['bellTiming' => $row, 'reason' => $this->dependencyChecker->summarize($dep)];
            } else {
                $safe[] = $row;
            }
        }

        $blockedIds = array_map(fn ($b) => $b['bellTiming']->id, $blocked);
        $blockedDetail = $this->dependencyChecker->describe($blockedIds);
        foreach ($blocked as &$b) {
            $b['detail'] = $blockedDetail[$b['bellTiming']->id]
                ?? ['timetable_slots' => [], 'teacher_substitutions' => [], 'teacher_availabilities' => []];
        }
        unset($b);

        return view('bell-timing.bulk-delete-preview', [
            'selections' => $selections,
            'groupsSummary' => $this->summarizeSelectedRows($rows),
            'safeCount' => count($safe),
            'blocked' => $blocked,
        ]);
    }

    /**
     * Bulk Delete step 3: re-derive the selection from the same tuple
     * keys and re-run the dependency checker immediately before deleting
     * -- independent of whatever bulkDeletePreview() showed, exactly the
     * same "never trust the earlier screen's snapshot" rule destroy()
     * already follows for a single record. Only ever deletes ids that
     * are safe at this exact moment; blocked ones are preserved and
     * reported, never silently dropped.
     */
    public function bulkDeleteConfirm(Request $request)
    {
        $this->authorize('bulkManage', BellTiming::class);

        $selections = $this->extractSelectedGroups($request);

        if (empty($selections)) {
            return redirect()->route('bell-timing.bulk-delete')
                ->with('error', 'Please select at least one class/day schedule.');
        }

        $rows = $this->resolveSelectedBellTimings($selections);

        if ($rows->isEmpty()) {
            return redirect()->route('bell-timing.bulk-delete')
                ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
        }

        $dependencies = $this->dependencyChecker->checkEach($rows->pluck('id')->all());

        $safeIds = [];
        $blockedCount = 0;
        foreach ($rows as $row) {
            $dep = $dependencies[$row->id] ?? [];
            if ($this->dependencyChecker->isBlocked($dep)) {
                $blockedCount++;
            } else {
                $safeIds[] = $row->id;
            }
        }

        if (empty($safeIds)) {
            return redirect()->route('bell-timing.bulk-delete')
                ->with('error', "Nothing was deleted -- all {$blockedCount} selected Bell Timing(s) are currently blocked by existing dependencies.");
        }

        DB::transaction(function () use ($safeIds) {
            BellTiming::whereIn('id', $safeIds)->delete();
        });

        $deletedCount = count($safeIds);
        $message = "{$deletedCount} Bell Timing(s) deleted.";
        if ($blockedCount > 0) {
            $message .= " {$blockedCount} Bell Timing(s) were protected because dependencies were detected.";
        }

        return redirect()->route('bell-timing.index')->with('success', $message);
    }

    /**
     * The Bulk Delete selection screen's grouped list -- one row per
     * distinct (class_section, day_of_week, academic_year, semester)
     * combination that actually exists, with a live period count. Never
     * assumes a fixed period count or a fixed set of days.
     */
    private function bulkDeleteGroups()
    {
        // Day-of-week ordering is done in PHP, not SQL (MySQL's FIELD()
        // has no portable equivalent -- same reason BellTiming's own
        // getWeeklySchedule()/getTimetableForClass() sort day order in
        // PHP rather than in the query).
        $daysOrder = array_flip(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);

        return BellTiming::selectRaw('class_section, day_of_week, academic_year, semester, COUNT(*) as period_count')
            ->groupBy('class_section', 'day_of_week', 'academic_year', 'semester')
            ->get()
            ->sortBy(fn ($g) => $daysOrder[$g->day_of_week] ?? 99)
            ->sortBy('class_section')
            ->values();
    }

    /**
     * Reads which grouped rows the admin checked and returns their
     * (class_section, day_of_week, academic_year, semester) tuples only
     * -- never a BellTiming id. These tuples are what both
     * bulkDeletePreview() and bulkDeleteConfirm() independently re-query
     * against on every request; a tampered/fabricated tuple simply
     * resolves to zero rows rather than exposing anything, since it can
     * only ever match real rows via a genuine WHERE clause.
     */
    private function extractSelectedGroups(Request $request): array
    {
        // These hidden fields are submitted for every row rendered on the
        // selection screen, not just the ones checked -- so the max: limits
        // here must tolerate whatever the widest real value in the table
        // is, or a single unrelated row (e.g. an academic_year like
        // "2026-2027-WALKTHROUGH", 21 chars) fails validation for the
        // entire request with no visible error (neither selection screen
        // renders $errors), silently bouncing the admin back to the same
        // page. bell_timings.academic_year/semester are plain unconstrained
        // VARCHAR(255) columns, so 50 (matching class_section's own limit)
        // is a generous, still-real ceiling rather than an arbitrary one.
        $request->validate([
            'groups' => 'required|array',
            'groups.*.selected' => 'nullable|string',
            'groups.*.class_section' => 'nullable|string|max:50',
            'groups.*.day_of_week' => 'nullable|string|max:20',
            'groups.*.academic_year' => 'nullable|string|max:50',
            'groups.*.semester' => 'nullable|string|max:50',
        ]);

        return collect($request->input('groups', []))
            ->filter(fn ($g) => ($g['selected'] ?? null) === '1')
            ->map(fn ($g) => [
                'class_section' => ($g['class_section'] ?? '') === '' ? null : $g['class_section'],
                'day_of_week' => ($g['day_of_week'] ?? '') === '' ? null : $g['day_of_week'],
                'academic_year' => ($g['academic_year'] ?? '') === '' ? null : $g['academic_year'],
                'semester' => ($g['semester'] ?? '') === '' ? null : $g['semester'],
            ])
            ->values()
            ->all();
    }

    /**
     * Resolves the exact BellTiming rows for a set of selection tuples in
     * ONE query (an OR of AND-tuple conditions), regardless of how many
     * tuples were selected -- avoids looping a query per selected
     * class/day. This is the sole place selection tuples become real ids.
     */
    private function resolveSelectedBellTimings(array $selections): \Illuminate\Support\Collection
    {
        return BellTiming::where(function ($outer) use ($selections) {
            foreach ($selections as $selection) {
                $outer->orWhere(function ($inner) use ($selection) {
                    $this->applySelectionTuple($inner, $selection);
                });
            }
        })->get();
    }

    private function applySelectionTuple($query, array $selection): void
    {
        foreach (['class_section', 'day_of_week', 'academic_year', 'semester'] as $column) {
            $value = $selection[$column];
            if (is_null($value)) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }
    }

    /**
     * Groups already-resolved rows back into the same class/day/period-count
     * shape the selection screen uses, purely for the preview's "Selected:"
     * list -- derived from the freshly-queried rows, never from anything
     * the client echoed back.
     */
    private function summarizeSelectedRows(\Illuminate\Support\Collection $rows): array
    {
        return $rows->groupBy(fn (BellTiming $r) => implode('|', [
                $r->class_section ?? '',
                $r->day_of_week ?? '',
                $r->academic_year ?? '',
                $r->semester ?? '',
            ]))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'class_section' => $first->class_section,
                    'day_of_week' => $first->day_of_week,
                    'academic_year' => $first->academic_year,
                    'semester' => $first->semester,
                    'period_count' => $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Bulk Edit step 1: pick which (class_section, day_of_week,
     * academic_year, semester) schedules to edit. Same grouped list Bulk
     * Delete already uses (bulkDeleteGroups() is reused unmodified) --
     * one selection screen serves both destructive operations.
     */
    public function bulkEditForm()
    {
        $this->authorize('bulkManage', BellTiming::class);

        $groups = $this->bulkDeleteGroups();

        return view('bell-timing.bulk-edit', compact('groups'));
    }

    /**
     * Bulk Edit step 2: resolve the selection (server-side, from the
     * tuple keys -- reuses extractSelectedGroups()/resolveSelectedBellTimings()
     * unmodified) and offer the distinct period_name values actually
     * present, so the admin can only ever target a period that exists in
     * at least one selected schedule.
     */
    public function bulkEditTarget(Request $request)
    {
        $this->authorize('bulkManage', BellTiming::class);

        $selections = $this->extractSelectedGroups($request);

        if (empty($selections)) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'Please select at least one class/day schedule.');
        }

        $rows = $this->resolveSelectedBellTimings($selections);

        if ($rows->isEmpty()) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
        }

        $periodNames = $rows->pluck('period_name')->unique()->sort()->values();

        return view('bell-timing.bulk-edit-target', [
            'selections' => $selections,
            'periodNames' => $periodNames,
            'groupsSummary' => $this->summarizeSelectedRows($rows),
        ]);
    }

    /**
     * Bulk Edit step 3: match the target period per (class, day) --
     * never by position -- and show every selected schedule's outcome
     * individually: matched (with an old->new diff and a dependency
     * warning if one applies, via the shared checker's existing
     * checkEach()/isBlocked()/summarize(), unmodified), missing (that
     * exact period_name doesn't exist there), or ambiguous (it exists
     * more than once -- the schema has no unique constraint preventing
     * that, so it's checked for rather than assumed away). No writes
     * happen here. Nothing is ever guessed.
     */
    public function bulkEditPreview(Request $request)
    {
        $this->authorize('bulkManage', BellTiming::class);

        // The target screen (bulk-edit-target) that submits here is itself
        // only reachable via POST -- it has no GET route, since rendering
        // it requires the posted selection tuples. If either validate()
        // call below threw and were left to Laravel's default behavior,
        // the automatic redirect-back would 302 to the Referer (this same
        // POST-only /bulk-edit/target URL), and the browser's follow-up GET
        // would 405 -- the exact PRG defect already fixed once for Template
        // preview earlier in this project. extractSelectedGroups() can only
        // fail this validation via a tampered request (the UI always
        // submits well-formed hidden fields), so on that failure there is
        // no sensible form to redisplay -- fall back to the safe,
        // GET-accessible selection screen instead. validateBulkEditPayload()
        // can fail via ordinary admin typos (e.g. end time before start
        // time), so that case re-renders the actual target form directly,
        // with errors/old input bound by hand since there's no redirect to
        // carry session-flashed input/errors through.
        try {
            $selections = $this->extractSelectedGroups($request);
        } catch (ValidationException $e) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'The submitted selection was invalid. Please start over.');
        }

        if (empty($selections)) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'Please select at least one class/day schedule.');
        }

        try {
            $validated = $this->validateBulkEditPayload($request);
        } catch (ValidationException $e) {
            $request->flash();
            $rows = $this->resolveSelectedBellTimings($selections);

            if ($rows->isEmpty()) {
                return redirect()->route('bell-timing.bulk-edit')
                    ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
            }

            return response()
                ->view('bell-timing.bulk-edit-target', [
                    'selections' => $selections,
                    'periodNames' => $rows->pluck('period_name')->unique()->sort()->values(),
                    'groupsSummary' => $this->summarizeSelectedRows($rows),
                    'errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', $e->validator->errors()),
                ], 422);
        }

        $rows = $this->resolveSelectedBellTimings($selections);

        if ($rows->isEmpty()) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
        }

        [$matched, $missing, $ambiguous] = $this->matchTargetPeriod($rows, $validated['target_period_name']);

        $attributes = $this->buildBulkEditAttributes($validated);

        $dependencies = $this->dependencyChecker->checkEach($matched->pluck('id')->all());

        $preview = $matched->map(function (BellTiming $row) use ($attributes, $dependencies) {
            $dep = $dependencies[$row->id] ?? [];
            $old = [
                'start_time' => $row->start_time->format('H:i'),
                'end_time' => $row->end_time->format('H:i'),
                'period_name' => $row->period_name,
                'custom_label' => $row->custom_label,
                'color_code' => $row->color_code,
            ];

            return [
                'bellTiming' => $row,
                'old' => $old,
                'new' => array_merge($old, $attributes),
                'warning' => $this->dependencyChecker->isBlocked($dep),
                'reason' => $this->dependencyChecker->summarize($dep),
                'known_updated_at' => $row->updated_at->toISOString(),
            ];
        })->values();

        return view('bell-timing.bulk-edit-preview', [
            'selections' => $selections,
            'payload' => $validated,
            'preview' => $preview,
            'missing' => $missing,
            'ambiguous' => $ambiguous,
        ]);
    }

    /**
     * Bulk Edit step 4: re-derive absolutely everything from scratch --
     * independent of whatever bulkEditPreview() showed -- exactly the
     * same "never trust the earlier screen's snapshot" rule destroy()
     * and bulkDeleteConfirm() already follow. A matched row is only
     * updated if its updated_at still matches what preview recorded;
     * anything that changed in between (or vanished, or became
     * ambiguous) is excluded and reported, never overwritten. Dependency
     * warnings never block here -- only staleness, missing/ambiguous
     * matches, validation, and authorization can prevent an update.
     */
    public function bulkEditConfirm(Request $request)
    {
        $this->authorize('bulkManage', BellTiming::class);

        // Confirm's own screen (bulk-edit-preview) is likewise POST-only,
        // so any validation failure left to Laravel's default back()-
        // redirect would 405 the same way -- see the identical guard in
        // bulkEditPreview() above. Reaching any of these catches at all
        // means the hidden fields carried forward from preview were
        // tampered with (the UI never submits invalid values here), so
        // there is no sensible form to redisplay -- send the admin back to
        // a safe, GET-accessible starting point instead.
        try {
            $selections = $this->extractSelectedGroups($request);
        } catch (ValidationException $e) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'The submitted selection was invalid. Please start over.');
        }

        if (empty($selections)) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'Please select at least one class/day schedule.');
        }

        try {
            $validated = $this->validateBulkEditPayload($request);
            $request->validate([
                'known_state' => 'nullable|array',
                'known_state.*' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'The submitted changes were invalid. Please start over.');
        }

        $knownState = $request->input('known_state', []);

        $rows = $this->resolveSelectedBellTimings($selections);

        if ($rows->isEmpty()) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'No matching schedules were found for the selected classes/days. They may have already been changed or removed.');
        }

        [$matched, $missing, $ambiguous] = $this->matchTargetPeriod($rows, $validated['target_period_name']);

        $toUpdateIds = [];
        $changedSincePreview = 0;

        foreach ($matched as $row) {
            $known = $knownState[$row->id] ?? null;
            if ($known === null || $known !== $row->updated_at->toISOString()) {
                $changedSincePreview++;
                continue;
            }
            $toUpdateIds[] = $row->id;
        }

        if (empty($toUpdateIds)) {
            return redirect()->route('bell-timing.bulk-edit')
                ->with('error', 'Nothing was updated -- every matched schedule changed since preview, was not found, or was ambiguous. Please review and try again.');
        }

        $attributes = $this->buildBulkEditAttributes($validated);

        DB::transaction(function () use ($toUpdateIds, $attributes) {
            BellTiming::whereIn('id', $toUpdateIds)->update($attributes);
        });

        $message = 'Updated ' . count($toUpdateIds) . ' Bell Timing(s).';
        if (!empty($missing)) {
            $message .= ' ' . count($missing) . ' schedule(s) did not have that period.';
        }
        if (!empty($ambiguous)) {
            $message .= ' ' . count($ambiguous) . ' schedule(s) had an ambiguous match and were skipped.';
        }
        if ($changedSincePreview > 0) {
            $message .= " {$changedSincePreview} schedule(s) changed since preview and were skipped.";
        }

        return redirect()->route('bell-timing.index')->with('success', $message);
    }

    /**
     * Shared validation for the target-period/new-value payload, used
     * identically by both bulkEditPreview() and bulkEditConfirm() so the
     * two steps can never silently drift apart. Only the five whitelisted
     * fields (start_time+end_time as a pair, period_name, custom_label,
     * color_code) can ever be requested -- class_section, academic_year,
     * semester, order_index, id, and every ownership field are simply
     * never read from the request at all, anywhere in this flow.
     */
    private function validateBulkEditPayload(Request $request): array
    {
        $validated = $request->validate([
            'target_period_name' => 'required|string|max:100',

            'change_time' => 'nullable|in:1',
            'new_start_time' => 'required_if:change_time,1|nullable|date_format:H:i',
            'new_end_time' => 'required_if:change_time,1|nullable|date_format:H:i|after:new_start_time',

            'change_period_name' => 'nullable|in:1',
            'new_period_name' => 'required_if:change_period_name,1|nullable|string|max:100',

            'change_custom_label' => 'nullable|in:1',
            'new_custom_label' => 'nullable|string|max:100',

            'change_color_code' => 'nullable|in:1',
            'new_color_code' => 'required_if:change_color_code,1|nullable|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $hasAnyChange = collect(['change_time', 'change_period_name', 'change_custom_label', 'change_color_code'])
            ->contains(fn ($key) => ($validated[$key] ?? null) === '1');

        if (!$hasAnyChange) {
            throw ValidationException::withMessages([
                'change_time' => 'Select at least one field to change.',
            ]);
        }

        return $validated;
    }

    /**
     * Builds the update() attribute array strictly from the checked
     * change_* flags -- explicit whitelist, nothing else can ever appear
     * here regardless of what else the request might contain.
     */
    private function buildBulkEditAttributes(array $validated): array
    {
        $attributes = [];

        if (($validated['change_time'] ?? null) === '1') {
            $attributes['start_time'] = $validated['new_start_time'];
            $attributes['end_time'] = $validated['new_end_time'];
        }
        if (($validated['change_period_name'] ?? null) === '1') {
            $attributes['period_name'] = $validated['new_period_name'];
        }
        if (($validated['change_custom_label'] ?? null) === '1') {
            $attributes['custom_label'] = $validated['new_custom_label'];
        }
        if (($validated['change_color_code'] ?? null) === '1') {
            $attributes['color_code'] = $validated['new_color_code'];
        }

        return $attributes;
    }

    /**
     * Matches the target period_name against the resolved rows, grouped
     * by (class_section, day_of_week) -- never by position/order_index.
     * A group with no matching period_name is "missing"; a group with
     * more than one row sharing that exact name is "ambiguous" (the
     * schema has no unique constraint preventing duplicate names, so
     * this is checked for rather than assumed away). Only an exact
     * single match is ever eligible for editing -- never guessed.
     *
     * @return array{0: \Illuminate\Support\Collection<int, BellTiming>, 1: array, 2: array}
     */
    private function matchTargetPeriod(\Illuminate\Support\Collection $rows, string $periodName): array
    {
        $matched = collect();
        $missing = [];
        $ambiguous = [];

        $rows->groupBy(fn (BellTiming $r) => implode('|', [$r->class_section ?? '', $r->day_of_week ?? '']))
            ->each(function ($groupRows) use ($periodName, &$matched, &$missing, &$ambiguous) {
                $hits = $groupRows->where('period_name', $periodName)->values();
                $first = $groupRows->first();
                $identity = [
                    'class_section' => $first->class_section,
                    'day_of_week' => $first->day_of_week,
                ];

                if ($hits->count() === 1) {
                    $matched->push($hits->first());
                } elseif ($hits->count() > 1) {
                    $ambiguous[] = $identity + ['count' => $hits->count()];
                } else {
                    $missing[] = $identity;
                }
            });

        return [$matched, $missing, $ambiguous];
    }

    /**
     * Display weekly timetable for a class.
     */
    public function weeklyTimetable(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);
        
        if ($classSection) {
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
        } else {
            $timetable = collect();
        }
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        $schedules = $timetable; // Rename variable to match view expectation
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter(); // Get all academic years for filter
        
        return view('bell-timing.weekly', compact('schedules', 'classSection', 'academicYear', 'classSections', 'academicYears'));
    }

    /**
     * Display today's schedule.
     */
    public function todaysSchedule(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $day = now()->format('l'); // Current day of week
        
        $schedule = BellTiming::getTodaysSchedule($day, $classSection);
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        return view('bell-timing.daily', compact('schedule', 'classSection', 'day', 'classSections'));
    }

    /**
     * Get current period (AJAX endpoint).
     */
    public function currentPeriod()
    {
        $this->authorize('viewAny', BellTiming::class);
        $currentPeriod = BellTiming::getCurrentPeriod();
        
        return response()->json([
            'current_period' => $currentPeriod,
            'current_time' => now()->format('H:i:s'),
            'current_day' => now()->format('l')
        ]);
    }

    /**
     * Bulk create schedule for a week.
     */
    public function bulkCreate(Request $request)
    {
        $this->authorize('create', BellTiming::class);
        if ($request->isMethod('get')) {
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
            $currentYear = date('Y');
            $academicYears = [
                $currentYear . '-' . ($currentYear + 1),
                ($currentYear - 1) . '-' . $currentYear,
                ($currentYear + 1) . '-' . ($currentYear + 2)
            ];
            
            return view('bell-timing.bulk-create', compact('daysOfWeek', 'classSections', 'academicYears'));
        }

        // Validate bulk creation
        $request->validate([
            'days' => 'required|array',
            'days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'class_section' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'periods' => 'required|array',
            'periods.*.period_name' => 'required|string|max:100',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.is_break' => 'boolean',
            'periods.*.order_index' => 'required|integer|min:0'
        ]);

        $createdCount = 0;
        $errors = [];

        foreach ($request->days as $day) {
            foreach ($request->periods as $index => $period) {
                try {
                    // Check for time conflicts. UAT Step 5, Scenario 1: the
                    // previous whereBetween-based check was inclusive on
                    // both boundaries, so a period whose start_time exactly
                    // equalled another period's end_time (a genuine
                    // back-to-back schedule, e.g. 09:00-09:40 followed by
                    // 09:40-10:20) was flagged as a false-positive conflict.
                    // A single strict-inequality overlap test --
                    // existing.start < new.end AND existing.end > new.start
                    // -- is the standard interval-overlap condition: two
                    // intervals that only touch at a boundary never satisfy
                    // both strict inequalities, while any genuine overlap
                    // (partial, identical, or one containing the other)
                    // always does.
                    $conflicts = BellTiming::where('day_of_week', $day)
                                          ->where('class_section', $request->class_section)
                                          ->where('start_time', '<', $period['end_time'])
                                          ->where('end_time', '>', $period['start_time'])
                                          ->where('is_active', true)
                                          ->get();

                    if ($conflicts->count() > 0) {
                        $errors[] = "Conflict on $day for " . $period['period_name'];
                        continue;
                    }

                    BellTiming::create([
                        'day_of_week' => $day,
                        'period_name' => $period['period_name'],
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                        'class_section' => $request->class_section,
                        'is_active' => true,
                        'is_break' => $period['is_break'] ?? false,
                        'order_index' => $period['order_index'],
                        'academic_year' => $request->academic_year,
                        'semester' => $request->semester,
                        'custom_label' => $period['custom_label'] ?? null,
                        'color_code' => $period['color_code'] ?? '#007bff',
                        'created_by' => Auth::id() // Current authenticated user
                    ]);

                    $createdCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error creating period on $day: " . $e->getMessage();
                }
            }
        }

        $message = "Successfully created $createdCount bell timings.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }

        return redirect()->route('bell-timing.index')
                         ->with('success', $message)
                         ->with('errors', $errors);
    }

    /**
     * Display print-friendly timetable for a class.
     */
    public function printTimetable(Request $request)
    {
        $this->authorize('viewAny', BellTiming::class);
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);

        if ($classSection) {
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
        } else {
            $timetable = collect();
        }

        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter();

        return view('bell-timing.print', compact('timetable', 'classSection', 'academicYear', 'classSections', 'academicYears'));
    }
}