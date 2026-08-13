<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableConflictResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (Conflict Resolver): TimetableConflictResolver is the one
 * authoritative place every interactive caller (manual editing today;
 * drag/drop, Auto-Fix, Rebalance, API tomorrow) evaluates a proposed
 * placement against. TimetableController::checkSlotConflicts() is now a
 * thin adapter to this class (covered end-to-end by
 * TimetableSchedulerTest/TimetableCombinedSlotTest already) -- these
 * tests exercise the resolver directly, including the constraints the
 * manual editor never checked before this phase (period type, teacher
 * availability, daily/weekly load caps, subject-per-day cap).
 */
class TimetableConflictResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Class R', 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    private function makeTiming(array $overrides = []): BellTiming
    {
        return BellTiming::create(array_merge([
            'day_of_week' => 'Monday', 'period_name' => 'P1',
            'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
            'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
        ], $overrides));
    }

    public function test_no_conflicts_for_a_clean_placement(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertFalse($result['conflict']);
        $this->assertSame([], $result['conflicts']);
    }

    public function test_flags_placing_a_lesson_in_a_non_teaching_period(): void
    {
        $class = $this->makeClass();
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing = $this->makeTiming(['period_type' => BellTiming::PERIOD_TYPE_BREAK, 'is_break' => true]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('period_type', $result['type']);
    }

    public function test_flags_a_teacher_marked_unavailable_for_this_exact_period(): void
    {
        $class = $this->makeClass();
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $timing->id, 'is_available' => false]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('availability', $result['type']);
    }

    public function test_does_not_flag_availability_when_teacher_is_marked_available(): void
    {
        $class = $this->makeClass();
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $timing->id, 'is_available' => true]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->assertFalse($result['conflict']);
    }

    public function test_flags_exceeding_the_teachers_daily_period_limit(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active', 'max_periods_per_day' => 1, 'max_periods_per_week' => 36]);
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1]);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('teacher_day_limit', $result['type']);
    }

    public function test_flags_exceeding_the_teachers_weekly_period_limit(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active', 'max_periods_per_day' => 7, 'max_periods_per_week' => 1]);
        $timing1 = $this->makeTiming(['day_of_week' => 'Monday', 'period_name' => 'P1']);
        $timing2 = $this->makeTiming(['day_of_week' => 'Tuesday', 'period_name' => 'P1']);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('teacher_week_limit', $result['type']);
    }

    public function test_resubmitting_the_same_cell_does_not_falsely_trip_the_daily_limit(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active', 'max_periods_per_day' => 1, 'max_periods_per_week' => 36]);
        $timing = $this->makeTiming();

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        // Same cell, same teacher, resubmitted with no ignore_slot_id (the
        // manual editor's own upsert-by-natural-key flow never passes one)
        // -- must not count the cell's own current occupant as an EXTRA
        // period on top of itself.
        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertFalse($result['conflict']);
    }

    public function test_flags_the_same_subject_twice_in_one_day_by_default(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1]);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('subject_per_day', $result['type']);
    }

    public function test_allows_a_second_period_for_a_subject_marked_require_consecutive(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1]);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2]);
        $timing3 = $this->makeTiming(['period_name' => 'P3', 'start_time' => '09:30', 'end_time' => '10:15', 'order_index' => 3]);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => null,
            'subject_id' => $subject->id, 'require_consecutive' => true,
        ]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        // 2nd period: allowed (cap is 2 for a double period).
        $second = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]);
        $this->assertFalse($second['conflict']);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing2->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        // 3rd period: blocked (even a double period caps at 2).
        $third = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing3->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]);
        $this->assertTrue($third['conflict']);
        $this->assertSame('subject_per_day', $third['type']);
    }

    /** The 'conflicts' array carries EVERY violation, not just the first -- needed so a future Auto-Fix/Rebalance can reason about the whole picture, not just whatever happened to be checked first. */
    public function test_conflicts_array_contains_every_violation_not_just_the_first(): void
    {
        $class = $this->makeClass();
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other', 'status' => 'active']);
        $timing = $this->makeTiming();

        // A room clash AND a teacher clash at the same period.
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_number' => 'Hall 1',
        ]);
        $otherClass = $this->makeClass();
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'room_number' => 'Room 9',
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $otherTeacher->id,
            'subject_id' => $subject->id,
            'room_number' => 'Hall 1',
        ]);

        $this->assertTrue($result['conflict']);
        $types = collect($result['conflicts'])->pluck('type');
        $this->assertTrue($types->contains('teacher'));
        $this->assertTrue($types->contains('class'));
        $this->assertTrue($types->contains('room'));
    }

    // --- Room safety pilot-completion pass: isolated room-only scenarios,
    // exercising roomOverlapConflicts() on its own rather than only ever
    // combined with a teacher/class clash as above. ---

    public function test_same_room_at_an_overlapping_period_is_a_room_conflict_even_with_no_other_clash(): void
    {
        $class = $this->makeClass();
        $otherClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'room_number' => 'Lab-3A',
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'room_number' => 'Lab-3A',
        ]);

        $this->assertTrue($result['conflict']);
        $this->assertSame('room', $result['type']);
    }

    public function test_same_room_at_a_different_non_overlapping_period_is_not_a_conflict(): void
    {
        $class = $this->makeClass();
        $otherClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1, 'start_time' => '08:00', 'end_time' => '08:45']);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'order_index' => 2, 'start_time' => '08:45', 'end_time' => '09:30']);

        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'room_number' => 'Lab-3A',
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'room_number' => 'Lab-3A',
        ]);

        $this->assertFalse($result['conflict'], 'The same room at a genuinely different period must never conflict.');
    }

    public function test_different_rooms_at_the_same_period_is_not_a_room_conflict(): void
    {
        $class = $this->makeClass();
        $otherClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'room_number' => 'Lab-3A',
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'room_number' => 'Lab-9B',
        ]);

        $this->assertFalse($result['conflict'], 'Two different rooms at the same period must never conflict with each other.');
    }

    public function test_a_blank_room_never_produces_a_room_conflict(): void
    {
        $class = $this->makeClass();
        $otherClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);
        $timing = $this->makeTiming();

        // An existing slot with NO room set at all.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $otherTeacher->id, 'room_number' => null,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'room_number' => null,
        ]);

        // Genuinely no room type in the conflict list -- a blank room never
        // participates in room-uniqueness at all (existing rooms in other
        // schools' free-text data are frequently left blank, and treating
        // two blank rooms as "the same room" would produce false positives
        // school-wide).
        $types = collect($result['conflicts'])->pluck('type');
        $this->assertFalse($types->contains('room'), 'A blank room must never be treated as a room conflict.');
    }

    /**
     * The exact scenario found live in this project's own dev database:
     * a retired/other-year bell timing (deactivated, e.g. leftover
     * walkthrough test data) sharing the same day+time as a real, active
     * current-year timing must never be treated as the same collision
     * target. An existing slot sitting on the INACTIVE timing must not
     * block a brand new placement on the ACTIVE one, even for the same
     * teacher, same day, same wall-clock time.
     */
    public function test_a_slot_on_a_deactivated_other_year_timing_does_not_block_the_real_active_one(): void
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);

        // Same day, same wall-clock time, but a RETIRED (inactive),
        // other-year timing -- exactly how the walkthrough's leftover
        // bell_timings rows now look after being deactivated.
        $retiredTiming = $this->makeTiming([
            'is_active' => false, 'academic_year' => '2026-2027-WALKTHROUGH-ARCHIVED',
        ]);
        $realTiming = $this->makeTiming(['academic_year' => '2026-2027']);

        TimetableSlot::create([
            'school_class_id' => $this->makeClass()->id, 'bell_timing_id' => $retiredTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $realTiming->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertFalse($result['conflict'], 'A slot on a deactivated, other-year timing must not block the real active one.');
    }

    /** Same scenario, for the subject-per-day cap specifically: a require_consecutive flag on an OTHER-year assignment row must not grant a double-period allowance for the current year. */
    public function test_an_other_year_require_consecutive_assignment_does_not_grant_a_double_period_this_year(): void
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027', 'is_current' => true,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31',
        ]);

        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1, 'academic_year' => '2026-2027']);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2, 'academic_year' => '2026-2027']);

        // This is the ONLY require_consecutive=true row for this
        // class+subject+teacher, but it belongs to a retired year.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => null,
            'subject_id' => $subject->id, 'require_consecutive' => true,
            'academic_year' => '2026-2027-WALKTHROUGH-ARCHIVED',
        ]);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertTrue($result['conflict'], 'Without a CURRENT-year require_consecutive assignment, the cap must default to 1, not 2.');
        $this->assertSame('subject_per_day', $result['type']);
    }

    // --- Hardening pass: academic-year isolation on TimetableSlot-vs-TimetableSlot comparisons ---

    /**
     * The gap the Hardening Triage flagged: baseQuery() (teacher/class/room
     * overlap) never filtered TimetableSlot comparisons by academic_year,
     * only the BellTiming/assignment lookups that feed it. A published
     * slot left over from a prior year -- e.g. a class that wasn't
     * regenerated when the school rolled into a new session -- shared the
     * SAME bell_timing_id (schools reuse bell timing rows year over year)
     * and was wrongly treated as "this teacher is busy then" for a
     * brand-new, current-year placement. This is the false-positive this
     * fix closes: same teacher, same bell_timing_id, different year, must
     * not conflict.
     */
    public function test_a_published_slot_from_a_different_academic_year_does_not_block_a_teacher_placement_this_year(): void
    {
        AcademicSession::create(['name' => 'New Year', 'code' => 'NEW-YEAR', 'is_current' => true, 'start_date' => '2027-04-01', 'end_date' => '2028-03-31']);

        $staleClass = $this->makeClass();
        $newClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        // Tagged to the CURRENT (resolved) year -- BellTiming rows are
        // already year-scoped by pre-existing, unrelated logic, so an
        // untagged timing would be excluded before this fix's own logic is
        // ever reached. Reusing the same bell timing across years (as a
        // real school would) is exactly the scenario this fix targets.
        $timing = $this->makeTiming(['academic_year' => 'NEW-YEAR']);

        // A stale, still-PUBLISHED slot from last year, on the same
        // (reused) bell timing, same teacher.
        TimetableSlot::create([
            'school_class_id' => $staleClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => 'OLD-YEAR', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        // academic_year omitted -> resolves to the current session's code (NEW-YEAR).
        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertFalse($result['conflict'], 'A prior-year published slot must not block a same-teacher placement in the current year.');
    }

    /** Same scenario, explicitly checked WITHIN the stale slot's own year -- the conflict must still be caught. Proves the fix narrows the match, it doesn't remove it. */
    public function test_a_teacher_conflict_within_the_same_academic_year_is_still_detected(): void
    {
        $staleClass = $this->makeClass();
        $newClass = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        // The BellTiming itself must also be tagged to this year -- the
        // resolver's overlappingBellTimingIds() already scopes BellTiming
        // by year (pre-existing behaviour, unrelated to this fix), so a
        // year-tagged check against an untagged timing would find zero
        // overlapping timings regardless of the TimetableSlot-level fix
        // this test is targeting.
        $timing = $this->makeTiming(['academic_year' => 'SAME-YEAR']);

        TimetableSlot::create([
            'school_class_id' => $staleClass->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => 'SAME-YEAR', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timing->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => 'SAME-YEAR',
        ]);

        $this->assertTrue($result['conflict'], 'A same-year teacher overlap must still be detected.');
        $this->assertSame('teacher', $result['type']);
    }

    /** teacherLoadConflicts(): a prior-year published period must not count toward this year's daily limit. */
    public function test_a_different_academic_years_load_does_not_count_toward_this_years_teacher_daily_limit(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active', 'max_periods_per_day' => 1, 'max_periods_per_week' => 36]);
        // Both timings tagged to the CURRENT (resolved) year -- sameDayIds
        // is itself year-scoped (pre-existing, unrelated), so both must
        // share the resolved year for either to be considered "that day"
        // at all; the differentiator this test targets is purely the
        // SLOT's own year.
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1, 'academic_year' => 'NEW-YEAR']);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2, 'academic_year' => 'NEW-YEAR']);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => 'OLD-YEAR', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => 'NEW-YEAR',
        ]);

        $this->assertFalse($result['conflict'], 'A prior-year period must not count toward this year\'s daily limit.');
    }

    /** subjectPerDayConflicts(): a prior-year placement of the same subject/day must not count toward this year's subject-per-day cap. */
    public function test_a_different_academic_years_subject_placement_does_not_count_toward_this_years_subject_per_day_cap(): void
    {
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Maths', 'code' => 'RM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'R Teacher', 'status' => 'active']);
        // Both timings tagged to the CURRENT (resolved) year -- same
        // reasoning as the daily-limit test above.
        $timing1 = $this->makeTiming(['period_name' => 'P1', 'order_index' => 1, 'academic_year' => 'NEW-YEAR']);
        $timing2 = $this->makeTiming(['period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'order_index' => 2, 'academic_year' => 'NEW-YEAR']);

        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => 'OLD-YEAR', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $result = (new TimetableConflictResolver())->check([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing2->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'academic_year' => 'NEW-YEAR',
        ]);

        $this->assertFalse($result['conflict'], 'A prior-year placement of the same subject must not count toward this year\'s subject-per-day cap.');
    }
}
