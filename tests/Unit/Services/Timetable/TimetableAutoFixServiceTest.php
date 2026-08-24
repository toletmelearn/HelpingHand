<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableGeneration;
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

    /**
     * Timetable Hardening pass: the final updateOrCreate() for $newPlacement
     * matches by NATURAL KEY (class, section, bell_timing, status), not by
     * row id -- and TimetableConflictResolver deliberately treats an
     * existing row at that exact key as "self" rather than a conflict (see
     * classSectionOverlapConflicts()'s documented same-section exclusion),
     * so a locked row sitting there is never surfaced as a "blocker"
     * through the normal UI flow at all. $blockingSlotId is caller-supplied
     * and never verified to be the row actually occupying the destination,
     * so a direct call naming a genuinely unrelated (unlocked) blocker
     * while a DIFFERENT, locked row already occupies the destination must
     * still be rejected, not silently overwritten.
     */
    public function test_apply_blocker_relocation_rejects_when_the_destination_is_occupied_by_a_locked_slot(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        // A different subject for the locked row -- otherwise
        // subjectPerDayConflicts() (a genuine, pre-existing resolver rule)
        // would already reject $newPlacement on its own, before this test
        // ever reaches the destination-occupant guard being exercised here.
        $otherSubject = Subject::create(['name' => 'History', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $lockedRowTeacher = Teacher::create(['name' => 'Locked Row Teacher', 'status' => 'active']);

        // Genuine, reportable blocker: a DIFFERENT class taught by the same
        // shared teacher at Monday1 -- a real teacher-overlap conflict.
        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        // A locked slot already sitting at the exact natural key the new
        // lesson wants (new class, no section, Monday1, published) -- a
        // DIFFERENT row from $blocker, invisible to the resolver's
        // class/section conflict check for the reason documented above.
        $lockedAtDestination = TimetableSlot::create([
            'school_class_id' => $newClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $otherSubject->id, 'teacher_id' => $lockedRowTeacher->id, 'is_locked' => true,
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
        $this->assertStringContainsString('locked', $result['message']);
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id, 'The blocker must not have moved either -- nothing in this attempt is applied.');
        $this->assertSame($lockedRowTeacher->id, $lockedAtDestination->fresh()->teacher_id, 'The locked row must be completely untouched.');
        $this->assertTrue($lockedAtDestination->fresh()->is_locked);
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

    /**
     * Timetable Hardening pass: same reasoning as
     * test_apply_blocker_relocation_rejects_when_the_destination_is_occupied_by_a_locked_slot()
     * above, for applyChainFix()'s own final updateOrCreate(). A locked row
     * already sitting at the root destination's exact natural key is
     * invisible to the resolver's class/section conflict check, so
     * previewChainFix() reports "no conflict, nothing to move" and this is
     * called with an empty step list -- exactly what a direct call would
     * look like, since there is genuinely nothing to discover a chain for.
     */
    public function test_apply_chain_fix_rejects_when_the_destination_is_occupied_by_a_locked_slot(): void
    {
        [$t1] = $this->makeLinearGrid(1);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        // A different subject for the locked row -- otherwise
        // subjectPerDayConflicts() (a genuine, pre-existing resolver rule)
        // would already reject $newPlacement on its own, before this test
        // ever reaches the destination-occupant guard being exercised here.
        $otherSubject = Subject::create(['name' => 'History', 'code' => 'AF' . uniqid()]);
        $lockedRowTeacher = Teacher::create(['name' => 'Locked Row Teacher', 'status' => 'active']);
        $newTeacher = Teacher::create(['name' => 'New Teacher', 'status' => 'active']);

        $lockedAtDestination = TimetableSlot::create([
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $otherSubject->id, 'teacher_id' => $lockedRowTeacher->id, 'is_locked' => true,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $newTeacher->id, 'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyChainFix($newPlacement, []);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('locked', $result['message']);
        $this->assertSame($lockedRowTeacher->id, $lockedAtDestination->fresh()->teacher_id, 'The locked row must be completely untouched.');
        $this->assertTrue($lockedAtDestination->fresh()->is_locked);
        $this->assertSame(1, TimetableSlot::count());
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
     * Hardening pass: before this fix, the blocker was fetched via a plain
     * find() BEFORE the write transaction opened, and every check
     * (including is_locked) ran against that same early snapshot -- the
     * final write went through the SAME stale in-memory object rather than
     * re-reading it. The fetch is now a lockForUpdate() read taken INSIDE
     * the transaction. This proves the write path is governed by a fresh,
     * transaction-scoped read: the row is locked via a raw write --
     * bypassing this test's own Eloquent reference entirely, exactly as a
     * second, truly concurrent database connection would -- and the
     * operation must still see and honour it.
     */
    public function test_a_lock_set_via_a_concurrent_raw_write_is_honoured_by_the_transaction_scoped_fetch(): void
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

        // A raw write, bypassing Eloquent entirely -- what a second, truly
        // concurrent database connection would do; the test's own
        // in-memory $blocker reference is deliberately left stale (never
        // refreshed) to prove the SERVICE re-reads the row itself rather
        // than trusting any reference obtained elsewhere.
        \Illuminate\Support\Facades\DB::table('timetable_slots')->where('id', $blocker->id)->update(['is_locked' => true]);
        $this->assertFalse((bool) $blocker->is_locked, 'Sanity check: this test\'s own reference really is stale.');

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
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id, 'The concurrently-locked blocker must not have moved.');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
        ]);
    }

    /**
     * Hardening pass: this method never had a catch for the DB's own
     * unique-constraint backstop, unlike TimetableController::store()'s
     * identical updateOrCreate() call. A genuinely last-moment collision at
     * the new lesson's destination -- simulated here via a raw insert
     * landing between this method's own conflict checks and its write, the
     * same technique the mid-transaction rollback tests below already use
     * -- must now be rejected gracefully instead of leaking an unhandled
     * QueryException to the caller.
     */
    public function test_a_genuine_last_moment_collision_at_the_destination_is_rejected_gracefully_not_thrown(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $intruderClass = SchoolClass::create(['name' => 'Intruder Class', 'class_order' => 3, 'is_active' => true]);
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

        // Fires when the blocker's own bell_timing_id update runs (the
        // first write in this method) -- inserts a row that collides with
        // the new lesson's own about-to-run updateOrCreate() on the DB's
        // teacher unique index, simulating a write that landed in the gap
        // between this method's checks and its final write.
        \Illuminate\Support\Facades\Event::listen('eloquent.updating: ' . TimetableSlot::class, function () use ($intruderClass, $timings, $sharedTeacher, $subject) {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            \Illuminate\Support\Facades\DB::table('timetable_slots')->insert([
                'school_class_id' => $intruderClass->id,
                'bell_timing_id' => $timings['Monday1']->id,
                'subject_id' => $subject->id,
                'teacher_id' => $sharedTeacher->id,
                'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        try {
            $result = (new TimetableAutoFixService())->applyBlockerRelocation(
                $newPlacement, $blocker->id, $timings['Monday2']->id
            );
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('no longer valid', strtolower($result['message']));
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

    // --- Production hardening: transaction rollback under an injected mid-write failure ---

    /**
     * Mirrors TimetableSwapServiceTest::test_a_failure_mid_transaction_rolls_back_the_entire_swap()
     * for the chain Auto-Fix path: applyChainFix()'s per-step loop writes
     * TWO rows in execution order (slotB then slotA) before creating the
     * new placement. Previous rollback tests only proved "reject BEFORE
     * any write" (a stale/locked/combined-group guard failing early);
     * this proves the stronger guarantee -- a failure injected AFTER the
     * FIRST step has already been written for real still rolls back that
     * already-applied write too, and the new placement is never created.
     */
    public function test_a_failure_after_the_first_chain_step_has_written_rolls_back_everything(): void
    {
        $this->withoutExceptionHandling();
        [$t1, $t2, $t3] = $this->makeLinearGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $slotA = TimetableSlot::create(['school_class_id' => $classA->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        $slotB = TimetableSlot::create(['school_class_id' => $classB->id, 'bell_timing_id' => $t2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $newPlacement = ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement, maxDepth: 3);
        $this->assertTrue($preview['ok'], $preview['message']);
        $this->assertCount(2, $preview['steps']);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);

        $updateCount = 0;
        TimetableSlot::updating(function () use (&$updateCount) {
            $updateCount++;
            // Let the FIRST step's update (slotB -> t3) go through for
            // real, then fail on the SECOND (slotA -> t2) -- a genuine
            // partial write must still be fully undone.
            if ($updateCount === 2) {
                throw new \RuntimeException('Simulated failure after the first chain step has already written');
            }
        });

        try {
            $threw = false;
            try {
                $service->applyChainFix($newPlacement, $steps);
            } catch (\RuntimeException $e) {
                $threw = true;
            }
            $this->assertTrue($threw, 'Expected the simulated failure to propagate out of the transaction.');
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $slotA->refresh();
        $slotB->refresh();

        // No partial chain: slotB's already-written move is rolled back too, not just slotA's.
        $this->assertSame($t1->id, $slotA->bell_timing_id, 'Slot A must retain its original period.');
        $this->assertSame($t2->id, $slotB->bell_timing_id, "Slot B's already-applied move must be rolled back too, not left half-applied.");
        $this->assertSame(2, TimetableSlot::count(), 'The new placement must never have been created.');
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id]);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_applied']);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_placed']);
    }

    /**
     * Root-cause bug found this session: $placement can be blocked by TWO
     * unrelated occupants at once -- one lesson blocking on teacher, a
     * completely different lesson blocking on room. discoverChain() used to
     * resolve only the FIRST blocker it found (teacher sorts ahead of room
     * in TimetableConflictResolver::check()'s conflict list) and declare
     * success immediately, never re-checking whether $placement was
     * actually clean afterward. previewChainFix() would report "Found a
     * fix!" while the room conflict was still completely untouched.
     *
     * This 2-period grid has exactly one free period for the blocker to
     * move into -- not enough room for BOTH the teacher-blocker and the
     * room-holder to relocate, so the correct, honest answer is "no fix
     * found," not a false "fixed!". applyChainFix() already independently
     * re-validates before writing (see the class docblock), so this never
     * actually corrupted data -- only the preview was misleading. Verified
     * to fail (ok:true) before the discoverChain() fix and pass after.
     */
    public function test_preview_chain_fix_reports_no_fix_when_a_second_unrelated_blocker_has_nowhere_to_go(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $roomHolderClass = SchoolClass::create(['name' => 'Room Holder', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $roomHolderTeacher = Teacher::create(['name' => 'Room Holder Teacher', 'status' => 'active']);

        // Blocks t1 for the shared teacher -- its only alternate, t2, is
        // exactly what the room-holder below would also need.
        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        // An entirely unrelated lesson, different teacher, already sitting
        // in the requested room at t1 -- moving the teacher-blocker away
        // does nothing to free this.
        TimetableSlot::create([
            'school_class_id' => $roomHolderClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $roomHolderTeacher->id, 'room_number' => 'Lab-1',
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $sharedTeacher->id, 'subject_id' => $subject->id, 'room_number' => 'Lab-1',
        ];

        $preview = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        $this->assertFalse($preview['ok'], 'Only one of the two unrelated blockers can relocate within this 2-period grid -- the room conflict is genuinely unresolved and must be reported honestly, not as a false success.');

        // And even if a caller tried to apply exactly the (incomplete)
        // teacher-only move anyway, applyChainFix() must independently
        // reject it too -- it must never trust a preview blindly.
        $applied = (new TimetableAutoFixService())->applyChainFix($newPlacement, [
            ['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id],
        ]);

        $this->assertFalse($applied['applied']);
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id, 'A rejected chain must never leave the blocker relocated.');
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id, 'room_number' => 'Lab-1']);
    }

    /**
     * Companion to the test above, proving the discoverChain() fix is a
     * genuine capability, not just a stricter rejection: with a THIRD
     * period available, both the teacher-blocker and the room-blocker have
     * somewhere to go, and the chain-fix must find and report both moves,
     * successfully resolving two simultaneous, unrelated blockers at once.
     */
    public function test_preview_chain_fix_resolves_two_simultaneous_unrelated_blockers_when_a_solution_exists(): void
    {
        [$t1, $t2, $t3] = $this->makeLinearGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $roomHolderClass = SchoolClass::create(['name' => 'Room Holder', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $roomHolderTeacher = Teacher::create(['name' => 'Room Holder Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);
        $roomHolder = TimetableSlot::create([
            'school_class_id' => $roomHolderClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $roomHolderTeacher->id, 'room_number' => 'Lab-1',
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $sharedTeacher->id, 'subject_id' => $subject->id, 'room_number' => 'Lab-1',
        ];

        $preview = (new TimetableAutoFixService())->previewChainFix($newPlacement);

        $this->assertTrue($preview['ok'], 'With a genuinely free period for each blocker, both must be found and resolved.');
        $this->assertCount(2, $preview['steps'], 'Both the teacher-blocker and the room-holder must be part of the reported fix.');
        $movedSlotIds = collect($preview['steps'])->pluck('slot_id')->sort()->values()->all();
        $this->assertSame([$blocker->id, $roomHolder->id], $movedSlotIds, 'The reported fix must move exactly these two lessons, nothing else.');

        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $applied = (new TimetableAutoFixService())->applyChainFix($newPlacement, $steps);

        $this->assertTrue($applied['applied']);
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'room_number' => 'Lab-1']);
        $this->assertNotSame($t1->id, $blocker->fresh()->bell_timing_id);
        $this->assertNotSame($t1->id, $roomHolder->fresh()->bell_timing_id);
        $this->assertNotSame($blocker->fresh()->bell_timing_id, $roomHolder->fresh()->bell_timing_id, 'The two relocated blockers must not collide with each other either.');
    }

    // --- Issue #14: Auto-Fix-created slots must carry the generation they belong to ---

    /**
     * The exact Issue #14 scenario: an admin reviewing a specific
     * generation's draft (unplaced lessons still to resolve) uses chain
     * Auto-Fix to place one. Before this fix, the new slot was created with
     * a NULL timetable_generation_id, so publishGeneration()'s
     * generation-scoped promote sweep (TimetableSlot::draft()->where(
     * 'timetable_generation_id', $generation->id)) never reached it -- the
     * admin saw "your lesson is now scheduled", but the row stayed draft
     * forever, invisible to every Teacher/Parent published-timetable view.
     * This proves the created slot both retains the supplied generation_id
     * AND is genuinely captured by that exact publish-sweep query shape.
     */
    public function test_apply_chain_fix_preserves_a_supplied_generation_id_so_the_slot_is_captured_by_the_publish_sweep(): void
    {
        [$t1, $t2] = $this->makeLinearGrid(2, '2026-2027');
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $generation = TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => [$newClass->id],
            'style' => 'rotating',
            'status' => TimetableGeneration::STATUS_COMPLETED,
            'placed_count' => 0,
            'unplaced_count' => 1,
        ]);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        // Exactly what the draft review grid's Auto-Fix payload carries
        // once the generation id is threaded through: the unplaced
        // lesson's own placement, tagged with the generation being
        // reviewed, requested as a draft row (the review grid's own
        // draft/published toggle state).
        $newPlacement = [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'status' => TimetableSlot::STATUS_DRAFT,
            'timetable_generation_id' => $generation->id,
        ];

        $service = new TimetableAutoFixService();
        $preview = $service->previewChainFix($newPlacement);
        $this->assertTrue($preview['ok'], $preview['message']);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);

        $applied = $service->applyChainFix($newPlacement, $steps);
        $this->assertTrue($applied['applied'], $applied['message']);

        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $t1->id)->first();
        $this->assertNotNull($newSlot, 'The new placement must have been created.');
        $this->assertSame(TimetableSlot::STATUS_DRAFT, $newSlot->status);
        $this->assertSame(
            $generation->id,
            $newSlot->timetable_generation_id,
            'Regression guard for Issue #14: the created slot must carry the generation_id it was resolving an unplaced lesson for, not NULL.'
        );

        // The actual boundary that failed in production: publishGeneration()
        // promotes exactly this query. Before the fix, this collection
        // would NOT have contained the new slot at all.
        $capturedByPublishSweep = TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->pluck('id');
        $this->assertTrue(
            $capturedByPublishSweep->contains($newSlot->id),
            'The Auto-Fix-created slot must be captured by the same generation-scoped query publishGeneration() uses to promote drafts to published.'
        );
    }

    /** Same guard for the single-hop relocate path (applyBlockerRelocation), the other call site this fix touched. */
    public function test_apply_blocker_relocation_preserves_a_supplied_generation_id(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $generation = TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => [$newClass->id],
            'style' => 'rotating',
            'status' => TimetableGeneration::STATUS_COMPLETED,
            'placed_count' => 0,
            'unplaced_count' => 1,
        ]);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
            'status' => TimetableSlot::STATUS_DRAFT,
            'timetable_generation_id' => $generation->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertTrue($result['applied']);
        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $timings['Monday1']->id)->first();
        $this->assertNotNull($newSlot);
        $this->assertSame($generation->id, $newSlot->timetable_generation_id);
    }

    /**
     * The other half of "carry through when present, never invent a
     * default": when the caller supplies no timetable_generation_id at
     * all (every caller in production today, until the controller/view
     * are wired to send one), the created slot must land on NULL -- not a
     * guessed or fabricated generation id.
     */
    public function test_apply_chain_fix_leaves_generation_id_null_when_the_caller_supplies_none(): void
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
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview['steps']);
        $applied = $service->applyChainFix($newPlacement, $steps);
        $this->assertTrue($applied['applied']);

        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $t1->id)->first();
        $this->assertNull($newSlot->timetable_generation_id, 'No generation_id was supplied -- none must be invented.');
    }
}
