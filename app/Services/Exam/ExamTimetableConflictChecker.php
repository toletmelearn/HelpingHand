<?php

namespace App\Services\Exam;

use App\Models\BellTiming;
use App\Models\ExamSeatingArrangement;
use App\Models\TimetableSlot;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The Exam-side counterpart to Timetable's own
 * App\Services\Timetable\TimetableConflictResolver: answers "does this
 * exam-day activity collide with the live teaching timetable?" for two
 * distinct questions that were previously answered nowhere at all --
 *
 *  - teachingConflictFor(): is a given teacher already scheduled to teach
 *    a class during this exact date/time window? (used for invigilator
 *    and relieving-duty assignment, and teacher-substitute assignment)
 *  - classTeachingConflict(): does this class/section already have a
 *    published lesson scheduled during this exact date/time window? (used
 *    when scheduling an exam itself, e.g. via Datesheet)
 *
 * TimetableSlot rows are keyed by a recurring weekly BellTiming
 * (day_of_week + time-of-day), not an absolute calendar date -- so a
 * concrete exam_date is first resolved to its day-of-week name, then
 * matched against active BellTiming rows whose time range overlaps the
 * exam's start/end time, mirroring
 * TimetableConflictResolver::overlappingBellTimingIds() but starting from
 * an explicit date/time pair instead of an existing BellTiming row.
 */
class ExamTimetableConflictChecker
{
    /**
     * @return array{slot_id: int, class_name: string, bell_timing_id: int}|null
     *   The blocking TimetableSlot's details, or null if the teacher is free.
     */
    public function teachingConflictFor(int $teacherId, \DateTimeInterface|string $date, \DateTimeInterface|string $startTime, \DateTimeInterface|string $endTime): ?array
    {
        $overlappingIds = $this->overlappingBellTimingIds($date, $startTime, $endTime);
        if ($overlappingIds->isEmpty()) {
            return null;
        }

        $slot = TimetableSlot::whereIn('bell_timing_id', $overlappingIds)
            ->where('status', TimetableSlot::STATUS_PUBLISHED)
            ->where(fn ($q) => $q->where('teacher_id', $teacherId)->orWhere('co_teacher_id', $teacherId))
            ->with('schoolClass')
            ->first();

        if (!$slot) {
            return null;
        }

        return [
            'slot_id' => $slot->id,
            'class_name' => $slot->schoolClass->name ?? 'another class',
            'bell_timing_id' => $slot->bell_timing_id,
        ];
    }

    /**
     * @return array{slot_id: int, section_name: ?string}|null
     *   The blocking TimetableSlot's details, or null if the class/section
     *   has no lesson scheduled during this window. A whole-class slot
     *   (section_id null) blocks every section; a section-specific slot
     *   only blocks that section (or a whole-class exam check, when
     *   $sectionId is null, is blocked by any section's lesson too).
     */
    public function classTeachingConflict(int $schoolClassId, ?int $sectionId, \DateTimeInterface|string $date, \DateTimeInterface|string $startTime, \DateTimeInterface|string $endTime): ?array
    {
        $overlappingIds = $this->overlappingBellTimingIds($date, $startTime, $endTime);
        if ($overlappingIds->isEmpty()) {
            return null;
        }

        $slot = TimetableSlot::whereIn('bell_timing_id', $overlappingIds)
            ->where('status', TimetableSlot::STATUS_PUBLISHED)
            ->where('school_class_id', $schoolClassId)
            ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $sectionId))
            ->with('section')
            ->first();

        if (!$slot) {
            return null;
        }

        return [
            'slot_id' => $slot->id,
            'section_name' => $slot->section->name ?? null,
        ];
    }

    /**
     * Priority 1.1 (room hard-block): a physical room can't hold two
     * different exams' seating at once. Room/date/time were freely
     * re-entered on every seating generate/save with nothing checking them
     * against OTHER exams already seated in that same room -- unlike
     * TimetableConflictResolver::roomOverlapConflicts() (weekly timetable
     * grid) and DatesheetConflictChecker::check() (datesheet entries),
     * which already hard-block room clashes in their own domains, this is
     * the exam-seating domain's own room check.
     *
     * @return array{exam_id: int, exam_name: string}|null
     *   The blocking exam's details, or null if the room is free for this
     *   date/time.
     */
    public function roomConflictForExam(string $roomNumber, int $excludeExamId, \DateTimeInterface|string $date, \DateTimeInterface|string $startTime, \DateTimeInterface|string $endTime): ?array
    {
        $dateStr = Carbon::parse($date)->format('Y-m-d');
        $start = Carbon::parse($startTime)->format('H:i:s');
        $end = Carbon::parse($endTime)->format('H:i:s');

        $seating = ExamSeatingArrangement::where('room_number', $roomNumber)
            ->where('exam_id', '!=', $excludeExamId)
            ->whereHas('exam', function ($q) use ($dateStr, $start, $end) {
                $q->whereDate('exam_date', $dateStr)
                    ->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->with('exam')
            ->first();

        if (!$seating) {
            return null;
        }

        return [
            'exam_id' => $seating->exam_id,
            'exam_name' => $seating->exam->name ?? 'another exam',
        ];
    }

    /** @return Collection<int,int> active bell_timing ids on $date's weekday whose time range overlaps [$startTime, $endTime). */
    private function overlappingBellTimingIds(\DateTimeInterface|string $date, \DateTimeInterface|string $startTime, \DateTimeInterface|string $endTime): Collection
    {
        $dayOfWeek = Carbon::parse($date)->format('l');
        $start = Carbon::parse($startTime)->format('H:i:s');
        $end = Carbon::parse($endTime)->format('H:i:s');

        return BellTiming::where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->pluck('id');
    }
}
