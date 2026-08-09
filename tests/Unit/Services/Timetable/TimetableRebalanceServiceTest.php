<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableRebalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Rebalancing Engine: scoring is a deterministic, purely arithmetic
 * function of the timetable snapshot (see TimetableRebalanceService's
 * class docblock and self::WEIGHTS); candidate generation is a bounded
 * greedy hill-climb whose every legality check goes through the SAME
 * TimetableConflictResolver / TimetableSuggestionService::checkSwap()
 * every other interactive write already uses -- these tests prove the
 * scoring math, the search's boundedness, and that it never proposes (or
 * applies) anything a hard constraint would reject.
 */
class TimetableRebalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Subject $morningSubject;
    protected Teacher $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Rebal Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'RB-MATH' . uniqid(), 'prefer_morning' => false]);
        $this->morningSubject = Subject::create(['name' => 'Science', 'code' => 'RB-SCI' . uniqid(), 'prefer_morning' => true]);
        $this->teacher = Teacher::create(['name' => 'Rebal Teacher ' . uniqid(), 'status' => 'active']);
    }

    /**
     * A full teaching week: Monday..Friday, periods 1..$perDay, each
     * BellTiming's own day_of_week/order_index -- the same shape every
     * other timetable test builds. Returns [day][period] => BellTiming.
     */
    private function makeWeek(int $days = 5, int $perDay = 6): array
    {
        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $grid = [];
        $order = 1;

        for ($d = 0; $d < $days; $d++) {
            for ($p = 1; $p <= $perDay; $p++) {
                $grid[$dayNames[$d]][$p] = BellTiming::create([
                    'day_of_week' => $dayNames[$d],
                    'period_name' => "Period {$p}",
                    'start_time' => sprintf('%02d:00:00', 7 + $p),
                    'end_time' => sprintf('%02d:00:00', 8 + $p),
                    'is_active' => true, 'is_break' => false, 'order_index' => $order++,
                    'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
                ]);
            }
        }

        return $grid;
    }

    private function makeSlot(BellTiming $timing, array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
        ], $overrides));
    }

    // ============================================================
    // Scoring components
    // ============================================================

    public function test_baseline_score_detects_a_teacher_gap(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        // Period 2 free -- a gap.
        $this->makeSlot($week['Monday'][3]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['baseline_score']['breakdown']['teacher_gap_periods']);
    }

    public function test_baseline_score_is_zero_for_a_perfectly_packed_single_teacher_day(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Monday'][2]);
        $this->makeSlot($week['Monday'][3]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(0, $result['baseline_score']['breakdown']['teacher_gap_periods']);
        $this->assertSame(0, $result['baseline_score']['breakdown']['class_gap_periods']);
    }

    public function test_class_gap_is_independent_of_teacher_gap(): void
    {
        $week = $this->makeWeek(1, 3);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        // Class has periods 1 and 3 occupied (a class gap at period 2), but by TWO DIFFERENT teachers -- no teacher gap for either.
        $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id]);
        $this->makeSlot($week['Monday'][3], ['teacher_id' => $teacherB->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['baseline_score']['breakdown']['class_gap_periods']);
        $this->assertSame(0, $result['baseline_score']['breakdown']['teacher_gap_periods']);
    }

    public function test_teacher_workload_imbalance_measures_spread_across_days(): void
    {
        $week = $this->makeWeek(2, 6);
        // Monday: 1 period. Tuesday: 4 periods. Spread = 3.
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Tuesday'][1]);
        $this->makeSlot($week['Tuesday'][2]);
        $this->makeSlot($week['Tuesday'][3]);
        $this->makeSlot($week['Tuesday'][4]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(3, $result['baseline_score']['breakdown']['teacher_workload_imbalance']);
    }

    public function test_prefer_morning_subject_placed_in_the_afternoon_is_flagged(): void
    {
        $week = $this->makeWeek(1, 6);
        $this->makeSlot($week['Monday'][6], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['baseline_score']['breakdown']['prefer_morning_violations']);
    }

    public function test_prefer_morning_subject_placed_in_the_morning_is_not_flagged(): void
    {
        $week = $this->makeWeek(1, 6);
        $this->makeSlot($week['Monday'][1], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(0, $result['baseline_score']['breakdown']['prefer_morning_violations']);
    }

    public function test_excessive_consecutive_periods_penalizes_beyond_the_threshold(): void
    {
        $week = $this->makeWeek(1, 6);
        // 5 consecutive periods for the same teacher -- threshold is 3, so 2 periods are "excessive".
        foreach ([1, 2, 3, 4, 5] as $p) {
            $this->makeSlot($week['Monday'][$p]);
        }

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(2, $result['baseline_score']['breakdown']['excessive_consecutive_periods']);
    }

    public function test_room_switches_are_only_scored_when_room_data_exists(): void
    {
        $week = $this->makeWeek(1, 2);
        $this->makeSlot($week['Monday'][1]); // no room_number anywhere
        $this->makeSlot($week['Monday'][2]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(0, $result['baseline_score']['breakdown']['room_switch_count']);
    }

    public function test_room_switch_between_adjacent_periods_is_flagged_when_room_data_exists(): void
    {
        $week = $this->makeWeek(1, 2);
        $this->makeSlot($week['Monday'][1], ['room_number' => '101']);
        $this->makeSlot($week['Monday'][2], ['room_number' => '102']);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['baseline_score']['breakdown']['room_switch_count']);
    }

    public function test_total_score_is_the_weighted_sum_of_the_breakdown(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Monday'][3]); // 1 teacher gap, 1 class gap

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $breakdown = $result['baseline_score']['breakdown'];
        $expected = $breakdown['teacher_gap_periods'] * 4
            + $breakdown['class_gap_periods'] * 3
            + $breakdown['teacher_workload_imbalance'] * 2
            + $breakdown['prefer_morning_violations'] * 2
            + $breakdown['excessive_consecutive_periods'] * 3
            + $breakdown['room_switch_count'] * 1;

        $this->assertSame($expected, $result['baseline_score']['total']);
    }

    public function test_scoring_context_includes_the_teachers_other_classes(): void
    {
        $week = $this->makeWeek(1, 3);
        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => 999, 'is_active' => true]);
        $this->makeSlot($week['Monday'][1]);
        // Same teacher, ANOTHER class, period 3 -- creates a gap in the teacher's day even though it's not this class/section's own slot.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $week['Monday'][3]->id,
            'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'status' => 'published',
        ]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['baseline_score']['breakdown']['teacher_gap_periods'], 'The teacher gap must be visible even though the OTHER slot belongs to a different class.');
    }

    // ============================================================
    // No-op / empty cases
    // ============================================================

    public function test_no_op_when_no_improvement_exists(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Monday'][2]);
        $this->makeSlot($week['Monday'][3]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['movements']);
        $this->assertSame($result['baseline_score']['total'], $result['proposed_score']['total']);
    }

    public function test_empty_class_returns_a_clean_not_ok_result(): void
    {
        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertFalse($result['ok']);
        $this->assertNull($result['baseline_score']);
        $this->assertSame([], $result['movements']);
    }

    // ============================================================
    // Candidate generation / movement selection
    // ============================================================

    public function test_a_swap_that_fixes_a_teacher_gap_is_proposed(): void
    {
        $week = $this->makeWeek(1, 3);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        $thirdSubject = Subject::create(['name' => 'English', 'code' => 'RB-ENG' . uniqid()]);
        // Teacher A: P1 (Math), P3 (Science) -- gap at P2. Teacher B: P2 (English).
        // Every period this day is a DIFFERENT subject, so no arrangement
        // reached by swapping can ever trip the subject-per-day cap --
        // isolates the teacher-gap improvement as the only thing in play.
        // Swapping teacher A's P3 lesson with teacher B's P2 lesson closes
        // the gap for teacher A (now P1,P2) and leaves teacher B with just
        // P3 (no gap, since they'd only have 1 period).
        $slotA1 = $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id]);
        $slotB = $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id, 'subject_id' => $thirdSubject->id]);
        $slotA3 = $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertNotEmpty($result['movements']);
        $this->assertSame('swap', $result['movements'][0]['type']);
        $this->assertLessThan($result['baseline_score']['total'], $result['proposed_score']['total']);
        $this->assertSame(0, $result['proposed_score']['breakdown']['teacher_gap_periods']);
    }

    public function test_a_relocation_to_a_genuinely_free_period_is_proposed_when_no_swap_helps(): void
    {
        $week = $this->makeWeek(1, 4);
        // Only one lesson this day at period 4 for a prefer_morning subject -- period 1 is free, moving there removes the violation and there's no OTHER lesson to swap with.
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertNotEmpty($result['movements']);
        $this->assertSame('relocate', $result['movements'][0]['type']);
        $this->assertSame(0, $result['proposed_score']['breakdown']['prefer_morning_violations']);
    }

    public function test_movements_include_a_human_readable_reason(): void
    {
        $week = $this->makeWeek(1, 4);
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertNotEmpty($result['movements'][0]['reason']);
        $this->assertIsString($result['movements'][0]['reason']);
    }

    public function test_scoring_and_movement_selection_is_deterministic_across_repeated_calls(): void
    {
        $week = $this->makeWeek(2, 6);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id]);
        $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id]);
        $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id]);
        $this->makeSlot($week['Tuesday'][6], ['subject_id' => $this->morningSubject->id]);

        $service = new TimetableRebalanceService();
        $first = $service->analyze($this->class->id, $this->section->id, null, 'published');
        $second = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame($first['baseline_score'], $second['baseline_score']);
        $this->assertSame($first['proposed_score'], $second['proposed_score']);
        $this->assertSame($first['movements'], $second['movements']);
    }

    // ============================================================
    // Locked lesson protection
    // ============================================================

    public function test_a_locked_slot_is_never_proposed_to_move(): void
    {
        $week = $this->makeWeek(1, 3);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id, 'is_locked' => true]);
        $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id]);
        $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame(1, $result['locked_excluded_count']);
        foreach ($result['movements'] as $movement) {
            if ($movement['type'] === 'swap') {
                $this->assertNotEquals(TimetableSlot::where('bell_timing_id', $week['Monday'][1]->id)->value('id'), $movement['slot_a_id']);
                $this->assertNotEquals(TimetableSlot::where('bell_timing_id', $week['Monday'][1]->id)->value('id'), $movement['slot_b_id']);
            } else {
                $lockedId = TimetableSlot::where('bell_timing_id', $week['Monday'][1]->id)->value('id');
                $this->assertNotEquals($lockedId, $movement['slot_id']);
            }
        }
    }

    public function test_every_lesson_locked_returns_a_clean_no_movable_result(): void
    {
        $week = $this->makeWeek(1, 2);
        $this->makeSlot($week['Monday'][1], ['is_locked' => true]);
        $this->makeSlot($week['Monday'][2], ['is_locked' => true]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertFalse($result['ok']);
        $this->assertSame(2, $result['locked_excluded_count']);
        $this->assertSame([], $result['movements']);
    }

    // ============================================================
    // Hard constraints -- candidates that would violate one are never proposed
    // ============================================================

    public function test_never_proposes_a_swap_that_would_create_a_teacher_conflict(): void
    {
        $week = $this->makeWeek(1, 3);
        $busyClass = SchoolClass::create(['name' => 'Busy', 'class_order' => 999, 'is_active' => true]);
        // This teacher is ALSO busy elsewhere at period 3 -- so relocating/swapping the period-1 lesson to period 3 must never be proposed.
        TimetableSlot::create(['school_class_id' => $busyClass->id, 'bell_timing_id' => $week['Monday'][3]->id, 'subject_id' => $this->subject->id, 'teacher_id' => $this->teacher->id, 'status' => 'published']);
        $slot = $this->makeSlot($week['Monday'][1]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        foreach ($result['movements'] as $movement) {
            $toBellTimingId = $movement['type'] === 'relocate'
                ? BellTiming::where('day_of_week', 'Monday')->where('period_name', 'Period 3')->value('id')
                : null;
            if ($toBellTimingId) {
                $this->assertNotEquals($toBellTimingId, $movement['to_bell_timing_id'] ?? null, 'Must never propose moving into a period that creates a teacher conflict.');
            }
        }
        // Explicit re-validation: applying every proposed movement must never fail.
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_swap_that_would_create_a_room_conflict(): void
    {
        $week = $this->makeWeek(1, 4);
        $busyClass = SchoolClass::create(['name' => 'Busy', 'class_order' => 999, 'is_active' => true]);
        $otherTeacher = Teacher::create(['name' => 'Other', 'status' => 'active']);
        // Room Lab-1 is busy (a different class) at P3 -- P3 is otherwise
        // the attractive destination for this prefer-morning-violating
        // lesson currently sitting at P4, since P1/P2 are already taken.
        TimetableSlot::create(['school_class_id' => $busyClass->id, 'bell_timing_id' => $week['Monday'][3]->id, 'subject_id' => $this->subject->id, 'teacher_id' => $otherTeacher->id, 'status' => 'published', 'room_number' => 'Lab-1']);
        // Every movable slot in this class shares the SAME room (a common
        // real case -- one homeroom) so there is no room-less slot that
        // could legitimately fill the P3 gap without a room conflict.
        $this->makeSlot($week['Monday'][1], ['subject_id' => $this->subject->id, 'room_number' => 'Lab-1']);
        $this->makeSlot($week['Monday'][2], ['subject_id' => $this->morningSubject->id, 'room_number' => 'Lab-1']);
        $this->makeSlot($week['Monday'][4], ['subject_id' => \App\Models\Subject::create(['name' => 'English', 'code' => 'RB-ENG' . uniqid()])->id, 'room_number' => 'Lab-1']);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $targetedPeriods = collect($result['movements'])->filter(fn ($m) => $m['type'] === 'relocate')->pluck('to_bell_timing_id');
        $this->assertNotContains($week['Monday'][3]->id, $targetedPeriods, 'Must never propose moving Lab-1 into a period where Lab-1 is already occupied by another class.');
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_move_that_would_violate_teacher_availability(): void
    {
        $week = $this->makeWeek(1, 4);
        // Subject prefers morning but sits at P4 (afternoon, position 3 of
        // 0..3 -- a violation). P1 is the ideal fix, but the teacher is
        // explicitly unavailable there -- P2 is the only legal alternative.
        TeacherAvailability::create(['teacher_id' => $this->teacher->id, 'bell_timing_id' => $week['Monday'][1]->id, 'is_available' => false]);
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $targetedPeriods = collect($result['movements'])->filter(fn ($m) => $m['type'] === 'relocate')->pluck('to_bell_timing_id');
        $this->assertNotContains($week['Monday'][1]->id, $targetedPeriods, 'Must never propose moving into a period the teacher is marked unavailable for.');
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_move_into_a_non_teaching_period(): void
    {
        $week = $this->makeWeek(1, 4);
        // candidatePeriodsFor()/loadPeriodMeta() both filter to teaching-type
        // periods only, so a break can never even enter the candidate pool
        // -- this is a regression guard on that filtering, not a live trap.
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Break', 'start_time' => '06:00:00', 'end_time' => '06:15:00', 'is_active' => true, 'is_break' => true, 'period_type' => BellTiming::PERIOD_TYPE_BREAK, 'order_index' => 0]);
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertNotEmpty($result['movements'], 'Expected a relocation away from the afternoon prefer-morning violation.');
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_academic_year_isolation_context_from_another_year_never_affects_scoring_or_candidates(): void
    {
        $week = $this->makeWeek(1, 4);
        foreach ($week['Monday'] as $timing) {
            $timing->update(['academic_year' => '2026-2027']);
        }
        // Same day name, same "attractive" early order_index, but a
        // DIFFERENT academic year -- must never be offered as a destination.
        $otherYearTiming = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 0', 'start_time' => '06:00:00', 'end_time' => '07:00:00', 'is_active' => true, 'is_break' => false, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING, 'order_index' => 0, 'academic_year' => '2025-2026']);
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id, 'academic_year' => '2026-2027']);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, '2026-2027', 'published');

        $targetedPeriods = collect($result['movements'])->filter(fn ($m) => $m['type'] === 'relocate')->pluck('to_bell_timing_id');
        $this->assertNotContains($otherYearTiming->id, $targetedPeriods);
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_move_for_a_combined_group_slot(): void
    {
        $week = $this->makeWeek(1, 3);
        $session = \App\Models\AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027-' . uniqid(), 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined ' . uniqid(), 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        // Combined at P1 (Math), movable at P3 (a DIFFERENT subject, so the
        // only thing standing between them -- P2 -- is a genuine, legal
        // gap-closing relocation target for the movable slot alone).
        $combined = $this->makeSlot($week['Monday'][1], ['combined_class_group_id' => $group->id, 'subject_id' => $this->subject->id]);
        $this->makeSlot($week['Monday'][3], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertNotEmpty($result['movements'], 'Expected the gap between the combined slot and the movable slot to be closed.');
        foreach ($result['movements'] as $movement) {
            $ids = $movement['type'] === 'swap' ? [$movement['slot_a_id'], $movement['slot_b_id']] : [$movement['slot_id']];
            $this->assertNotContains($combined->id, $ids);
        }
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_move_that_would_exceed_teacher_daily_limit(): void
    {
        $limitedTeacher = Teacher::create(['name' => 'Limited', 'status' => 'active', 'max_periods_per_day' => 1]);
        $week = $this->makeWeek(1, 4);
        // This teacher already has a period elsewhere (another class) on
        // Monday at P1 -- their daily limit is 1, so relocating THIS
        // lesson anywhere on Monday would put them at 2 that day and must
        // never be proposed; the only legal fix (if any) is off Monday
        // entirely, which a single-day fixture can't offer -- so nothing
        // legal should ever target Monday for this teacher.
        TimetableSlot::create(['school_class_id' => SchoolClass::create(['name' => 'X', 'class_order' => 999, 'is_active' => true])->id, 'bell_timing_id' => $week['Monday'][1]->id, 'subject_id' => $this->subject->id, 'teacher_id' => $limitedTeacher->id, 'status' => 'published']);
        $this->makeSlot($week['Monday'][4], ['teacher_id' => $limitedTeacher->id, 'subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame([], $result['movements'], 'A single day with an already-at-limit teacher has no legal relocation target -- must propose nothing rather than break the daily limit.');
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    public function test_never_proposes_a_move_that_would_exceed_subject_per_day_cap(): void
    {
        $week = $this->makeWeek(1, 4);
        $this->makeSlot($week['Monday'][1]);
        // Same subject, same class, same day already at period 2 -- any
        // relocation of either Math slot within the SAME day would still
        // leave 2 Math periods that day, illegal without require_consecutive.
        $this->makeSlot($week['Monday'][2]);
        // A third, different-subject slot with an obvious prefer-morning
        // violation gives the search something it COULD legally improve,
        // proving the Math pair is specifically what gets left alone.
        $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        foreach ($result['movements'] as $movement) {
            $ids = $movement['type'] === 'swap' ? [$movement['slot_a_id'], $movement['slot_b_id']] : [$movement['slot_id']];
            $mathSlotIds = TimetableSlot::where('school_class_id', $this->class->id)->where('subject_id', $this->subject->id)->pluck('id')->all();
            foreach ($mathSlotIds as $mathId) {
                $this->assertNotContains($mathId, $ids, 'Must never move a Math lesson into/within a day that already has another Math lesson.');
            }
        }
        $this->assertRebalanceApplyAlwaysSucceeds($result);
    }

    // ============================================================
    // Draft/published scoping
    // ============================================================

    public function test_draft_slots_are_never_mixed_into_a_published_rebalance(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1], ['status' => 'published']);
        $this->makeSlot($week['Monday'][3], ['status' => 'draft']);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        // Only 1 published slot exists -- no gap possible with a single slot, and the draft slot must not have been counted.
        $this->assertSame(0, $result['baseline_score']['breakdown']['teacher_gap_periods']);
    }

    public function test_draft_status_can_be_rebalanced_independently(): void
    {
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1], ['status' => 'draft']);
        $this->makeSlot($week['Monday'][3], ['status' => 'draft']);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'draft');

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['baseline_score']['breakdown']['teacher_gap_periods']);
    }

    // ============================================================
    // Movement limits / search-budget exhaustion
    // ============================================================

    public function test_movements_never_exceed_the_configured_max(): void
    {
        Config::set('timetable.rebalance.max_movements', 1);
        $week = $this->makeWeek(2, 6);
        $teacherB = Teacher::create(['name' => 'B', 'status' => 'active']);
        $teacherC = Teacher::create(['name' => 'C', 'status' => 'active']);
        $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id]);
        $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id]);
        $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id]);
        $this->makeSlot($week['Tuesday'][1], ['teacher_id' => $teacherC->id]);
        $this->makeSlot($week['Tuesday'][3], ['teacher_id' => $teacherC->id]);
        $this->makeSlot($week['Tuesday'][2], ['teacher_id' => $this->teacher->id]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertLessThanOrEqual(1, count($result['movements']));
    }

    public function test_a_tiny_candidate_evaluation_budget_is_reported_as_exhausted(): void
    {
        Config::set('timetable.rebalance.max_candidate_evaluations', 1);
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Monday'][3]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertTrue($result['budget_exhausted']);
        $this->assertLessThanOrEqual(1, $result['evaluated_candidates']);
    }

    public function test_a_zero_iteration_budget_finds_no_movements(): void
    {
        Config::set('timetable.rebalance.max_iterations', 0);
        $week = $this->makeWeek(1, 3);
        $this->makeSlot($week['Monday'][1]);
        $this->makeSlot($week['Monday'][3]);

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertSame([], $result['movements']);
    }

    public function test_the_search_never_evaluates_more_than_the_configured_candidate_budget(): void
    {
        Config::set('timetable.rebalance.max_candidate_evaluations', 5);
        $week = $this->makeWeek(2, 6);
        foreach (['Monday', 'Tuesday'] as $day) {
            foreach (range(1, 6) as $p) {
                if (($day === 'Monday' && $p === 2) || ($day === 'Tuesday' && $p === 5)) {
                    continue;
                }
                $this->makeSlot($week[$day][$p]);
            }
        }

        $result = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');

        $this->assertLessThanOrEqual(5 * (config('timetable.rebalance.max_iterations')), $result['evaluated_candidates'], 'Evaluated candidates must stay within iteration_count * per-iteration budget bound.');
    }

    // ============================================================
    // Apply phase
    // ============================================================

    public function test_apply_writes_a_proposed_swap_and_preserves_relationships(): void
    {
        $week = $this->makeWeek(1, 3);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        $thirdSubject = Subject::create(['name' => 'English', 'code' => 'RB-ENG' . uniqid()]);
        $slotA1 = $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id, 'academic_year' => '2026-2027']);
        $slotB = $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id, 'subject_id' => $thirdSubject->id, 'academic_year' => '2026-2027']);
        $slotA3 = $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->morningSubject->id, 'academic_year' => '2026-2027']);

        $preview = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');
        $this->assertNotEmpty($preview['movements']);

        $movements = array_map(fn ($m) => $m['type'] === 'swap'
            ? ['type' => 'swap', 'slot_a_id' => $m['slot_a_id'], 'slot_b_id' => $m['slot_b_id']]
            : ['type' => 'relocate', 'slot_id' => $m['slot_id'], 'to_bell_timing_id' => $m['to_bell_timing_id']], $preview['movements']);

        $result = (new TimetableRebalanceService())->apply($movements);

        $this->assertTrue($result['applied']);
        $this->assertSame(count($movements), $result['movements_applied']);

        foreach ([$slotA1, $slotB, $slotA3] as $slot) {
            $slot->refresh();
            $this->assertSame('2026-2027', $slot->academic_year);
            $this->assertSame('published', $slot->status);
        }
    }

    public function test_apply_relocation_writes_an_activity_log_entry(): void
    {
        $week = $this->makeWeek(1, 4);
        $slot = $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $preview = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');
        $this->assertSame('relocate', $preview['movements'][0]['type']);

        $movements = [['type' => 'relocate', 'slot_id' => $preview['movements'][0]['slot_id'], 'to_bell_timing_id' => $preview['movements'][0]['to_bell_timing_id']]];
        (new TimetableRebalanceService())->apply($movements);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_rebalance_relocated',
        ]);
    }

    public function test_apply_swap_writes_the_swap_engines_own_activity_log_entries(): void
    {
        $week = $this->makeWeek(1, 3);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);
        $thirdSubject = Subject::create(['name' => 'English', 'code' => 'RB-ENG' . uniqid()]);
        $this->makeSlot($week['Monday'][1], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->subject->id]);
        $this->makeSlot($week['Monday'][2], ['teacher_id' => $teacherB->id, 'subject_id' => $thirdSubject->id]);
        $this->makeSlot($week['Monday'][3], ['teacher_id' => $this->teacher->id, 'subject_id' => $this->morningSubject->id]);

        $preview = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');
        $swap = collect($preview['movements'])->firstWhere('type', 'swap');
        $this->assertNotNull($swap);

        (new TimetableRebalanceService())->apply([['type' => 'swap', 'slot_a_id' => $swap['slot_a_id'], 'slot_b_id' => $swap['slot_b_id']]]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $swap['slot_a_id'],
            'description' => 'timetable_slot_swapped',
        ]);
    }

    public function test_apply_rejects_a_movement_whose_slot_has_since_been_locked(): void
    {
        $week = $this->makeWeek(1, 4);
        $slot = $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $preview = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');
        $movement = $preview['movements'][0];

        $slot->update(['is_locked' => true]);

        $result = (new TimetableRebalanceService())->apply([['type' => 'relocate', 'slot_id' => $movement['slot_id'], 'to_bell_timing_id' => $movement['to_bell_timing_id']]]);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('locked', strtolower($result['message']));
        $this->assertSame($week['Monday'][4]->id, $slot->fresh()->bell_timing_id);
    }

    public function test_apply_rejects_a_movement_whose_destination_is_no_longer_free(): void
    {
        $week = $this->makeWeek(1, 4);
        $slot = $this->makeSlot($week['Monday'][4], ['subject_id' => $this->morningSubject->id]);

        $preview = (new TimetableRebalanceService())->analyze($this->class->id, $this->section->id, null, 'published');
        $movement = $preview['movements'][0];
        $this->assertSame('relocate', $movement['type']);

        // Someone else fills the destination period between preview and apply.
        TimetableSlot::create([
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
            'bell_timing_id' => $movement['to_bell_timing_id'], 'subject_id' => $this->subject->id,
            'teacher_id' => Teacher::create(['name' => 'Interloper', 'status' => 'active'])->id, 'status' => 'published',
        ]);

        $result = (new TimetableRebalanceService())->apply([['type' => 'relocate', 'slot_id' => $movement['slot_id'], 'to_bell_timing_id' => $movement['to_bell_timing_id']]]);

        $this->assertFalse($result['applied']);
        $this->assertSame($week['Monday'][4]->id, $slot->fresh()->bell_timing_id);
    }

    public function test_apply_aborts_the_whole_batch_and_rolls_back_if_any_movement_is_invalid(): void
    {
        $week = $this->makeWeek(1, 4);
        $goodSlot = $this->makeSlot($week['Monday'][2], ['subject_id' => $this->morningSubject->id]);
        $badSlot = $this->makeSlot($week['Monday'][4], ['subject_id' => $this->subject->id]);
        $badSlot->update(['is_locked' => true]); // makes the second movement invalid at apply time

        $movements = [
            ['type' => 'relocate', 'slot_id' => $goodSlot->id, 'to_bell_timing_id' => $week['Monday'][1]->id],
            ['type' => 'relocate', 'slot_id' => $badSlot->id, 'to_bell_timing_id' => $week['Monday'][3]->id],
        ];

        $result = (new TimetableRebalanceService())->apply($movements);

        $this->assertFalse($result['applied']);
        $this->assertSame($week['Monday'][2]->id, $goodSlot->fresh()->bell_timing_id, 'The FIRST (valid) movement must be rolled back too -- never a partial apply.');
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_rebalance_relocated']);
    }

    public function test_apply_with_no_movements_is_a_no_op(): void
    {
        $result = (new TimetableRebalanceService())->apply([]);

        $this->assertFalse($result['applied']);
        $this->assertSame(0, $result['movements_applied']);
    }

    /**
     * Mirrors TimetableSwapServiceTest::test_a_failure_mid_transaction_rolls_back_the_entire_swap()
     * and TimetableAutoFixServiceTest's chain equivalent. The existing
     * "aborts_the_whole_batch" test above proves the WEAKER guarantee
     * (a movement that's already invalid before apply() ever writes
     * anything is rejected). This proves the STRONGER one: a real write
     * for the first movement succeeds, THEN a failure is injected before
     * the second movement's write -- the whole transaction, including
     * the already-applied first write, must still roll back completely.
     *
     * Unlike TimetableSwapService::apply()/TimetableAutoFixService::
     * applyChainFix() (which let a \RuntimeException propagate to their
     * caller), TimetableRebalanceService::apply() deliberately catches it
     * itself and returns applied:false -- by design, so the controller
     * never needs its own try/catch (see rebalanceApply()). The
     * DB::transaction() rollback itself is unaffected by where the
     * exception is finally caught: Laravel rolls back and re-throws
     * BEFORE apply()'s own catch block ever runs.
     */
    public function test_a_failure_after_the_first_movement_has_written_rolls_back_everything(): void
    {
        $week = $this->makeWeek(1, 4);
        $slotA = $this->makeSlot($week['Monday'][2], ['subject_id' => $this->morningSubject->id]);
        $slotB = $this->makeSlot($week['Monday'][4], ['subject_id' => $this->subject->id]);

        $movements = [
            ['type' => 'relocate', 'slot_id' => $slotA->id, 'to_bell_timing_id' => $week['Monday'][1]->id],
            ['type' => 'relocate', 'slot_id' => $slotB->id, 'to_bell_timing_id' => $week['Monday'][3]->id],
        ];

        $updateCount = 0;
        TimetableSlot::updating(function () use (&$updateCount) {
            $updateCount++;
            // Let the FIRST movement's update go through for real, then fail on the SECOND.
            if ($updateCount === 2) {
                throw new \RuntimeException('Simulated failure after the first movement has already written');
            }
        });

        try {
            $result = (new TimetableRebalanceService())->apply($movements);
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $this->assertFalse($result['applied'], 'apply() must report failure, not silently swallow the injected exception.');
        $this->assertSame(0, $result['movements_applied']);

        $slotA->refresh();
        $slotB->refresh();

        $this->assertSame($week['Monday'][2]->id, $slotA->bell_timing_id, "Slot A's already-applied move must be rolled back too, not left half-applied.");
        $this->assertSame($week['Monday'][4]->id, $slotB->bell_timing_id, 'Slot B must retain its original period.');
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_rebalance_relocated']);
    }

    /** Every movement analyze() proposes must be independently, immediately applicable (proves the preview and the authoritative resolver never disagree). */
    private function assertRebalanceApplyAlwaysSucceeds(array $preview): void
    {
        if (empty($preview['movements'])) {
            return;
        }

        foreach ($preview['movements'] as $movement) {
            $single = $movement['type'] === 'swap'
                ? ['type' => 'swap', 'slot_a_id' => $movement['slot_a_id'], 'slot_b_id' => $movement['slot_b_id']]
                : ['type' => 'relocate', 'slot_id' => $movement['slot_id'], 'to_bell_timing_id' => $movement['to_bell_timing_id']];

            $result = (new TimetableRebalanceService())->apply([$single]);
            $this->assertTrue($result['applied'], "A movement analyze() proposed was rejected at apply time: {$result['message']}");
        }
    }
}
