<?php

namespace App\Services;

use App\Models\Datesheet;
use App\Models\DatesheetEntry;
use App\Models\SchoolClass;
use App\Models\TeacherClassSubjectAssignment;
use App\Services\Exam\ExamTimetableConflictChecker;

/**
 * Mirrors the validation style already established this session for
 * TimetableController/TeacherSubjectAssignmentController's section-
 * ownership checks and TimetableSlot's own conflict rules, applied to
 * Datesheet entries. Returns a plain error string (or null) rather than
 * throwing, matching the existing sectionOwnershipError() convention.
 */
class DatesheetConflictChecker
{
    public function __construct(private ExamTimetableConflictChecker $timetableConflicts)
    {
    }
    /**
     * @return string|null error message, or null if the entry is valid
     */
    public function check(Datesheet $datesheet, SchoolClass $schoolClass, ?int $sectionId, int $subjectId, string $examDate, string $startTime, string $endTime, ?int $ignoreEntryId = null, ?string $room = null): ?string
    {
        // Section must genuinely belong to the class -- same
        // SchoolClass::validSectionIds() bridge already relied on by
        // Timetable/TeacherSubjectAssignment/TeacherSubstitution/
        // CombinedClassGroup this session.
        if ($sectionId !== null && ! in_array($sectionId, $schoolClass->validSectionIds(), true)) {
            return 'That section does not belong to "' . $schoolClass->name . '" -- choose a section that actually belongs to this class.';
        }

        if (strtotime($endTime) <= strtotime($startTime)) {
            return 'End time must be after start time.';
        }

        if ($examDate < $datesheet->start_date->toDateString() || $examDate > $datesheet->end_date->toDateString()) {
            return 'Exam date must fall within the datesheet\'s window (' . $datesheet->start_date->toDateString() . ' to ' . $datesheet->end_date->toDateString() . ').';
        }

        // Duplicate subject for the same class+section within this
        // datesheet (the DB unique constraint also enforces this; this
        // check exists to return a clean message instead of a raw SQL
        // error, and to run before any write is attempted).
        $duplicate = DatesheetEntry::where('datesheet_id', $datesheet->id)
            ->where('school_class_id', $schoolClass->id)
            ->where('section_id', $sectionId)
            ->where('subject_id', $subjectId)
            ->when($ignoreEntryId, fn ($q) => $q->where('id', '!=', $ignoreEntryId))
            ->exists();
        if ($duplicate) {
            return 'This subject is already scheduled for this class/section in this datesheet.';
        }

        // Same class+section cannot have two papers overlapping in time on
        // the same date, whether within this datesheet or any other
        // datesheet for the same academic session (a student can't sit two
        // exams at once regardless of which datesheet scheduled them).
        $overlap = DatesheetEntry::where('school_class_id', $schoolClass->id)
            ->where('section_id', $sectionId)
            // whereDate(), not where(): DatesheetEntry.exam_date is an
            // Eloquent 'date' cast, which serializes to a full
            // 'Y-m-d H:i:s' string on write -- a plain where() comparing
            // against a bare 'Y-m-d' input string silently never matches.
            ->whereDate('exam_date', $examDate)
            ->whereHas('datesheet', fn ($q) => $q->where('academic_session_id', $datesheet->academic_session_id))
            ->when($ignoreEntryId, fn ($q) => $q->where('id', '!=', $ignoreEntryId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
        if ($overlap) {
            return 'This class/section already has an exam scheduled that overlaps this time on ' . $examDate . '.';
        }

        // Sync-audit loophole L-07: room was a stored field on every
        // DatesheetEntry but never checked against anything -- two classes
        // could be booked into the same physical room at an overlapping
        // time. Scoped across ALL datesheets (not just this academic
        // session): a room clash is a facilities conflict, not a
        // curriculum one, so it's checked regardless of which session
        // scheduled the other entry.
        if ($room !== null && $room !== '') {
            $roomConflict = DatesheetEntry::where('room', $room)
                ->whereDate('exam_date', $examDate)
                ->when($ignoreEntryId, fn ($q) => $q->where('id', '!=', $ignoreEntryId))
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime)
                ->exists();
            if ($roomConflict) {
                return "Room \"{$room}\" is already booked for another exam that overlaps this time on {$examDate}.";
            }
        }

        // Informational, not a hard block: no TeacherClassSubjectAssignment
        // exists for this class+subject, so nobody is currently set up to
        // teach/examine it. Schools sometimes schedule before finishing
        // assignments, so this is surfaced by the caller as a warning, not
        // rejected here.

        return null;
    }

    /**
     * Sync-audit loophole L-08: nothing ever compared an exam's date/time
     * against the live teaching timetable -- despite this class's own
     * docblock claiming to mirror "TimetableSlot's own conflict rules," no
     * query here ever touched timetable_slots. Deliberately advisory, not
     * a hard block: TimetableSlot is a recurring WEEKLY template
     * (day_of_week + time-of-day, not a specific calendar date), and
     * schools don't archive/regenerate it for exam weeks -- a hard block
     * here would treat every normal class period as an unresolvable
     * conflict and make it impossible to schedule an exam during the
     * school day at all. Surfaced to the admin as a heads-up instead,
     * matching subjectHasNoAssignedTeacher()'s existing precedent below.
     */
    public function teachingPeriodWarning(SchoolClass $schoolClass, ?int $sectionId, string $examDate, string $startTime, string $endTime): ?string
    {
        $conflict = $this->timetableConflicts->classTeachingConflict($schoolClass->id, $sectionId, $examDate, $startTime, $endTime);

        if (!$conflict) {
            return null;
        }

        $scope = $conflict['section_name'] ? "section {$conflict['section_name']} of " : '';

        return "Note: {$scope}\"{$schoolClass->name}\" has a regular lesson scheduled during this time per the timetable -- confirm classes are actually suspended for this exam.";
    }

    public function subjectHasNoAssignedTeacher(SchoolClass $schoolClass, ?int $sectionId, int $subjectId): bool
    {
        return ! TeacherClassSubjectAssignment::where('class_id', $schoolClass->id)
            ->where('subject_id', $subjectId)
            ->where(function ($q) use ($sectionId) {
                $q->whereNull('section_id');
                if ($sectionId) {
                    $q->orWhere('section_id', $sectionId);
                }
            })
            ->exists();
    }
}
