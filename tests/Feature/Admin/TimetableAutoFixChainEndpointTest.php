<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Auto-Fix chain repair) HTTP endpoints: preview is read-only and
 * open to any authenticated viewer (same gate as check-conflicts); apply
 * is admin-only (moves class-sections the caller may have no claim over)
 * and re-validates everything against live data before writing.
 */
class TimetableAutoFixChainEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeGrid(int $count = 3): array
    {
        $timings = [];
        for ($p = 1; $p <= $count; $p++) {
            $timings[] = BellTiming::create([
                'day_of_week' => 'Monday', 'period_name' => "P{$p}",
                'start_time' => sprintf('%02d:00', 7 + $p), 'end_time' => sprintf('%02d:45', 7 + $p),
                'is_active' => true, 'is_break' => false, 'order_index' => $p,
                'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
            ]);
        }

        return $timings;
    }

    public function test_teacher_can_preview_a_chain_fix(): void
    {
        [$t1, $t2] = $this->makeGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAs($this->teacherUser())->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]));

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertCount(1, $response->json('steps'));
    }

    /**
     * Regression: the preview call must be scoped to whichever grid
     * (draft/published) is actually open. A blocker sitting in the DRAFT
     * grid must be found as a real conflict when status=draft is passed --
     * omitting status here would silently default to 'published' and miss
     * it, exactly the bug this test would have caught.
     */
    public function test_preview_is_scoped_to_the_draft_status_when_requested(): void
    {
        [$t1, $t2] = $this->makeGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'draft']);

        $published = $this->actingAs($this->admin())->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]));
        $published->assertOk();
        $published->assertJson(['ok' => true, 'steps' => []], 'A draft-only blocker must not be found while checking the published grid.');

        $draft = $this->actingAs($this->admin())->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id, 'status' => 'draft',
        ]));
        $draft->assertOk();
        $draft->assertJson(['ok' => true]);
        $this->assertCount(1, $draft->json('steps'));
    }

    public function test_guest_cannot_preview_a_chain_fix(): void
    {
        [$t1] = $this->makeGrid(1);
        $class = SchoolClass::create(['name' => 'Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $response = $this->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $class->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]));

        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_apply_a_chain_fix(): void
    {
        [$t1, $t2] = $this->makeGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $response = $this->actingAs($this->teacherUser())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $t1->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'steps' => [['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id]],
        ]);

        $response->assertForbidden();
        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id);
    }

    public function test_admin_can_preview_then_apply_a_chain_fix_end_to_end(): void
    {
        [$t1, $t2, $t3] = $this->makeGrid(3);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $slotA = TimetableSlot::create(['school_class_id' => $classA->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        $slotB = TimetableSlot::create(['school_class_id' => $classB->id, 'bell_timing_id' => $t2->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        $admin = $this->admin();

        $preview = $this->actingAs($admin)->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
        ]));
        $preview->assertOk();
        $preview->assertJson(['ok' => true]);
        $this->assertCount(2, $preview->json('steps'));

        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview->json('steps'));

        $apply = $this->actingAs($admin)->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'steps' => $steps,
        ]);

        $apply->assertOk();
        $apply->assertJson(['applied' => true]);
        $this->assertSame($t3->id, $slotB->fresh()->bell_timing_id);
        $this->assertSame($t2->id, $slotA->fresh()->bell_timing_id);
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id]);
        $this->assertSame(3, TimetableSlot::count());
    }

    public function test_apply_ignores_a_client_supplied_academic_year(): void
    {
        [$t1, $t2] = $this->makeGrid(2);
        AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]);

        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $apply = $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'academic_year' => '1999-2000',
            'steps' => [],
        ]);

        $apply->assertOk();
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'academic_year' => '2026-2027']);
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id, 'academic_year' => '1999-2000']);
    }

    public function test_a_failure_mid_apply_rolls_back_the_whole_chain_and_leaves_no_activity_log(): void
    {
        $this->withoutExceptionHandling();
        [$t1, $t2] = $this->makeGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        TimetableSlot::updating(function () {
            throw new \RuntimeException('Simulated failure after the chain transaction has begun mutating rows');
        });

        try {
            $threw = false;

            try {
                $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
                    'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
                    'steps' => [['slot_id' => $blocker->id, 'to_bell_timing_id' => $t2->id]],
                ]);
            } catch (\RuntimeException $e) {
                $threw = true;
            }

            $this->assertTrue($threw, 'Expected the simulated failure to propagate out of the transaction.');
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $this->assertSame($t1->id, $blocker->fresh()->bell_timing_id, 'The blocker must not have moved.');
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id]);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_applied']);
        $this->assertDatabaseMissing('activity_log', ['description' => 'timetable_autofix_chain_placed']);
    }

    // --- Issue #14: end-to-end wiring (Admin page -> controller -> service -> publish) ---

    /**
     * The complete Issue #14 path, exactly as a real admin reviewing a
     * generation's draft would trigger it: preview and apply both carry
     * the generation being reviewed, the created slot retains it, and --
     * the actual real-world failure -- publishing that SAME generation
     * through the real publish endpoint now promotes it to published,
     * where it was previously left behind as an invisible orphaned draft.
     */
    public function test_auto_fix_created_slot_is_captured_by_publishing_the_generation_it_belongs_to(): void
    {
        [$t1, $t2] = $this->makeGrid(2);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        // Draft, not published -- matches the real Issue #14 scenario:
        // another lesson already sitting in the draft grid blocks the
        // unplaced one, not an unrelated published row (draft/published
        // are independent grids, so a published blocker wouldn't even be
        // seen as a conflict against a draft placement).
        $blocker = TimetableSlot::create(['school_class_id' => $blockerClass->id, 'bell_timing_id' => $t1->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => TimetableSlot::STATUS_DRAFT]);

        // The generation under review -- covers $newClass, exactly what
        // draft review is resolving an unplaced lesson for.
        $generation = TimetableGeneration::create([
            'academic_year' => null,
            'school_class_ids' => [$newClass->id],
            'style' => 'rotating',
            'status' => TimetableGeneration::STATUS_COMPLETED,
            'placed_count' => 0,
            'unplaced_count' => 1,
        ]);

        $admin = $this->admin();

        $preview = $this->actingAs($admin)->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'status' => 'draft', 'timetable_generation_id' => $generation->id,
        ]));
        $preview->assertOk();
        $preview->assertJson(['ok' => true]);
        $steps = array_map(fn ($s) => ['slot_id' => $s['slot_id'], 'to_bell_timing_id' => $s['to_bell_timing_id']], $preview->json('steps'));

        $apply = $this->actingAs($admin)->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'status' => 'draft', 'timetable_generation_id' => $generation->id,
            'steps' => $steps,
        ]);
        $apply->assertOk();
        $apply->assertJson(['applied' => true]);

        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $t1->id)->first();
        $this->assertNotNull($newSlot);
        $this->assertSame('draft', $newSlot->status);
        $this->assertSame($generation->id, $newSlot->timetable_generation_id, 'Issue #14 regression: the slot must carry the generation it was created to resolve.');

        // The previous failure, reproduced exactly: publishing the SAME
        // generation this slot belongs to must promote it. Before this
        // wiring, this assertion is exactly what would have failed --
        // the slot stayed draft, invisible to Teacher/Parent views.
        $publish = $this->actingAs($admin)->post(route('timetable.generation.publish', $generation));
        $publish->assertRedirect();

        $this->assertSame('published', $newSlot->fresh()->status, 'Issue #14 regression: the Auto-Fix-created slot must be promoted by publishing its own generation.');
    }

    /**
     * Security requirement: a client-supplied timetable_generation_id is
     * never trusted on its own. A generation that genuinely exists but
     * does NOT cover the class this request is placing a lesson into must
     * be rejected outright -- reusing the same school_class_ids membership
     * check viewGenerationReview() already applies -- not silently
     * accepted and attached to the wrong generation's publish sweep.
     */
    public function test_apply_rejects_a_generation_id_that_does_not_cover_the_requested_class(): void
    {
        [$t1] = $this->makeGrid(1);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $unrelatedClass = SchoolClass::create(['name' => 'Unrelated', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        // A real generation, but for a DIFFERENT class than the one this
        // request is placing a lesson into.
        $unrelatedGeneration = TimetableGeneration::create([
            'academic_year' => null,
            'school_class_ids' => [$unrelatedClass->id],
            'style' => 'rotating',
            'status' => TimetableGeneration::STATUS_COMPLETED,
            'placed_count' => 0,
            'unplaced_count' => 0,
        ]);

        $apply = $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'status' => 'draft', 'timetable_generation_id' => $unrelatedGeneration->id,
            'steps' => [],
        ]);

        $apply->assertStatus(422);
        $apply->assertJsonValidationErrors(['timetable_generation_id']);
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $newClass->id]);
    }

    /** A wholly nonexistent generation id must be rejected the same ordinary way any other exists: rule failure already is. */
    public function test_apply_rejects_a_generation_id_that_does_not_exist(): void
    {
        [$t1] = $this->makeGrid(1);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $apply = $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'timetable_generation_id' => 999999,
            'steps' => [],
        ]);

        $apply->assertStatus(422);
        $apply->assertJsonValidationErrors(['timetable_generation_id']);
    }

    /**
     * Regression guard restated at the HTTP boundary: when the request
     * genuinely carries no timetable_generation_id at all (the ordinary
     * published-grid Auto-Fix case, exactly what the page sends when there
     * is no active generation to review), the created slot's
     * generation_id must be NULL -- never invented, never defaulted to
     * some other generation.
     */
    public function test_apply_leaves_generation_id_null_when_the_request_supplies_none(): void
    {
        [$t1] = $this->makeGrid(1);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $apply = $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'steps' => [],
        ]);

        $apply->assertOk();
        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $t1->id)->first();
        $this->assertNotNull($newSlot);
        $this->assertNull($newSlot->timetable_generation_id);
    }

    /**
     * Same regression guard for the exact wire format the real page sends
     * when there is no active generation: an explicit empty string (what
     * @json($activeGeneration->id ?? null) ?? '' renders to in the blade),
     * not an absent key -- must resolve to the same NULL, not a validation
     * error and not an invented id.
     */
    public function test_apply_treats_an_empty_string_generation_id_the_same_as_absent(): void
    {
        [$t1] = $this->makeGrid(1);
        $newClass = SchoolClass::create(['name' => 'New', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFC' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $apply = $this->actingAs($this->admin())->postJson(route('timetable.auto-fix.apply-chain'), [
            'school_class_id' => $newClass->id, 'bell_timing_id' => $t1->id, 'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            'timetable_generation_id' => '',
            'steps' => [],
        ]);

        $apply->assertOk();
        $newSlot = TimetableSlot::where('school_class_id', $newClass->id)->where('bell_timing_id', $t1->id)->first();
        $this->assertNotNull($newSlot);
        $this->assertNull($newSlot->timetable_generation_id);
    }
}
