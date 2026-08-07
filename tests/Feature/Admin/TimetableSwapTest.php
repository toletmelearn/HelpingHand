<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Swap Engine: exchanges two ALREADY-PLACED lessons' periods via
 * TimetableController::swapSlots() / swapPreviewApi(), backed by
 * TimetableSwapService -> TimetableSuggestionService::checkSwap() ->
 * TimetableConflictResolver -- the same authoritative chain every other
 * timetable write already goes through. HTTP-level: authorization,
 * request validation, transaction/atomicity as observed through the
 * endpoint, activity logging. Rule-by-rule conflict coverage lives in
 * tests/Unit/Services/Timetable/TimetableSwapServiceTest.php.
 */
class TimetableSwapTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subjectA;
    protected Subject $subjectB;
    protected Teacher $teacherA;
    protected Teacher $teacherB;
    protected BellTiming $timing1;
    protected BellTiming $timing2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Swap Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subjectA = Subject::create(['name' => 'Mathematics', 'code' => 'SW-MATH' . uniqid()]);
        $this->subjectB = Subject::create(['name' => 'English', 'code' => 'SW-ENG' . uniqid()]);
        $this->teacherA = Teacher::create(['name' => 'Teacher A']);
        $this->teacherB = Teacher::create(['name' => 'Teacher B']);

        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $this->timing2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 2', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 2]);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeTeacherUser(): array
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);
        $teacher = Teacher::create(['name' => 'Test Teacher ' . uniqid()]);
        $teacher->update(['user_id' => $user->id]);

        return [$user, $teacher];
    }

    private function makeSlotA(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subjectA->id,
            'teacher_id' => $this->teacherA->id,
        ], $overrides));
    }

    private function makeSlotB(array $overrides = []): TimetableSlot
    {
        $otherClass = SchoolClass::create(['name' => 'Swap Class B ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);

        return TimetableSlot::create(array_merge([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subjectB->id,
            'teacher_id' => $this->teacherB->id,
        ], $overrides));
    }

    // --- Basic ------------------------------------------------------------

    public function test_valid_swap_succeeds_via_the_endpoint(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertOk();
        $response->assertJson(['applied' => true]);

        $slotA->refresh();
        $slotB->refresh();
        $this->assertSame($this->timing2->id, $slotA->bell_timing_id);
        $this->assertSame($this->timing1->id, $slotB->bell_timing_id);

        // Same rows, not a duplicate -- exactly the 2 rows that existed before.
        $this->assertSame(2, TimetableSlot::count());
    }

    public function test_swap_preserves_each_rows_own_teacher_subject_class_room(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA(['room_number' => 'Room 1']);
        $slotB = $this->makeSlotB(['room_number' => 'Room 2']);

        $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ])->assertOk();

        $slotA->refresh();
        $slotB->refresh();
        $this->assertSame($this->teacherA->id, $slotA->teacher_id);
        $this->assertSame($this->subjectA->id, $slotA->subject_id);
        $this->assertSame('Room 1', $slotA->room_number);
        $this->assertSame($this->teacherB->id, $slotB->teacher_id);
        $this->assertSame($this->subjectB->id, $slotB->subject_id);
        $this->assertSame('Room 2', $slotB->room_number);
    }

    // --- Authorization ------------------------------------------------------

    public function test_unauthenticated_user_cannot_swap(): void
    {
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->postJson(route('timetable.swap'), ['slot_a_id' => $slotA->id, 'slot_b_id' => $slotB->id]);

        $response->assertUnauthorized();
    }

    public function test_teacher_with_no_assignment_to_either_class_cannot_swap(): void
    {
        [$user] = $this->makeTeacherUser();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($user)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
        $this->assertSame($this->timing2->id, $slotB->refresh()->bell_timing_id);
    }

    /** Assigned to slot A's class only -- direct endpoint access must not bypass the per-slot policy check on slot B. */
    public function test_teacher_assigned_to_only_one_side_cannot_swap(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $this->class->id, 'section_id' => $this->section->id, 'subject_id' => $this->subjectA->id,
        ]);
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($user)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertForbidden();
        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
    }

    public function test_teacher_assigned_to_both_classes_can_swap(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $this->class->id, 'section_id' => $this->section->id, 'subject_id' => $this->subjectA->id,
        ]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $slotB->school_class_id, 'subject_id' => $this->subjectB->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertOk();
        $response->assertJson(['applied' => true]);
    }

    // --- Request validation ---------------------------------------------

    public function test_missing_slot_ids_are_rejected(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slot_a_id', 'slot_b_id']);
    }

    public function test_nonexistent_slot_id_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => 999999,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['slot_b_id']);
    }

    // --- Self-swap ---------------------------------------------------------

    public function test_swapping_a_slot_with_itself_is_rejected_cleanly(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotA->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['applied' => false]);
        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
    }

    // --- Conflict rejection via the endpoint --------------------------------

    public function test_conflicting_swap_is_rejected_and_leaves_both_slots_unchanged(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();
        // teacherA is busy at timing2 via a THIRD, unrelated class -- swapping A there would double-book them.
        $thirdClass = SchoolClass::create(['name' => 'Swap Feature Third ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create(['school_class_id' => $thirdClass->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['applied' => false]);
        $this->assertNotEmpty($response->json('conflicts'));

        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
        $this->assertSame($this->timing2->id, $slotB->refresh()->bell_timing_id);
        // slotA + slotB + the third conflicting slot -- none duplicated by the rejected attempt.
        $this->assertSame(3, TimetableSlot::count());
    }

    // --- Preview endpoint ----------------------------------------------------

    public function test_preview_reports_a_valid_swap_without_changing_anything(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($admin)->getJson(route('timetable.swap-preview', [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]));

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $response->assertJsonPath('slot_a.id', $slotA->id);
        $response->assertJsonPath('slot_b.id', $slotB->id);

        // Read-only -- nothing written.
        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
        $this->assertSame($this->timing2->id, $slotB->refresh()->bell_timing_id);
    }

    public function test_preview_reports_conflicts_for_an_invalid_swap(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();
        $thirdClass = SchoolClass::create(['name' => 'Swap Feature Third ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create(['school_class_id' => $thirdClass->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $response = $this->actingAs($admin)->getJson(route('timetable.swap-preview', [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]));

        $response->assertOk();
        $response->assertJson(['ok' => false]);
        $this->assertNotEmpty($response->json('conflicts'));
    }

    /** viewAny-gated like check-conflicts -- any authenticated viewer may preview, even without swap authorization on either slot. */
    public function test_preview_is_readable_by_a_teacher_with_no_assignment_at_all(): void
    {
        [$user] = $this->makeTeacherUser();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($user)->getJson(route('timetable.swap-preview', [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]));

        $response->assertOk();
    }

    // --- Combined groups / archived ------------------------------------------

    public function test_swapping_a_combined_group_slot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027-' . uniqid(), 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined ' . uniqid(), 'subject_id' => $this->subjectA->id, 'academic_session_id' => $session->id]);
        $slotA = $this->makeSlotA(['combined_class_group_id' => $group->id]);
        $slotB = $this->makeSlotB();

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertStatus(422);
        $this->assertSame($this->timing1->id, $slotA->refresh()->bell_timing_id);
    }

    public function test_swapping_an_archived_slot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA(['status' => TimetableSlot::STATUS_ARCHIVED]);
        $slotB = $this->makeSlotB(['status' => TimetableSlot::STATUS_ARCHIVED]);

        $response = $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ]);

        $response->assertStatus(422);
    }

    // --- Activity log --------------------------------------------------------

    public function test_successful_swap_creates_activity_log_entries_for_both_slots(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();

        $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slotA->id,
            'description' => 'timetable_slot_swapped',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slotB->id,
            'description' => 'timetable_slot_swapped',
        ]);
    }

    public function test_failed_swap_does_not_create_a_false_success_log(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlotA();
        $slotB = $this->makeSlotB();
        $thirdClass = SchoolClass::create(['name' => 'Swap Feature Third ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create(['school_class_id' => $thirdClass->id, 'bell_timing_id' => $this->timing2->id, 'subject_id' => $this->subjectB->id, 'teacher_id' => $this->teacherA->id]);

        $this->actingAs($admin)->postJson(route('timetable.swap'), [
            'slot_a_id' => $slotA->id,
            'slot_b_id' => $slotB->id,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slotA->id,
            'description' => 'timetable_slot_swapped',
        ]);
    }
}
