<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\SchoolHoliday;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Services\TimetablePdfGenerator;
use Illuminate\Support\Facades\Auth;

/**
 * The student-facing counterpart to Parent\TimetableController -- same
 * security pattern (server-resolved own class/section, never a
 * client-supplied id), same PUBLISHED-slots-only read, same substitution
 * overlay. Reuses that controller's exact query/grouping logic rather than
 * reinventing it; the only structural difference is the auth source
 * (plain `web` guard + Auth::user()->student, vs the separate `parent`
 * guard's session-aware student accessor).
 */
class StudentTimetableController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function today()
    {
        $student = Auth::user()->student;

        if (! $student) {
            return view('student.timetable.today', [
                'student' => null,
                'periods' => collect(),
                'date' => now(),
                'isHoliday' => false,
            ]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;
        $date = now();
        $isHoliday = SchoolHoliday::isHolidayOn($date);

        if (! $classId || $isHoliday) {
            return view('student.timetable.today', [
                'student' => $student,
                'periods' => collect(),
                'date' => $date,
                'isHoliday' => $isHoliday,
            ]);
        }

        $periods = $this->todaysPeriods($classId, $sectionId, $date);

        return view('student.timetable.today', compact('student', 'periods', 'date', 'isHoliday'));
    }

    public function weekly()
    {
        $student = Auth::user()->student;

        if (! $student) {
            return view('student.timetable.weekly', ['student' => null, 'days' => [], 'periodsByDay' => collect(), 'holidays' => collect()]);
        }

        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;

        if (! $classId) {
            return view('student.timetable.weekly', ['student' => $student, 'days' => [], 'periodsByDay' => collect(), 'holidays' => collect()]);
        }

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

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
            ->groupBy(fn ($s) => $s->substitution_date->toDateString().'|'.$s->bell_timing_id);

        $holidays = SchoolHoliday::getHolidaysInRange($weekRangeStart, $weekRangeEnd);

        $periodsByDay = $slots
            ->groupBy('bellTiming.day_of_week')
            ->map(function ($daySlots, $day) use ($dateByDayOfWeek, $substitutions) {
                $date = $dateByDayOfWeek->get($day);

                return $daySlots->sortBy('bellTiming.order_index')->values()->map(function (TimetableSlot $slot) use ($date, $substitutions) {
                    $key = $date ? $date->toDateString().'|'.$slot->bell_timing_id : null;
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

        return view('student.timetable.weekly', compact('student', 'days', 'periodsByDay', 'holidays'));
    }

    /**
     * Priority 1.4: same server-resolved own-student security as
     * today()/weekly() above -- no route parameter, nothing for a student
     * to tamper with to download another student's timetable.
     */
    public function downloadPdf(TimetablePdfGenerator $generator)
    {
        $student = Auth::user()->student;

        if (! $student) {
            return redirect()->back()->with('error', 'No student profile found for this account.');
        }

        $pdf = $generator->generateStudentTimetablePdf($student);

        return $pdf->download('timetable-'.str_replace(' ', '-', strtolower($student->name)).'.pdf');
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
