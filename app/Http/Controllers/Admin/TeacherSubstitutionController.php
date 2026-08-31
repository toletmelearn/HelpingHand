<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BellTiming;
use App\Models\TeacherSubstitution;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAvailability;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\Timetable\SubstituteFinderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherSubstitutionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', TeacherSubstitution::class);

        $query = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming']);

        // Filter by date
        if ($request->filled('date')) {
            $query->forDate($request->date);
        } else {
            $query->forDate(now()->format('Y-m-d')); // Default to today
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by teacher
        if ($request->filled('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        $substitutions = $query->join('bell_timings', 'teacher_substitutions.bell_timing_id', '=', 'bell_timings.id')
            ->orderBy('bell_timings.order_index')
            ->select('teacher_substitutions.*')
            ->paginate(20);

        // Get filters for the view
        $classes = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::with('user')->orderBy('id')->get();
        $statuses = ['pending' => 'Pending', 'assigned' => 'Assigned', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];

        return view('admin.teacher-substitutions.index', compact('substitutions', 'classes', 'teachers', 'statuses'));
    }

    public function create()
    {
        $this->authorize('create', TeacherSubstitution::class);

        $teachers = Teacher::with('user')->orderBy('id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $bellTimings = BellTiming::teachingType()->where('is_active', true)->orderBy('order_index')->get();

        return view('admin.teacher-substitutions.create', compact('teachers', 'classes', 'sections', 'subjects', 'bellTimings'));
    }

    /**
     * UAT Test 21 defect fix: the Period/Bell Timing selector on the
     * create/edit forms previously listed every active teaching period
     * from every class, with no way to tell them apart -- the same
     * defect already fixed for the Timetable grid. Called via JS when
     * the Class select changes, so the dropdown only ever OFFERS periods
     * that actually belong to the selected class (or the "All Classes"
     * null rows) -- a UI convenience only; store()/update()/
     * assignFromSlot() independently re-validate ownership server-side
     * regardless of what this returns.
     */
    public function bellTimingsForClass(Request $request)
    {
        $this->authorize('create', TeacherSubstitution::class);

        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
        ]);

        $schoolClass = SchoolClass::findOrFail($validated['class_id']);

        $bellTimings = BellTiming::teachingType()->where('is_active', true)
            ->where(function ($q) use ($schoolClass) {
                $q->whereNull('class_section')->orWhere('class_section', $schoolClass->name);
            })
            ->orderBy('order_index')
            ->get();

        return response()->json($bellTimings->map(fn (BellTiming $t) => [
            'id' => $t->id,
            'label' => $t->day_of_week . ' - ' . $t->period_name . ' (' . $t->start_time->format('H:i') . '-' . $t->end_time->format('H:i') . ')',
        ]));
    }

    /**
     * UAT Test 21 defect fix (Section architecture): the Section
     * dropdown previously listed every Section row globally, including
     * accidental orphan duplicates sharing a name with a real one (e.g.
     * two rows both named "A") -- called via JS when the Class select
     * changes, so the dropdown only ever OFFERS the class's real
     * sections, resolved via the canonical legacy_class_map ->
     * class_sections bridge (SchoolClass::validSectionIds()). A UI
     * convenience only; store()/update()/assignFromSlot() independently
     * re-validate ownership server-side regardless of what this returns.
     */
    public function sectionsForClass(Request $request)
    {
        $this->authorize('create', TeacherSubstitution::class);

        $validated = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
        ]);

        $schoolClass = SchoolClass::findOrFail($validated['class_id']);
        $sections = Section::whereIn('id', $schoolClass->validSectionIds())->orderBy('name')->get();

        return response()->json($sections->map(fn (Section $s) => [
            'id' => $s->id,
            'label' => $s->name,
        ]));
    }

    /**
     * UAT Test 21 defect fix: SubstituteFinderService (used by
     * suggestSubstitutes()'s automatic suggestion after store()) already
     * excludes teachers with a TeacherAvailability(is_available=false) row
     * for the relevant bell timing -- but update(), assignSubstitute(),
     * and assignFromSlot() all accept a client-supplied
     * substitute_teacher_id directly, with no equivalent check, so a
     * manually-chosen substitute could bypass availability entirely.
     * Reuses the exact same TeacherAvailability semantics and message
     * wording TimetableConflictResolver::teacherAvailabilityConflicts()
     * already uses for the Timetable grid, rather than inventing a second
     * availability rule.
     */
    private function substituteAvailabilityError(int $substituteTeacherId, int $bellTimingId): ?string
    {
        $blocked = TeacherAvailability::where('teacher_id', $substituteTeacherId)
            ->where('bell_timing_id', $bellTimingId)
            ->where('is_available', false)
            ->exists();

        if ($blocked) {
            $teacherName = Teacher::find($substituteTeacherId)->name ?? 'This teacher';

            return "{$teacherName} has been marked unavailable for this period.";
        }

        // Sync-audit loophole L-15: an explicit unavailability flag was the
        // ONLY thing checked here -- nothing derived availability from the
        // substitute's actual live TimetableSlot load, so a substitute
        // could be handed two classes at the same period. Same bell_timing
        // both tables key on, so this is a direct match, not an overlap
        // resolution.
        $busySlot = TimetableSlot::where('bell_timing_id', $bellTimingId)
            ->where('status', TimetableSlot::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->where('teacher_id', $substituteTeacherId)->orWhere('co_teacher_id', $substituteTeacherId))
            ->with('schoolClass')
            ->first();

        if ($busySlot) {
            $teacherName = Teacher::find($substituteTeacherId)->name ?? 'This teacher';

            return "{$teacherName} is already scheduled to teach " . ($busySlot->schoolClass->name ?? 'another class') . ' during this period.';
        }

        return null;
    }

    /**
     * UAT Test 21 defect fix: BellTiming.class_section has no FK to
     * SchoolClass (same root cause fixed for the Timetable grid in
     * store()/update() there) -- `exists:bell_timings,id` alone never
     * proved the chosen period actually belonged to the class this
     * substitution is FOR, so a substitution could silently anchor
     * itself to a different class's period. A null class_section is
     * BellTiming's own "All Classes" semantics and is valid for every
     * class. Section-level scoping is deliberately not attempted here:
     * BellTiming has no section-level granularity at all in the current
     * schema (confirmed by inspection), so there is nothing to check.
     */
    private function bellTimingOwnershipError(BellTiming $bellTiming, SchoolClass $schoolClass): ?string
    {
        if ($bellTiming->class_section !== null && $bellTiming->class_section !== $schoolClass->name) {
            return "This period belongs to \"{$bellTiming->class_section}\", not \"{$schoolClass->name}\" -- choose a period that belongs to the selected class.";
        }

        return null;
    }

    /**
     * UAT Test 21 defect fix (Section architecture): validates the
     * submitted section_id is actually one of the selected class's real
     * sections per SchoolClass::validSectionIds() (the legacy_class_map
     * -> class_sections bridge) -- the same "never trust only the
     * dropdown" principle as bellTimingOwnershipError(). Confirmed live:
     * without this, a substitution could silently save against an
     * orphan/duplicate Section row (e.g. one of several rows named "A")
     * with no real relationship to the class at all.
     */
    private function sectionOwnershipError(SchoolClass $schoolClass, int $sectionId): ?string
    {
        if (! in_array($sectionId, $schoolClass->validSectionIds(), true)) {
            $section = Section::find($sectionId);
            $sectionName = $section->name ?? 'This section';

            return "\"{$sectionName}\" is not a section of \"{$schoolClass->name}\" -- choose a section that actually belongs to this class.";
        }

        return null;
    }

    /**
     * UAT Test 21 defect fix: store()/update() had no protection at all
     * against recording two active substitutions for the same absent
     * teacher's same real period on the same date -- assignFromSlot()
     * already had this exact check, just never applied to the manual
     * create/edit form. Keyed on bell_timing_id (not section_id, which
     * isn't yet a reliable identifier on its own) -- bell_timing_id
     * already uniquely pins down day+period+class now that ownership is
     * enforced. Cancelled substitutions are excluded, matching
     * assignFromSlot()'s own established rule.
     */
    private function duplicateSubstitutionError(string $date, int $absentTeacherId, int $bellTimingId, ?int $excludeId = null): ?string
    {
        $exists = TeacherSubstitution::where('absent_teacher_id', $absentTeacherId)
            ->where('bell_timing_id', $bellTimingId)
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        return $exists ? 'A substitution for this teacher and period is already recorded.' : null;
    }

    public function store(Request $request)
    {
        $this->authorize('create', TeacherSubstitution::class);

        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $schoolClass = SchoolClass::findOrFail($request->class_id);

        $ownershipError = $this->bellTimingOwnershipError(
            BellTiming::findOrFail($request->bell_timing_id),
            $schoolClass
        );
        if ($ownershipError) {
            return back()->withInput()->with('error', $ownershipError);
        }

        $sectionError = $this->sectionOwnershipError($schoolClass, (int) $request->section_id);
        if ($sectionError) {
            return back()->withInput()->with('error', $sectionError);
        }

        $duplicateError = $this->duplicateSubstitutionError($request->substitution_date, (int) $request->absent_teacher_id, (int) $request->bell_timing_id);
        if ($duplicateError) {
            return back()->withInput()->with('error', $duplicateError);
        }

        $substitution = TeacherSubstitution::create([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'bell_timing_id' => $request->bell_timing_id,
            'reason' => $request->reason,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        // Automatically suggest substitutes
        $this->suggestSubstitutes($substitution);

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution record created successfully.');
    }

    public function show(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('view', $teacherSubstitution);

        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'createdBy', 'updatedBy']);

        return view('admin.teacher-substitutions.show', compact('teacherSubstitution'));
    }

    public function edit(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('update', $teacherSubstitution);

        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming']);

        $teachers = Teacher::with('user')->orderBy('id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $bellTimings = BellTiming::teachingType()->where('is_active', true)->orderBy('order_index')->get();

        return view('admin.teacher-substitutions.edit', compact(
            'teacherSubstitution',
            'teachers',
            'classes',
            'sections',
            'subjects',
            'bellTimings'
        ));
    }

    public function update(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('update', $teacherSubstitution);

        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'status' => 'required|in:pending,assigned,approved,cancelled',
            'substitute_teacher_id' => 'nullable|exists:teachers,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $schoolClass = SchoolClass::findOrFail($request->class_id);

        $ownershipError = $this->bellTimingOwnershipError(
            BellTiming::findOrFail($request->bell_timing_id),
            $schoolClass
        );
        if ($ownershipError) {
            return back()->withInput()->with('error', $ownershipError);
        }

        $sectionError = $this->sectionOwnershipError($schoolClass, (int) $request->section_id);
        if ($sectionError) {
            return back()->withInput()->with('error', $sectionError);
        }

        $duplicateError = $this->duplicateSubstitutionError(
            $request->substitution_date,
            (int) $request->absent_teacher_id,
            (int) $request->bell_timing_id,
            $teacherSubstitution->id
        );
        if ($duplicateError) {
            return back()->withInput()->with('error', $duplicateError);
        }

        if ($request->filled('substitute_teacher_id')) {
            $availabilityError = $this->substituteAvailabilityError(
                (int) $request->substitute_teacher_id,
                (int) $request->bell_timing_id
            );
            if ($availabilityError) {
                return back()->withInput()->with('error', $availabilityError);
            }
        }

        $teacherSubstitution->update([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'bell_timing_id' => $request->bell_timing_id,
            'status' => $request->status,
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'reason' => $request->reason,
            'updated_by' => Auth::id(),
        ]);

        if ($request->status === 'assigned' || $request->status === 'approved') {
            $teacherSubstitution->assigned_at = now();
            $teacherSubstitution->save();
        }

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution updated successfully.');
    }

    public function destroy(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('delete', $teacherSubstitution);

        $teacherSubstitution->delete();

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution deleted successfully.');
    }

    /**
     * T3 item 2: real scoring via SubstituteFinderService, replacing the
     * former stub implementation (calculateSubjectMatchScore always
     * returned 0, hasClassExperience always returned false).
     */
    public function suggestSubstitutes(TeacherSubstitution $substitution)
    {
        $substitution->loadMissing(['bellTiming', 'class', 'subject']);
        $candidates = (new SubstituteFinderService())->findCandidatesForSubstitution($substitution);

        // Auto-suggest the top-ranked candidate; stays pending for admin review.
        if (!empty($candidates)) {
            $substitution->update([
                'substitute_teacher_id' => $candidates[0]['teacher']->id,
                'status' => 'pending',
            ]);
        }

        return $candidates;
    }

    public function assignSubstitute(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('assignSubstitute', $teacherSubstitution);

        $request->validate([
            'substitute_teacher_id' => 'required|exists:teachers,id'
        ]);

        $availabilityError = $this->substituteAvailabilityError(
            (int) $request->substitute_teacher_id,
            (int) $teacherSubstitution->bell_timing_id
        );
        if ($availabilityError) {
            return back()->with('error', $availabilityError);
        }

        $teacherSubstitution->update([
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute teacher assigned successfully.');
    }

    public function approveSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('approveSubstitute', $teacherSubstitution);

        $teacherSubstitution->update([
            'status' => 'approved',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment approved successfully.');
    }

    public function cancelSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $this->authorize('cancelSubstitute', $teacherSubstitution);

        $teacherSubstitution->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment cancelled successfully.');
    }

    public function today()
    {
        $this->authorize('viewTodaySubstitutions', TeacherSubstitution::class);

        $substitutions = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming'])
            ->forDate(now())
            ->get()
            ->sortBy(fn (TeacherSubstitution $s) => $s->bellTiming?->order_index ?? PHP_INT_MAX)
            ->values();

        return view('admin.teacher-substitutions.today', compact('substitutions'));
    }

    public function absenceOverview()
    {
        $this->authorize('viewAbsenceOverview', TeacherSubstitution::class);

        $absentTeachers = Teacher::whereHas('absentSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('absent_teacher_id');
        })
        ->with(['absentSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        $substitutedTeachers = Teacher::whereHas('substituteSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('substitute_teacher_id');
        })
        ->with(['substituteSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        return view('admin.teacher-substitutions.absence-overview', compact(
            'absentTeachers', 
            'substitutedTeachers'
        ));
    }

    public function substitutionRules()
    {
        $this->authorize('manageRules', TeacherSubstitution::class);

        // Return view for managing substitution rules
        return view('admin.teacher-substitutions.rules');
    }

    /**
     * T3 item 3: "Teacher absent today" flow -- pick a teacher + date,
     * see their day's timetable slots, and a ranked list of substitute
     * suggestions per slot that doesn't already have one recorded.
     */
    public function absentToday(Request $request)
    {
        $this->authorize('manageAbsentToday', TeacherSubstitution::class);

        $teachers = Teacher::active()->orderBy('name')->get();
        $selectedTeacherId = $request->integer('teacher_id') ?: null;
        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();

        // T3 item 5: HR leave integration -- read-only. Teachers with an
        // approved TeacherLeave covering the selected date are surfaced
        // as one-click shortcuts into this same flow, so admin doesn't
        // have to separately know who's on leave before setting up
        // substitutions for them. Nothing is written to teacher_leaves.
        $teachersOnApprovedLeave = Teacher::whereHas('leaves', function ($q) use ($date) {
            $q->where('status', 'approved')
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date);
        })->orderBy('name')->get();

        $selectedTeacher = null;
        $rows = [];

        if ($selectedTeacherId) {
            $selectedTeacher = Teacher::findOrFail($selectedTeacherId);
            $dayOfWeek = $date->format('l');

            // T4b: only the live timetable counts as "this teacher's actual
            // slot today" -- a draft proposal isn't a real commitment yet.
            $timetableSlots = TimetableSlot::with(['bellTiming', 'schoolClass', 'section', 'subject'])
                ->published()
                ->where('teacher_id', $selectedTeacherId)
                ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
                ->get()
                ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
                ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
                ->values();

            $service = new SubstituteFinderService();

            foreach ($timetableSlots as $slot) {
                $existing = TeacherSubstitution::with('substituteTeacher')
                    ->where('absent_teacher_id', $selectedTeacherId)
                    ->where('bell_timing_id', $slot->bell_timing_id)
                    ->whereDate('substitution_date', $date)
                    ->where('status', '!=', 'cancelled')
                    ->first();

                $candidates = $existing || !$slot->schoolClass || !$slot->subject
                    ? []
                    : $service->findCandidates($slot->bellTiming, $date, $slot->schoolClass, $slot->subject, $selectedTeacherId);

                $rows[] = [
                    'slot' => $slot,
                    'existing' => $existing,
                    'candidates' => array_slice($candidates, 0, 5),
                ];
            }
        }

        return view('admin.teacher-substitutions.absent-today', compact('teachers', 'selectedTeacher', 'date', 'rows', 'teachersOnApprovedLeave'));
    }

    /**
     * T3 item 3: one-click assign from the absent-today flow -- records
     * the substitution and assigns the chosen candidate in one step
     * (skips the separate "create then suggest then assign" path the
     * manual form uses).
     */
    public function assignFromSlot(Request $request)
    {
        $this->authorize('manageAbsentToday', TeacherSubstitution::class);

        $validated = $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'substitute_teacher_id' => 'required|exists:teachers,id',
        ]);

        $alreadyRecorded = TeacherSubstitution::where('absent_teacher_id', $validated['absent_teacher_id'])
            ->where('bell_timing_id', $validated['bell_timing_id'])
            ->whereDate('substitution_date', $validated['substitution_date'])
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($alreadyRecorded) {
            return redirect()->route('admin.teacher-substitutions.absent-today', [
                'teacher_id' => $validated['absent_teacher_id'],
                'date' => $validated['substitution_date'],
            ])->with('error', 'A substitution for this teacher and period is already recorded.');
        }

        $schoolClass = SchoolClass::findOrFail($validated['class_id']);

        $ownershipError = $this->bellTimingOwnershipError(
            BellTiming::findOrFail($validated['bell_timing_id']),
            $schoolClass
        );
        if ($ownershipError) {
            return redirect()->route('admin.teacher-substitutions.absent-today', [
                'teacher_id' => $validated['absent_teacher_id'],
                'date' => $validated['substitution_date'],
            ])->with('error', $ownershipError);
        }

        if (! empty($validated['section_id'])) {
            $sectionError = $this->sectionOwnershipError($schoolClass, (int) $validated['section_id']);
            if ($sectionError) {
                return redirect()->route('admin.teacher-substitutions.absent-today', [
                    'teacher_id' => $validated['absent_teacher_id'],
                    'date' => $validated['substitution_date'],
                ])->with('error', $sectionError);
            }
        }

        $availabilityError = $this->substituteAvailabilityError(
            (int) $validated['substitute_teacher_id'],
            (int) $validated['bell_timing_id']
        );
        if ($availabilityError) {
            return redirect()->route('admin.teacher-substitutions.absent-today', [
                'teacher_id' => $validated['absent_teacher_id'],
                'date' => $validated['substitution_date'],
            ])->with('error', $availabilityError);
        }

        TeacherSubstitution::create([
            'substitution_date' => $validated['substitution_date'],
            'absent_teacher_id' => $validated['absent_teacher_id'],
            'substitute_teacher_id' => $validated['substitute_teacher_id'],
            'class_id' => $validated['class_id'],
            'section_id' => $validated['section_id'] ?? null,
            'subject_id' => $validated['subject_id'],
            'bell_timing_id' => $validated['bell_timing_id'],
            'status' => 'assigned',
            'assigned_at' => now(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.teacher-substitutions.absent-today', [
            'teacher_id' => $validated['absent_teacher_id'],
            'date' => $validated['substitution_date'],
        ])->with('success', 'Substitute assigned.');
    }

    /**
     * T3 item 4: the daily "arrangement sheet" -- a period x class grid
     * of ONLY the substitution changes for one day (not the whole
     * timetable), the sheet on the principal's desk at 8am. Periods are
     * that date's day-of-week active bell timings; classes are only the
     * ones with an actual change that day (an unaffected class doesn't
     * need a row).
     */
    public function arrangementSheetPdf(Request $request)
    {
        $this->authorize('viewAny', TeacherSubstitution::class);

        $date = $request->filled('date') ? Carbon::parse($request->date) : Carbon::today();
        $dayOfWeek = $date->format('l');

        $substitutions = TeacherSubstitution::with(['bellTiming', 'class', 'section', 'subject', 'absentTeacher', 'substituteTeacher'])
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($substitutions->isEmpty()) {
            return back()->with('error', "No substitutions recorded for {$date->format('d M Y')} -- nothing to print yet.");
        }

        $periods = BellTiming::where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->teachingType()
            ->orderBy('order_index')
            ->pluck('period_name')
            ->unique()
            ->values()
            ->all();

        $classes = $substitutions->pluck('class')->filter()->unique('id')->sortBy('class_order')->values();

        // [class_id][period_name] => substitution
        $grid = [];
        foreach ($substitutions as $substitution) {
            if (!$substitution->bellTiming) {
                continue;
            }
            $grid[$substitution->class_id][$substitution->bellTiming->period_name] = $substitution;
        }

        $pdf = Pdf::loadView('admin.teacher-substitutions.pdf.arrangement-sheet', [
            'date' => $date,
            'periods' => $periods,
            'classes' => $classes,
            'grid' => $grid,
        ]);
        $pdf->setPaper('A4', 'landscape');

        $safeDate = $date->format('Y-m-d');

        return $pdf->download("arrangement_sheet_{$safeDate}.pdf");
    }
}