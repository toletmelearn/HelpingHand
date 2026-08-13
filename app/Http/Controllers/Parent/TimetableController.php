<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use Illuminate\Support\Facades\Auth;

/**
 * T5 item 1: "today's periods" for the logged-in parent's child --
 * period time, subject, teacher, with any substitution applied (substitute
 * teacher shown, marked "Arrangement"). Reads PUBLISHED slots only, same as
 * every other reader outside draft review (T4b). Security follows
 * HomeworkController's exact pattern: the parent guard's own linked
 * student, class-matched server-side -- never a client-supplied class id.
 */
class TimetableController extends Controller
{
    public function today()
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        $student = $parent->student;

        if (!$student) {
            return view('parent.timetable.today', [
                'student' => null,
                'periods' => collect(),
                'date' => now(),
            ]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;
        $date = now();

        if (!$classId) {
            return view('parent.timetable.today', [
                'student' => $student,
                'periods' => collect(),
                'date' => $date,
            ]);
        }

        $periods = $this->todaysPeriods($classId, $sectionId, $date);

        return view('parent.timetable.today', compact('student', 'periods', 'date'));
    }

    /**
     * Timetable pilot-completion pass (Phase 3): the weekly companion to
     * today() above, same security pattern exactly -- $parent->student is
     * ParentModel's own session-aware accessor (getStudentAttribute()),
     * which already resolves whichever child switchStudent() last set as
     * session('active_student_id'), pre-validated there against
     * $parent->students() so a parent can never switch to a child that
     * isn't theirs. There is no route parameter here at all, so nothing to
     * tamper with -- switching child happens through the existing
     * dashboard switcher, unchanged.
     */
    public function weekly()
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent) {
            abort(403, 'Parent not logged in');
        }

        $student = $parent->student;

        if (!$student) {
            return view('parent.timetable.weekly', ['student' => null, 'days' => [], 'periodsByDay' => collect()]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;

        if (!$classId) {
            return view('parent.timetable.weekly', ['student' => $student, 'days' => [], 'periodsByDay' => collect()]);
        }

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

        // Active teaching days, in real calendar order -- derived from this
        // class's own actual bell timings, not a hard-coded Mon-Sat.
        $days = $slots->pluck('bellTiming.day_of_week')->unique()
            ->sortBy(fn ($day) => $slots->firstWhere('bellTiming.day_of_week', $day)?->bellTiming?->day_order)
            ->values();

        $weekStart = now()->startOfWeek();
        $dateByDayOfWeek = collect($days)->mapWithKeys(function ($day) use ($weekStart) {
            $date = $weekStart->copy();
            while ($date->format('l') !== $day) {
                $date->addDay();
            }

            return [$day => $date];
        });

        $weekRangeStart = $weekStart->copy()->startOfDay();
        $weekRangeEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereBetween('substitution_date', [$weekRangeStart, $weekRangeEnd])
            ->where('status', '!=', 'cancelled')
            ->with('substituteTeacher')
            ->get()
            ->groupBy(fn ($s) => $s->substitution_date->toDateString() . '|' . $s->bell_timing_id);

        $periodsByDay = $slots
            ->groupBy('bellTiming.day_of_week')
            ->map(function ($daySlots, $day) use ($dateByDayOfWeek, $substitutions) {
                $date = $dateByDayOfWeek->get($day);

                return $daySlots->sortBy('bellTiming.order_index')->values()->map(function (TimetableSlot $slot) use ($date, $substitutions) {
                    $key = $date ? $date->toDateString() . '|' . $slot->bell_timing_id : null;
                    $sub = $key ? $substitutions->get($key, collect())->first() : null;
                    $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

                    return (object) [
                        'bell_timing_id' => $slot->bell_timing_id,
                        'period_name' => $slot->bellTiming->period_name,
                        'start_time' => $slot->bellTiming->start_time,
                        'end_time' => $slot->bellTiming->end_time,
                        'subject_name' => $slot->subject->name ?? 'N/A',
                        'teacher_name' => $isArrangement ? ($sub->substituteTeacher->name ?? 'N/A') : ($slot->teacher->name ?? 'N/A'),
                        'room_number' => $slot->room_number,
                        'is_arrangement' => $isArrangement,
                    ];
                });
            });

        return view('parent.timetable.weekly', compact('student', 'days', 'periodsByDay'));
    }

    private function todaysPeriods(int $classId, ?int $sectionId, \Carbon\Carbon $date)
    {
        $dayOfWeek = $date->format('l');

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereDate('substitution_date', $date)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('bell_timing_id');

        return $slots->map(function (TimetableSlot $slot) use ($substitutions) {
            $sub = $substitutions->get($slot->bell_timing_id);
            $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

            return (object) [
                'bell_timing_id' => $slot->bell_timing_id,
                'period_name' => $slot->bellTiming->period_name,
                'start_time' => $slot->bellTiming->start_time,
                'end_time' => $slot->bellTiming->end_time,
                'subject_name' => $slot->subject->name ?? 'N/A',
                'teacher_name' => $isArrangement ? ($sub->substituteTeacher->name ?? 'N/A') : ($slot->teacher->name ?? 'N/A'),
                'is_arrangement' => $isArrangement,
            ];
        })->values();
    }
}
