<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rebalancing Engine HTTP endpoints: authorization, request validation,
 * and the JSON response shape end to end -- rule-by-rule scoring/
 * candidate-generation coverage lives in
 * tests/Unit/Services/Timetable/TimetableRebalanceServiceTest.php, this
 * file proves the routes/controller wire into that service correctly and
 * enforce the same authorization boundaries every other preview/apply
 * pair in this controller already does (preview: viewAny; apply: autoFix,
 * admin-only, mirroring chain Auto-Fix's own apply gate).
 */
class TimetableRebalanceEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Subject $morningSubject;
    protected Teacher $teacher;
    protected BellTiming $timing1;
    protected BellTiming $timing2;
    protected BellTiming $timing4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Rebal Endpoint Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'RBE-MATH' . uniqid(), 'prefer_morning' => false]);
        $this->morningSubject = Subject::create(['name' => 'Science', 'code' => 'RBE-SCI' . uniqid(), 'prefer_morning' => true]);
        $this->teacher = Teacher::create(['name' => 'Rebal Endpoint Teacher', 'status' => 'active']);
        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $this->timing2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 2', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 2]);
        $this->timing4 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 4', 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 4]);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
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

    // ---- Preview: authorization ----

    public function test_guest_cannot_preview_a_rebalance(): void
    {
        $response = $this->getJson(route('timetable.rebalance.preview', ['school_class_id' => $this->class->id]));

        $response->assertUnauthorized();
    }

    public function test_a_teacher_can_preview_a_rebalance(): void
    {
        $this->makeSlot($this->timing1);
        $this->makeSlot($this->timing2);

        $response = $this->actingAs($this->makeTeacherUser())->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_preview_a_rebalance(): void
    {
        $this->makeSlot($this->timing1);
        $this->makeSlot($this->timing2);

        $response = $this->actingAs($this->makeAdmin())->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['ok', 'message', 'baseline_score', 'proposed_score', 'movements', 'locked_excluded_count', 'evaluated_candidates', 'budget_exhausted']);
    }

    public function test_preview_requires_a_valid_school_class_id(): void
    {
        $response = $this->actingAs($this->makeAdmin())->getJson(route('timetable.rebalance.preview', ['school_class_id' => 999999]));

        $response->assertStatus(422);
    }

    public function test_preview_reports_baseline_and_proposed_scores_end_to_end(): void
    {
        // A genuine gap: Period 1 and Period 4 committed, 2 and 3 free.
        $this->makeSlot($this->timing1);
        $this->makeSlot($this->timing4, ['subject_id' => $this->morningSubject->id]);

        $response = $this->actingAs($this->makeAdmin())->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
        ]));

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertGreaterThan(0, $response->json('baseline_score.total'));
    }

    // ---- Apply: authorization ----

    public function test_guest_cannot_apply_a_rebalance(): void
    {
        $response = $this->postJson(route('timetable.rebalance.apply'), ['movements' => [['type' => 'relocate', 'slot_id' => 1, 'to_bell_timing_id' => 1]]]);

        $response->assertUnauthorized();
    }

    public function test_a_teacher_cannot_apply_a_rebalance(): void
    {
        $slot = $this->makeSlot($this->timing1, ['subject_id' => $this->morningSubject->id]);

        $response = $this->actingAs($this->makeTeacherUser())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'relocate', 'slot_id' => $slot->id, 'to_bell_timing_id' => $this->timing2->id]],
        ]);

        $response->assertForbidden();
        $this->assertSame($this->timing1->id, $slot->fresh()->bell_timing_id);
    }

    public function test_apply_requires_a_non_empty_movements_array(): void
    {
        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), ['movements' => []]);

        $response->assertStatus(422);
    }

    // ---- Apply: production-hardening -- movement-item validation ----

    public function test_apply_rejects_a_movement_with_a_missing_type(): void
    {
        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['slot_id' => 1, 'to_bell_timing_id' => 1]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.type']);
    }

    public function test_apply_rejects_a_movement_with_an_unrecognised_type(): void
    {
        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'teleport', 'slot_id' => 1, 'to_bell_timing_id' => 1]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.type']);
    }

    public function test_apply_rejects_a_relocate_movement_with_a_nonexistent_slot_id(): void
    {
        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'relocate', 'slot_id' => 999999, 'to_bell_timing_id' => $this->timing1->id]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.slot_id']);
    }

    /** The exact scenario the production audit found: a stale/tampered to_bell_timing_id must be rejected by validation, never reach the database's own foreign-key constraint as an uncaught exception. */
    public function test_apply_rejects_a_relocate_movement_with_a_nonexistent_to_bell_timing_id(): void
    {
        $slot = $this->makeSlot($this->timing1, ['subject_id' => $this->morningSubject->id]);

        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'relocate', 'slot_id' => $slot->id, 'to_bell_timing_id' => 999999]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.to_bell_timing_id']);
        $this->assertSame($this->timing1->id, $slot->fresh()->bell_timing_id);
    }

    public function test_apply_rejects_a_swap_movement_with_a_nonexistent_slot_a_id(): void
    {
        $slotB = $this->makeSlot($this->timing2);

        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'swap', 'slot_a_id' => 999999, 'slot_b_id' => $slotB->id]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.slot_a_id']);
    }

    public function test_apply_rejects_a_swap_movement_with_a_nonexistent_slot_b_id(): void
    {
        $slotA = $this->makeSlot($this->timing1);

        $response = $this->actingAs($this->makeAdmin())->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'swap', 'slot_a_id' => $slotA->id, 'slot_b_id' => 999999]],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['movements.0.slot_b_id']);
    }

    public function test_apply_accepts_a_well_formed_swap_movement_shape(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot($this->timing1, ['subject_id' => $this->subject->id]);
        $slotB = $this->makeSlot($this->timing2, ['subject_id' => $this->morningSubject->id, 'teacher_id' => Teacher::create(['name' => 'Second Teacher', 'status' => 'active'])->id]);

        $response = $this->actingAs($admin)->postJson(route('timetable.rebalance.apply'), [
            'movements' => [['type' => 'swap', 'slot_a_id' => $slotA->id, 'slot_b_id' => $slotB->id]],
        ]);

        // Validation itself must pass (no 422 from a malformed-shape rejection) -- whatever TimetableRebalanceService::apply() then decides is a separate, already-covered concern.
        $response->assertStatus(200);
    }

    // ---- Apply: end-to-end preview -> apply flow ----

    public function test_admin_can_preview_then_apply_a_rebalance_end_to_end(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot($this->timing4, ['subject_id' => $this->morningSubject->id]);

        $preview = $this->actingAs($admin)->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
        ]));
        $preview->assertOk();
        $movements = $preview->json('movements');
        $this->assertNotEmpty($movements);

        $applyPayload = array_map(fn ($m) => $m['type'] === 'swap'
            ? ['type' => 'swap', 'slot_a_id' => $m['slot_a_id'], 'slot_b_id' => $m['slot_b_id']]
            : ['type' => 'relocate', 'slot_id' => $m['slot_id'], 'to_bell_timing_id' => $m['to_bell_timing_id']], $movements);

        $apply = $this->actingAs($admin)->postJson(route('timetable.rebalance.apply'), ['movements' => $applyPayload]);

        $apply->assertOk();
        $apply->assertJson(['applied' => true]);
        $this->assertNotEquals($this->timing4->id, $slot->fresh()->bell_timing_id);
    }

    public function test_apply_with_a_stale_movement_returns_422_and_changes_nothing(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot($this->timing4, ['subject_id' => $this->morningSubject->id]);

        $preview = $this->actingAs($admin)->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $this->class->id, 'section_id' => $this->section->id,
        ]));
        $movement = $preview->json('movements.0');

        // Lock the slot between preview and apply.
        $slot->update(['is_locked' => true]);

        $applyPayload = $movement['type'] === 'swap'
            ? ['type' => 'swap', 'slot_a_id' => $movement['slot_a_id'], 'slot_b_id' => $movement['slot_b_id']]
            : ['type' => 'relocate', 'slot_id' => $movement['slot_id'], 'to_bell_timing_id' => $movement['to_bell_timing_id']];

        $apply = $this->actingAs($admin)->postJson(route('timetable.rebalance.apply'), ['movements' => [$applyPayload]]);

        $apply->assertStatus(422);
        $apply->assertJson(['applied' => false]);
        $this->assertSame($this->timing4->id, $slot->fresh()->bell_timing_id);
    }
}
