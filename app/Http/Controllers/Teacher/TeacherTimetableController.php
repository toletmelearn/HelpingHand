<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Services\TimetablePdfGenerator;
use Illuminate\Support\Facades\Auth;

/**
 * Timetable pilot-completion pass (Phase 2): a proper weekly view,
 * alongside the existing "today" card on the teacher dashboard (untouched
 * -- this is an additional page, not a replacement). Published slots only,
 * scoped exclusively to the AUTHENTICATED teacher (never a client-supplied
 * id), matching the exact security pattern
 * TeacherDashboardController::todaysPeriodsForTeacher() already
 * established -- there is no route parameter here at all, so there is
 * nothing for a teacher to tamper with to see another teacher's timetable.
 */
class TeacherTimetableController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? Teacher::find($teacherLogin->teacher_id) : null;

        if (!$teacher) {
            return view('teacher.timetable.index', [
                'teacher' => null,
                'days' => [],
                'periodsByDay' => collect(),
            ]);
        }

        $slots = TimetableSlot::published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id))
            ->with(['subject', 'schoolClass', 'section', 'teacher', 'coTeacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

        // Active teaching days, in real calendar order -- derived from the
        // actual bell timings this teacher's own slots reference, not a
        // hard-coded Monday-Saturday assumption (a school that only
        // teaches Monday-Friday must never show a blank, misleading
        // Saturday column).
        $days = $slots->pluck('bellTiming.day_of_week')->unique()
            ->sortBy(fn ($day) => $slots->firstWhere('bellTiming.day_of_week', $day)?->bellTiming?->day_order)
            ->values();

        $periodsByDay = $slots
            ->groupBy('bellTiming.day_of_week')
            ->map(fn ($daySlots) => $daySlots->sortBy('bellTiming.order_index')->values());

        // This week's actual calendar dates for each active day, so any
        // substitution recorded for a specific date this week can be
        // overlaid -- mirrors Parent\TimetableController::today()'s own
        // "Arrangement" treatment, extended across the whole week instead
        // of just today.
        $weekStart = now()->startOfWeek();
        $dateByDayOfWeek = collect($days)->mapWithKeys(function ($day) use ($weekStart) {
            $date = $weekStart->copy();
            while ($date->format('l') !== $day) {
                $date->addDay();
            }

            return [$day => $date];
        });

        // A plain date range, not an exact-string whereIn -- substitution_date
        // is stored as a full datetime column ("2026-08-03 00:00:00"), so a
        // strict string match against pure "Y-m-d" values is DB-driver
        // dependent (works on MySQL's loose datetime coercion, silently
        // matches nothing on SQLite's literal string comparison). A
        // whereBetween range is portable across both, and the exact date is
        // still filtered precisely in PHP below via ->toDateString().
        $weekRangeStart = $weekStart->copy()->startOfDay();
        $weekRangeEnd = $weekStart->copy()->addDays(6)->endOfDay();

        // Substitutions touching this teacher's OWN regular slots this
        // week -- either direction (they were absent and covered, or they
        // are the one covering) -- overlaid onto the matching grid cell
        // below.
        $substitutions = TeacherSubstitution::whereIn('bell_timing_id', $slots->pluck('bell_timing_id')->unique()->values())
            ->where(function ($q) use ($teacher) {
                $q->where('absent_teacher_id', $teacher->id)->orWhere('substitute_teacher_id', $teacher->id);
            })
            ->where('status', '!=', 'cancelled')
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->with('substituteTeacher', 'absentTeacher')
            ->get()
            ->groupBy(fn ($s) => $s->substitution_date->toDateString() . '|' . $s->bell_timing_id);

        $periodsByDay = $periodsByDay->map(function ($daySlots, $day) use ($dateByDayOfWeek, $substitutions, $teacher) {
            $date = $dateByDayOfWeek->get($day);

            return $daySlots->map(function (TimetableSlot $slot) use ($date, $substitutions, $teacher) {
                $key = $date ? $date->toDateString() . '|' . $slot->bell_timing_id : null;
                $arrangement = $key ? $substitutions->get($key, collect())->first() : null;

                // "Team teaching with" from the VIEWER's own perspective --
                // if they're the co-teacher on this lesson, show the
                // primary teacher's name (not their own); if they're the
                // primary, show the co-teacher's name as before.
                $withTeacher = $slot->teacher_id === $teacher->id ? $slot->coTeacher : $slot->teacher;

                return (object) [
                    'slot' => $slot,
                    'arrangement' => $arrangement,
                    'with_teacher_name' => $slot->co_teacher_id ? ($withTeacher->name ?? null) : null,
                ];
            });
        });

        // Extra assignments this week where the teacher is covering a
        // DIFFERENT class than any of their own regular slots -- these
        // have no cell of their own in the grid above, so listed
        // separately rather than silently dropped.
        $ownBellTimingIds = $slots->pluck('bell_timing_id')->unique();
        $coveringAssignments = TeacherSubstitution::where('substitute_teacher_id', $teacher->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->whereNotIn('bell_timing_id', $ownBellTimingIds)
            ->with(['absentTeacher', 'bellTiming', 'class', 'section', 'subject'])
            ->get()
            ->sortBy(fn ($s) => [$s->substitution_date->toDateString(), $s->bellTiming?->order_index]);

        return view('teacher.timetable.index', [
            'teacher' => $teacher,
            'days' => $days,
            'periodsByDay' => $periodsByDay,
            'coveringAssignments' => $coveringAssignments,
        ]);
    }

    /**
     * Priority 1.4: same server-resolved own-teacher security as index()
     * above -- no route parameter, nothing for a teacher to tamper with to
     * download another teacher's timetable.
     */
    public function downloadPdf(TimetablePdfGenerator $generator)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? Teacher::find($teacherLogin->teacher_id) : null;

        if (!$teacher) {
            return redirect()->back()->with('error', 'No teacher profile found for this account.');
        }

        $pdf = $generator->generateTeacherTimetablePdf($teacher);

        return $pdf->download('timetable-' . str_replace(' ', '-', strtolower($teacher->name)) . '.pdf');
    }
}
