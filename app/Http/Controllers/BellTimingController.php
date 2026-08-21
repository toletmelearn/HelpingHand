<?php

namespace App\Http\Controllers;

use App\Models\BellTiming;
use App\Models\Student;
use App\Models\User;
use App\Services\Timetable\BellTimingDependencyChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $message = "Deleted {$deletedCount} Bell Timing(s).";
        if ($blockedCount > 0) {
            $message .= " {$blockedCount} were skipped because they are still in use.";
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
        $request->validate([
            'groups' => 'required|array',
            'groups.*.selected' => 'nullable|string',
            'groups.*.class_section' => 'nullable|string|max:50',
            'groups.*.day_of_week' => 'nullable|string|max:20',
            'groups.*.academic_year' => 'nullable|string|max:20',
            'groups.*.semester' => 'nullable|string|max:20',
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