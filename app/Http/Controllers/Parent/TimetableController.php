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
