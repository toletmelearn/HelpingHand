<?php

namespace App\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use Carbon\CarbonInterface;

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
     * Convenience wrapper for an already-persisted TeacherSubstitution
     * (excludes it from the "already substituting elsewhere" check via
     * its own id). Used by the manual create/edit flow.
     */
    public function findCandidatesForSubstitution(TeacherSubstitution $substitution): array
    {
        if (!$substitution->bellTiming || !$substitution->class || !$substitution->subject) {
            return [];
        }

        return $this->findCandidates(
            $substitution->bellTiming,
            $substitution->substitution_date,
            $substitution->class,
            $substitution->subject,
            $substitution->absent_teacher_id,
            $substitution->id
        );
    }

    /**
     * @return array<int, array{teacher: Teacher, score: int, reasons: array<string>, reason_text: string}>
     *         Ranked highest score first.
     */
    public function findCandidates(
        BellTiming $bellTiming,
        string|CarbonInterface $date,
        SchoolClass $class,
        Subject $subject,
        int $absentTeacherId,
        ?int $excludeSubstitutionId = null
    ): array {
        $bellTimingId = $bellTiming->id;
        $dayOfWeek = $bellTiming->day_of_week;

        $busyTeacherIds = TimetableSlot::where('bell_timing_id', $bellTimingId)
            ->pluck('teacher_id')->unique();

        $blockedTeacherIds = TeacherAvailability::where('bell_timing_id', $bellTimingId)
            ->where('is_available', false)
            ->pluck('teacher_id')->unique();

        $alreadySubstitutingTeacherIds = TeacherSubstitution::where('bell_timing_id', $bellTimingId)
            ->whereDate('substitution_date', $date)
            ->whereIn('status', ['pending', 'assigned', 'approved'])
            ->when($excludeSubstitutionId, fn ($q) => $q->where('id', '!=', $excludeSubstitutionId))
            ->pluck('substitute_teacher_id')->filter()->unique();

        $classTeacherIds = TeacherClassSubjectAssignment::where('class_id', $class->id)
            ->pluck('teacher_id')->unique();

        $subjectTeacherIds = TeacherClassSubjectAssignment::where('subject_id', $subject->id)
            ->pluck('teacher_id')->unique();

        $periodsTodayByTeacher = TimetableSlot::whereHas(
            'bellTiming',
            fn ($q) => $q->where('day_of_week', $dayOfWeek)
        )->selectRaw('teacher_id, COUNT(*) as c')
            ->groupBy('teacher_id')
            ->pluck('c', 'teacher_id');

        $teachers = Teacher::active()->where('id', '!=', $absentTeacherId)->get();

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
                $reasons[] = "Teaches {$class->name} {$subject->name}";
            } elseif ($teachesClass) {
                $score += 25;
                $reasons[] = "Teaches {$class->name}";
            } elseif ($teachesSubject) {
                $score += 20;
                $reasons[] = "Teaches {$subject->name}";
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
