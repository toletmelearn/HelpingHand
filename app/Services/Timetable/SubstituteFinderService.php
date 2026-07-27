<?php

namespace App\Services\Timetable;

use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;

/**
 * T3 item 2: real substitute scoring, replacing TeacherSubstitutionController's
 * two stubbed functions (calculateSubjectMatchScore always returned 0,
 * hasClassExperience always returned false).
 *
 * Score, per the plan:
 *   +40 free that period (from timetable_slots + availability) -- MANDATORY
 *        filter, not just a score bonus: a busy/blocked teacher is never
 *        returned as a candidate at all.
 *   +25 teaches this class already (any subject, from assignments)
 *   +20 teaches this subject (any class)
 *   +15 fewest total periods that day, scaled inversely (linear, capped at
 *        0 for 15+ periods -- no further precision given by the plan)
 *   -100 already substituting that period elsewhere -- also an outright
 *        exclusion ("(exclude)" per the plan), not a raw penalty a huge
 *        score could out-weigh.
 */
class SubstituteFinderService
{
    /**
     * @return array<int, array{teacher: Teacher, score: int, reasons: array<string>, reason_text: string}>
     *         Ranked highest score first.
     */
    public function findCandidates(TeacherSubstitution $substitution): array
    {
        $bellTiming = $substitution->bellTiming;
        if (!$bellTiming) {
            return [];
        }

        $bellTimingId = $bellTiming->id;
        $dayOfWeek = $bellTiming->day_of_week;

        $busyTeacherIds = TimetableSlot::where('bell_timing_id', $bellTimingId)
            ->pluck('teacher_id')->unique();

        $blockedTeacherIds = TeacherAvailability::where('bell_timing_id', $bellTimingId)
            ->where('is_available', false)
            ->pluck('teacher_id')->unique();

        $alreadySubstitutingTeacherIds = TeacherSubstitution::where('bell_timing_id', $bellTimingId)
            ->whereDate('substitution_date', $substitution->substitution_date)
            ->whereIn('status', ['pending', 'assigned', 'approved'])
            ->where('id', '!=', $substitution->id)
            ->pluck('substitute_teacher_id')->filter()->unique();

        $classTeacherIds = TeacherClassSubjectAssignment::where('class_id', $substitution->class_id)
            ->pluck('teacher_id')->unique();

        $subjectTeacherIds = TeacherClassSubjectAssignment::where('subject_id', $substitution->subject_id)
            ->pluck('teacher_id')->unique();

        $periodsTodayByTeacher = TimetableSlot::whereHas(
            'bellTiming',
            fn ($q) => $q->where('day_of_week', $dayOfWeek)
        )->selectRaw('teacher_id, COUNT(*) as c')
            ->groupBy('teacher_id')
            ->pluck('c', 'teacher_id');

        $teachers = Teacher::active()->where('id', '!=', $substitution->absent_teacher_id)->get();

        $candidates = [];

        foreach ($teachers as $teacher) {
            // MANDATORY: free that period.
            if ($busyTeacherIds->contains($teacher->id) || $blockedTeacherIds->contains($teacher->id)) {
                continue;
            }

            // Exclude: already substituting elsewhere this exact period.
            if ($alreadySubstitutingTeacherIds->contains($teacher->id)) {
                continue;
            }

            $score = 40;
            $reasons = ['Free'];

            $teachesClass = $classTeacherIds->contains($teacher->id);
            $teachesSubject = $subjectTeacherIds->contains($teacher->id);

            if ($teachesClass && $teachesSubject) {
                $score += 45;
                $reasons[] = "Teaches {$substitution->class->name} {$substitution->subject->name}";
            } elseif ($teachesClass) {
                $score += 25;
                $reasons[] = "Teaches {$substitution->class->name}";
            } elseif ($teachesSubject) {
                $score += 20;
                $reasons[] = "Teaches {$substitution->subject->name}";
            }

            $periodsToday = (int) ($periodsTodayByTeacher[$teacher->id] ?? 0);
            $score += max(0, 15 - $periodsToday);
            $reasons[] = "{$periodsToday} " . ($periodsToday === 1 ? 'period' : 'periods') . ' today';

            $candidates[] = [
                'teacher' => $teacher,
                'score' => $score,
                'reasons' => $reasons,
                'reason_text' => implode(' • ', $reasons),
            ];
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }
}
