<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableAutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Auto-Fix): applying a relocate-blocker suggestion is one
 * atomic action -- move the blocker, place the new lesson -- and it must
 * re-validate both against LIVE data immediately before writing anything,
 * never trust a suggestion computed earlier as still true.
 */
class TimetableAutoFixServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeGrid(): array
    {
        $timings = [];
        foreach (['Monday', 'Tuesday'] as $day) {
            for ($p = 1; $p <= 2; $p++) {
                $timings["{$day}{$p}"] = BellTiming::create([
                    'day_of_week' => $day, 'period_name' => "P{$p}",
                    'start_time' => sprintf('%02d:00', 7 + $p), 'end_time' => sprintf('%02d:45', 7 + $p),
                    'is_active' => true, 'is_break' => false, 'order_index' => $p,
                    'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
                ]);
            }
        }

        return $timings;
    }

    public function test_applies_the_fix_moving_the_blocker_and_placing_the_new_lesson(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertTrue($result['applied']);
        $this->assertSame($timings['Monday2']->id, $blocker->fresh()->bell_timing_id);
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
        ]);
    }

    public function test_rejects_a_stale_fix_when_the_destination_is_no_longer_free(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $blockerTeacher = Teacher::create(['name' => 'Blocker Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $blockerTeacher->id,
        ]);

        // Something else already took the suggested destination since the
        // suggestion was computed -- the blocker's own teacher is now busy there.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timings['Monday2']->id,
            'subject_id' => $subject->id, 'teacher_id' => $blockerTeacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertFalse($result['applied']);
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id, 'The blocker must not have moved.');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
        ]);
    }

    public function test_rejects_when_the_blocking_slot_no_longer_exists(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation($newPlacement, 999999, $timings['Monday2']->id);

        $this->assertFalse($result['applied']);
    }

    // --- Chain repair --------------------------------------------------------

    /** Three same-day periods, ordered, so candidate search order is deterministic for chain tests. */
    private function makeLinearGrid(int $count = 3, ?string $academicYear = null): array
    {
        $timings = [];
        for ($p = 1; $p <= $count; $p++) {
            $timings[] = BellTiming::create([
                'day_of_week' => 'Monday', 'period_name' => "P{$p}",
                'start_time' => sprintf('%02d:00', 7 + $p), 'end_time' => sprintf('%02d:45', 7 + $p),
                'is_active' => true, 'is_break' => false, 'order_index' => $p,
                'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
                'academic_year' => $academicYear,
            ]);
        }

        return $timings;
    }

    public function test_preview_reports_a_direct_placement_needs_no_moves_when_already_clean(): void
    {
        [$t1] = $this->makeLinearGrid(1);
        $class = SchoolClass::create(['name' => 'Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $result = (new TimetableAutoFixService())->previewChainFix([
            'school_class_id' => $class->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['steps']);
    }

    public function test_preview_and_apply_a_single_hop_chain(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);

        $this->assertTrue($preview['ok']);
        $this->assertCount(1, $preview['steps']);
        $this->assertSame($blocker->id, $preview['steps'][0]['slot_id']);
        $this->assertSame($t2->id, $preview['steps'][0]['to_bell_timing_id']);

        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $applied = $service->applyChainFix($newPlacement, $steps);

        $this->assertTrue($applied['applied']);
        $this->assertSame($t2->id, $blocker->fresh()->bell_timing_id);
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * The scenario this whole feature exists for: A blocks the wanted
     * period, A's only real alternative is blocked by B, and B has a
     * genuinely free period. Neither a plain suggestion nor a single
     * blocker relocation (depth 1) could solve this -- it requires
     * discovering and applying BOTH moves together.
     */
    public function test_discovers_and_applies_a_two_hop_chain(): void
    {
        [$t1, $t2, $t3] = $this->makeLinearGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $slotA = TimetableSlot::create([
            'school_class_id' => $classA->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        $slotB = TimetableSlot::create([
            'school_class_id' => $classB->id, 'bell_timing_id' => $t2->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        // t3 is deliberately left free.

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement, maxDepth: 3);

        $this->assertTrue($preview['ok'], $preview['message']);
        $this->assertCount(2, $preview['steps']);
        // Execution order: deepest (B) first, then A.
        $this->assertSame($slotB->id, $preview['steps'][0]['slot_id']);
        $this->assertSame($t3->id, $preview['steps'][0]['to_bell_timing_id']);
        $this->assertSame($slotA->id, $preview['steps'][1]['slot_id']);
        $this->assertSame($t2->id, $preview['steps'][1]['to_bell_timing_id']);

        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $applied = $service->applyChainFix($newPlacement, $steps);

        $this->assertTrue($applied['applied'], $applied['message']);
        $this->assertSame($t3->id, $slotB->fresh()->bell_timing_id);
        $this->assertSame($t2->id, $slotA->fresh()->bell_timing_id);
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id,
        ]);
        // Nothing was duplicated -- still exactly 3 rows (A, B, the new lesson).
        $this->assertSame(3, TimetableSlot::count());
    }

    /**
     * A conflict that a depth-1 search would resolve trivially (the
     * blocker has a genuinely free alternative period) must still be
     * reported unfixable when the caller caps the search at depth 0 --
     * proves the depth parameter actually bounds the search rather than
     * being silently ignored.
     */
    public function test_a_trivially_fixable_conflict_is_reported_unfixable_when_depth_is_capped_at_zero(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement, maxDepth: 0);

        $this->assertFalse($result['ok']);
        $this->assertSame([], $result['steps']);
        $this->assertSame(1, TimetableSlot::count(), 'Nothing should have been touched by a preview.');
    }

    public function test_no_fix_exists_when_every_period_is_genuinely_occupied(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        // Teacher is busy at BOTH periods -- there is no free period to chain into.
        TimetableSlot::create(['school_class_id' => $classA->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        TimetableSlot::create(['school_class_id' => $classB->id, 'bell_timing_id' => $t2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement, maxDepth: 3);

        $this->assertFalse($result['ok']);
    }

    public function test_constraint_only_conflict_has_no_blocker_to_chain_through(): void
    {
        [$t1] = $this->makeLinearGrid(1);
        $class = SchoolClass::create(['name' => 'Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        // A non-teaching period type has no single blocking row -- unfixable by relocating anything.
        $breakTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Break', 'start_time' => '10:00', 'end_time' => '10:15',
            'is_active' => true, 'is_break' => true, 'order_index' => 99, 'period_type' => BellTiming::PERIOD_TYPE_BREAK,
        ]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $result = (new TimetableAutoFixService())->previewChainFix([
            'school_class_id' => $class->id, 'bell_timing_id' => $breakTiming->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]);

        $this->assertFalse($result['ok']);
    }

    public function test_apply_rejects_a_stale_chain_when_a_step_target_is_no_longer_free(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);
        $this->assertTrue($preview['ok']);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);

        // Something else takes the planned destination between preview and apply.
        TimetableSlot::create(['school_class_id' => $otherClass->id, 'bell_timing_id' => $t2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $applied = $service->applyChainFix($newPlacement, $steps);

        $this->assertFalse($applied['applied']);
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id, 'The blocker must not have moved -- the whole chain rolls back together.');
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id]);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_applied']);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_placed']);
    }

    public function test_apply_rejects_a_chain_step_that_now_targets_a_combined_group_slot(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined', 'subject_id' => $subject->id, 'academic_session_id' => $session->id]);

        // The blocker's own row got turned into a combined-group slot by someone else after preview.
        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'combined_class_group_id' => $group->id]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $applied = (new TimetableAutoFixService())->applyChainFix($newPlacement, [
            ['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id],
        ]);

        $this->assertFalse($applied['applied']);
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id);
    }

    public function test_successful_apply_logs_activity_for_every_moved_slot_and_the_new_placement(): void
    {
        [$t1, $t2, $t3] = $this->makeLinearGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        TimetableSlot::create(['school_class_id' => $classA->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        TimetableSlot::create(['school_class_id' => $classB->id, 'bell_timing_id' => $t2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $service->applyChainFix($newPlacement, $steps);

        $this->assertSame(2, \Spatie\Activitylog\Models\Activity::where('description', 'timetable_autofix_chain_applied')->count());
        $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('description', 'timetable_autofix_chain_placed')->count());
    }

    public function test_moved_blockers_preserve_their_academic_year_and_every_other_relationship(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2, '2026-2027');
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'room_number' => 'Room 9',
        ]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'academic_year' => '2026-2027'];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $service->applyChainFix($newPlacement, $steps);

        $fresh = $blocker->fresh();
        $this->assertSame($t2->id, $fresh->bell_timing_id, 'Only the period should have changed.');
        $this->assertSame('2026-2027', $fresh->academic_year, 'A moved blocker must keep its own academic_year, never inherit the new lesson\'s.');
        $this->assertSame($blockerClass->id, $fresh->school_class_id);
        $this->assertSame($subject->id, $fresh->subject_id);
        $this->assertSame($teacher->id, $fresh->teacher_id);
        $this->assertSame('Room 9', $fresh->room_number);
    }

    public function test_apply_rejects_a_chain_that_names_the_same_slot_twice(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);
        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $applied = (new TimetableAutoFixService())->applyChainFix($newPlacement, [
            ['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id],
            ['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id],
        ]);

        $this->assertFalse($applied['applied']);
    }

    // --- Phase 5: Locked Lessons -----------------------------------------------------

    /**
     * A locked blocker must never be offered as something the chain search
     * can move -- even when relocating it would otherwise be the only
     * available fix, the search must report "no fix found" rather than
     * touching it.
     */
    public function test_a_locked_blocker_is_never_offered_as_part_of_a_fix(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'is_locked' => true,
        ]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        $this->assertFalse($result['ok']);
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id, 'The locked blocker must not have moved.');
    }

    /**
     * When a locked slot blocks the direct path but an UNLOCKED lesson
     * further down the chain can move instead, the search must find that
     * alternative rather than giving up -- proves locks constrain the
     * search space without breaking it entirely.
     */
    public function test_chain_search_finds_an_alternative_path_around_a_locked_slot(): void
    {
        [$t1, $t2, $t3] = $this->makeLinearGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $teacherFree = Teacher::create(['name' => 'Free Teacher', 'status' => 'active']);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        // A distinct subject on the locked slot -- shares the blocker's own
        // subject here and the "once per day" cap would block every
        // candidate regardless of the lock, which isn't what this test is
        // about.
        $lockedSubject = Subject::create(['name' => 'History', 'code' => 'AFH' . uniqid()]);

        // The blocker at t1 -- unlocked, it can move.
        $blocker = TimetableSlot::create([
            'school_class_id' => $classA->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacherFree->id,
        ]);
        // t2 is occupied by a LOCKED slot for the SAME teacher (so moving
        // the blocker there is a genuine teacher-overlap conflict, with the
        // locked slot as the blocking row) -- the search must recognise it
        // can't relocate that blocker and skip to the next candidate.
        TimetableSlot::create([
            'school_class_id' => $classA->id, 'bell_timing_id' => $t2->id,
            'subject_id' => $lockedSubject->id, 'teacher_id' => $teacherFree->id, 'is_locked' => true,
        ]);
        // t3 is free -- the blocker's real escape route.

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacherFree->id, 'subject_id' => $subject->id];

        $result = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertCount(1, $result['steps']);
        $this->assertSame($blocker->id, $result['steps'][0]['slot_id']);
        $this->assertSame($t3->id, $result['steps'][0]['to_bell_timing_id'], 'Must skip the locked t2 slot and land on the genuinely free t3.');
    }

    public function test_apply_rejects_a_step_that_targets_a_slot_locked_since_preview(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);
        $this->assertTrue($preview['ok']);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);

        // Someone locks the blocker between preview and apply.
        $blocker->update(['is_locked' => true]);

        $applied = $service->applyChainFix($newPlacement, $steps);

        $this->assertFalse($applied['applied']);
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id);
    }

    // --- Lock Integrity hardening: the single-hop relocate-blocker path -----------

    /**
     * applyBlockerRelocation() (single-hop Auto-Fix, distinct from the
     * chain search above) had no is_locked awareness at all before this
     * fix -- unlike discoverChain()/applyChainFix(), which already
     * treated a locked blocker as an immovable wall. A locked blocker
     * must be rejected here too, on live data, immediately before any
     * write -- and no activity log entry may exist for either side.
     */
    public function test_a_locked_blocker_is_never_relocated_by_the_single_hop_fix(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id, 'is_locked' => true,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('locked', strtolower($result['message']));
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id, 'The locked blocker must not have moved.');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
        ]);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $blocker->id,
            'description' => 'timetable_autofix_applied',
        ]);
    }

    /**
     * A blocker locked AFTER a suggestion was generated (the interactive
     * flow re-validates on live data at apply time regardless of when the
     * suggestion itself was computed) must still be caught -- same
     * "never trust anything but live data at write time" guarantee the
     * chain path already proves in
     * test_apply_rejects_a_step_that_targets_a_slot_locked_since_preview().
     */
    public function test_a_blocker_locked_after_the_suggestion_was_computed_is_still_rejected(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        // The suggestion (blocking_slot_id + destination period) is
        // decided while the blocker is still unlocked -- exactly what an
        // earlier checkConflictsApi() response would have handed back.
        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        // Locked before the fix is actually applied.
        $blocker->update(['is_locked' => true]);

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertFalse($result['applied']);
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id);
    }
}
