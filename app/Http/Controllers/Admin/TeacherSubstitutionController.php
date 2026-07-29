<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BellTiming;
use App\Models\TeacherSubstitution;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
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
        $teachers = Teacher::with('user')->orderBy('id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $bellTimings = BellTiming::teachingType()->where('is_active', true)->orderBy('order_index')->get();

        return view('admin.teacher-substitutions.create', compact('teachers', 'classes', 'sections', 'subjects', 'bellTimings'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'reason' => 'nullable|string|max:1000',
        ]);

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
        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'createdBy', 'updatedBy']);
        
        return view('admin.teacher-substitutions.show', compact('teacherSubstitution'));
    }

    public function edit(TeacherSubstitution $teacherSubstitution)
    {
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
        $request->validate([
            'substitute_teacher_id' => 'required|exists:teachers,id'
        ]);

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
        $teacherSubstitution->update([
            'status' => 'approved',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment approved successfully.');
    }

    public function cancelSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment cancelled successfully.');
    }

    public function today()
    {
        $substitutions = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'bellTiming'])
            ->forDate(now())
            ->get()
            ->sortBy(fn (TeacherSubstitution $s) => $s->bellTiming?->order_index ?? PHP_INT_MAX)
            ->values();

        return view('admin.teacher-substitutions.today', compact('substitutions'));
    }

    public function absenceOverview()
    {
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