<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableSwapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Swap Engine, service layer: exchanges bell_timing_id between two
 * already-placed lessons. Every conflict category here is delegated to
 * TimetableSuggestionService::checkSwap() -> the one authoritative
 * TimetableConflictResolver -- these tests prove the delegation actually
 * reaches every rule, not that the rules themselves are correct (that's
 * TimetableConflictResolverTest's job).
 */
class TimetableSwapServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $classA;
    protected SchoolClass $classB;
    protected Subject $subjectA;
    protected Subject $subjectB;
    protected Teacher $teacherA;
    protected Teacher $teacherB;
    protected BellTiming $timing1;
    protected BellTiming $timing2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classA = SchoolClass::create(['name' => 'Swap Unit A ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->classB = SchoolClass::create(['name' => 'Swap Unit B ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->subjectA = Subject::create(['name' => 'Mathematics', 'code' => 'SU-MATH' . uniqid()]);
        $this->subjectB = Subject::create(['name' => 'English', 'code' => 'SU-ENG' . uniqid()]);
        $this->teacherA = Teacher::create(['name' => 'Unit Teacher A']);
        $this->teacherB = Teacher::create(['name' => 'Unit Teacher B']);

        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $this->timing2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 2', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 2]);
    }

    private function slotA(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->classA->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subjectA->id,
            'teacher_id' => $this->teacherA->id,
        ], $overrides));
    }

    private function slotB(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->classB->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subjectB->id,
            'teacher_id' => $this->teacherB->id,
        ], $overrides));
    }

    // --- Basic --------------------------------------------------------------

    public function test_valid_swap_exchanges_periods_and_preserves_row_ids(): void
    {
        $a = $this->slotA();
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertTrue($result['applied']);
        $this->assertSame($a->id, $result['slot_a']->id);
        $this->assertSame($b->id, $result['slot_b']->id);
        $this->assertSame($this->timing2->id, $a->fresh()->bell_timing_id);
        $this->assertSame($this->timing1->id, $b->fresh()->bell_timing_id);
        $this->assertSame(2, TimetableSlot::count());
    }

    public function test_preview_does_not_mutate_anything(): void
    {
        $a = $this->slotA();
        $b = $this->slotB();

        $result = (new TimetableSwapService())->preview($a->id, $b->id);

        $this->assertTrue($result['ok']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
        $this->assertSame($this->timing2->id, $b->fresh()->bell_timing_id);
    }

    // --- Self-swap / missing rows --------------------------------------------

    public function test_self_swap_is_rejected(): void
    {
        $a = $this->slotA();

        $result = (new TimetableSwapService())->apply($a->id, $a->id);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('itself', $result['message']);
    }

    public function test_nonexistent_slot_is_rejected(): void
    {
        $a = $this->slotA();

        $result = (new TimetableSwapService())->apply($a->id, 999999);

        $this->assertFalse($result['applied']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
    }

    /** A fresh third class so a conflict-inducing row never collides with slotB()'s own default (classB, timing2). */
    private function thirdClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Swap Unit Third ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    // --- Teacher / co-teacher conflicts --------------------------------------

    public function test_teacher_conflict_is_rejected(): void
    {
        $a = $this->slotA();
        $b = $this->slotB(['teacher_id' => Teacher::create(['name' => 'Third Teacher'])->id]);
        // teacherA is already busy at timing2 elsewhere (a THIRD class, not b itself) -- swapping a into timing2 would double-book them.
        TimetableSlot::create(['school_class_id' => $this->thirdClass()->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('teacher', $types);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
    }

    public function test_co_teacher_conflict_is_rejected(): void
    {
        $busyCoTeacher = Teacher::create(['name' => 'Busy Co-Teacher']);
        TimetableSlot::create(['school_class_id' => $this->thirdClass()->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => Teacher::create(['name' => 'Filler'])->id, 'co_teacher_id' => $busyCoTeacher->id]);

        $a = $this->slotA(['co_teacher_id' => $busyCoTeacher->id]);
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
    }

    // --- Class/section conflicts ---------------------------------------------

    public function test_class_wide_vs_section_specific_conflict_is_rejected(): void
    {
        $section = \App\Models\Section::create(['name' => 'A', 'class_id' => $this->classA->id]);
        $a = $this->slotA(['section_id' => $section->id]);
        // classA already has a WHOLE-CLASS lesson at timing2 -- swapping a's section-specific lesson into timing2 collides with it.
        TimetableSlot::create(['school_class_id' => $this->classA->id, 'section_id' => null, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => Teacher::create(['name' => 'Whole Class Teacher'])->id]);
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('class', $types);
    }

    // --- Room conflicts -------------------------------------------------------

    public function test_room_conflict_is_rejected(): void
    {
        $a = $this->slotA(['room_number' => 'Lab-1']);
        $b = $this->slotB();
        TimetableSlot::create(['school_class_id' => $this->thirdClass()->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => Teacher::create(['name' => 'Room Filler'])->id, 'room_number' => 'Lab-1']);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('room', $types);
    }

    // --- Teacher availability -------------------------------------------------

    public function test_teacher_availability_conflict_is_rejected(): void
    {
        TeacherAvailability::create(['teacher_id' => $this->teacherA->id, 'bell_timing_id' => $this->timing2->id, 'is_available' => false]);

        $a = $this->slotA();
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
    }

    // --- Load limits ------------------------------------------------------------

    public function test_daily_teacher_load_limit_is_rejected(): void
    {
        $limited = Teacher::create(['name' => 'Limited Teacher', 'max_periods_per_day' => 1]);
        // Already teaching one period on Monday (timing2 is also Monday).
        $busyMonday = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 3]);
        TimetableSlot::create(['school_class_id' => $this->classA->id, 'bell_timing_id' => $busyMonday->id, 'subject_id' => $this->subjectA->id, 'teacher_id' => $limited->id]);

        $a = $this->slotA(['teacher_id' => $limited->id]);
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('teacher_day_limit', $types);
    }

    // --- Subject-per-day ---------------------------------------------------------

    public function test_subject_per_day_conflict_is_rejected(): void
    {
        // classA already has Mathematics on Monday at a third period; swapping a's Maths lesson to timing2 (also Monday) would be a second Maths period that day.
        $thirdMonday = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 4', 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 4]);
        TimetableSlot::create(['school_class_id' => $this->classA->id, 'bell_timing_id' => $thirdMonday->id, 'subject_id' => $this->subjectA->id, 'teacher_id' => $this->teacherA->id]);

        $a = $this->slotA();
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('subject_per_day', $types);
    }

    // --- Period type ------------------------------------------------------------

    public function test_swap_into_a_non_teaching_period_is_rejected(): void
    {
        $break = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Break', 'start_time' => '10:00:00', 'end_time' => '10:15:00', 'is_active' => true, 'is_break' => true, 'period_type' => BellTiming::PERIOD_TYPE_BREAK, 'order_index' => 5]);

        $a = $this->slotA();
        $b = $this->slotB(['bell_timing_id' => $break->id]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $types = array_column($result['conflicts'], 'type');
        $this->assertContains('period_type', $types);
    }

    // --- Academic year ------------------------------------------------------------

    public function test_cross_academic_year_swap_is_rejected(): void
    {
        $a = $this->slotA(['academic_year' => '2026-2027']);
        $b = $this->slotB(['academic_year' => '2025-2026']);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('academic year', $result['message']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
    }

    public function test_same_academic_year_swap_is_allowed(): void
    {
        $a = $this->slotA(['academic_year' => '2026-2027']);
        $b = $this->slotB(['academic_year' => '2026-2027']);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertTrue($result['applied']);
    }

    /**
     * Regression: preview() must return the same shape on every rejection
     * path, including the ones rejectIfIneligible() produces. A slot
     * created outside the generator (e.g. the manual "Schedule Class
     * Period" form) can legitimately have a null academic_year, which
     * differs from a generated slot's -- this is exactly the case that
     * crashed TimetableController::swapPreviewApi() with "Undefined array
     * key slot_a" before slot_a/slot_b were added to every branch here.
     */
    public function test_preview_on_ineligible_pair_returns_slot_a_and_slot_b_keys(): void
    {
        $a = $this->slotA(['academic_year' => '2026-2027']);
        $b = $this->slotB(['academic_year' => null]);

        $result = (new TimetableSwapService())->preview($a->id, $b->id);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('slot_a', $result);
        $this->assertArrayHasKey('slot_b', $result);
        $this->assertNull($result['slot_a']);
        $this->assertNull($result['slot_b']);
        $this->assertStringContainsString('academic year', $result['message']);
    }

    // --- Draft vs published -------------------------------------------------------

    public function test_swapping_a_published_slot_with_a_draft_slot_is_rejected(): void
    {
        $a = $this->slotA(['status' => TimetableSlot::STATUS_PUBLISHED]);
        $b = $this->slotB(['status' => TimetableSlot::STATUS_DRAFT]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('different timetable states', $result['message']);
    }

    // --- Combined groups / archived --------------------------------------------

    public function test_combined_group_slot_cannot_be_swapped(): void
    {
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027-' . uniqid(), 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Unit Combined ' . uniqid(), 'subject_id' => $this->subjectA->id, 'academic_session_id' => $session->id]);
        $a = $this->slotA(['combined_class_group_id' => $group->id]);
        $b = $this->slotB();

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('combined', strtolower($result['message']));
    }

    public function test_archived_slot_cannot_be_swapped(): void
    {
        $a = $this->slotA(['status' => TimetableSlot::STATUS_ARCHIVED]);
        $b = $this->slotB(['status' => TimetableSlot::STATUS_ARCHIVED]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertStringContainsString('archived', $result['message']);
    }

    // --- Same-teacher / same-room / same-subject edge cases (must NOT be auto-rejected) ---

    public function test_swapping_two_lessons_taught_by_the_same_teacher_is_allowed_when_otherwise_clean(): void
    {
        $shared = Teacher::create(['name' => 'Shared Teacher']);
        $a = $this->slotA(['teacher_id' => $shared->id]);
        $b = $this->slotB(['teacher_id' => $shared->id]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertTrue($result['applied'], 'The same teacher simply teaching both lessons is not itself a conflict.');
    }

    public function test_swapping_two_lessons_in_the_same_room_is_allowed_when_otherwise_clean(): void
    {
        $a = $this->slotA(['room_number' => 'Lab-9']);
        $b = $this->slotB(['room_number' => 'Lab-9']);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertTrue($result['applied']);
    }

    // --- Activity log / failure safety ------------------------------------------

    public function test_successful_swap_logs_before_after_for_both_slots(): void
    {
        $a = $this->slotA();
        $b = $this->slotB();

        (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $a->id,
            'description' => 'timetable_slot_swapped',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $b->id,
            'description' => 'timetable_slot_swapped',
        ]);
    }

    public function test_failed_swap_writes_no_activity_log_and_leaves_both_rows_untouched(): void
    {
        $a = $this->slotA();
        $b = $this->slotB(['teacher_id' => Teacher::create(['name' => 'Third'])->id]);
        TimetableSlot::create(['school_class_id' => $this->thirdClass()->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $result = (new TimetableSwapService())->apply($a->id, $b->id);

        $this->assertFalse($result['applied']);
        $this->assertSame($this->timing1->id, $a->fresh()->bell_timing_id);
        $this->assertSame($this->timing2->id, $b->fresh()->bell_timing_id);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $a->id,
            'description' => 'timetable_slot_swapped',
        ]);
    }

    /**
     * Concurrency-equivalent proof (PHPUnit is single-threaded, so this
     * simulates the meaningful guarantee rather than literal parallel
     * requests): a swap that a preview() said was clean must be
     * independently re-validated by apply() against whatever the DB
     * actually holds by the time it runs -- if something else moved in
     * between, apply() must catch it, not trust the earlier preview.
     */
    public function test_apply_revalidates_independently_even_after_an_earlier_clean_preview(): void
    {
        $a = $this->slotA();
        $b = $this->slotB();
        $service = new TimetableSwapService();

        $preview = $service->preview($a->id, $b->id);
        $this->assertTrue($preview['ok']);

        // Something else changes between preview and apply: teacherA becomes busy at timing2 (a THIRD class, not b itself).
        TimetableSlot::create(['school_class_id' => $this->thirdClass()->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $result = $service->apply($a->id, $b->id);

        $this->assertFalse($result['applied'], 'apply() must not trust a stale preview -- it must see the newly-created conflict.');
    }

    // --- Atomicity ------------------------------------------------------------

    /**
     * Pre-Auto-Fix hardening: apply()'s write is three sequential updates
     * inside one DB::transaction() (archive A, move B, restore+move A --
     * see the archived-parking trick documented on TimetableSwapService).
     * A failure between the first and second update -- forced here via an
     * Eloquent::updating listener, the same technique
     * TimetableSlotUpdateTest already uses for the same purpose -- must
     * roll back the ENTIRE transaction, not leave slot A stuck in
     * 'archived' status with B never having moved.
     */
    public function test_a_failure_mid_transaction_rolls_back_the_entire_swap(): void
    {
        $this->withoutExceptionHandling();
        $a = $this->slotA()->fresh();
        $b = $this->slotB()->fresh();
        $aOriginalStatus = $a->status;
        $aOriginalTiming = $a->bell_timing_id;
        $bOriginalTiming = $b->bell_timing_id;

        $updateCount = 0;
        TimetableSlot::updating(function () use (&$updateCount) {
            $updateCount++;
            // Let the FIRST update (archiving A) go through, then fail on
            // the second (moving B) -- proving a failure genuinely mid-
            // transaction, after a real mutation has already happened,
            // still rolls everything back together.
            if ($updateCount === 2) {
                throw new \RuntimeException('Simulated failure after the swap transaction has begun mutating rows');
            }
        });

        try {
            $threw = false;

            try {
                (new TimetableSwapService())->apply($a->id, $b->id);
            } catch (\RuntimeException $e) {
                $threw = true;
            }

            $this->assertTrue($threw, 'Expected the simulated failure to propagate out of the transaction.');
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $a->refresh();
        $b->refresh();

        // No partial swap: neither row ended up parked in 'archived', and
        // both are exactly where they started.
        $this->assertSame($aOriginalStatus, $a->status, 'Slot A must not be left archived by a rolled-back swap.');
        $this->assertSame($aOriginalTiming, $a->bell_timing_id, 'Slot A must retain its original period.');
        $this->assertSame($bOriginalTiming, $b->bell_timing_id, 'Slot B must retain its original period.');
        $this->assertSame($this->classA->id, $a->school_class_id, 'Slot A must retain its original class relationship.');
        $this->assertSame($this->classB->id, $b->school_class_id, 'Slot B must retain its original class relationship.');

        // Consistent activity logging: the log calls sit after all three
        // updates in apply()'s transaction, so a rollback this early must
        // leave no log entry for either slot -- not a log for one slot
        // with no matching data change.
        $this->assertDatabaseMissing('activity_log', [
            'description' => 'timetable_slot_swapped',
        ]);
    }
}
