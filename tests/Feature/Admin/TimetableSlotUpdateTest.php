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
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable Editor Slice 1: editing an ALREADY-PLACED lesson via
 * TimetableController::update(), by row id (never delete+recreate).
 * Mirrors the conventions already established by TimetableSchedulerTest,
 * TimetableSlotPolicyScopeTest, and TimetableActivityLogTest.
 */
class TimetableSlotUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $teacher;
    protected BellTiming $timing1;
    protected BellTiming $timing2;
    protected BellTiming $timing3;
    protected BellTiming $timing4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Class U' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH' . uniqid()]);
        $this->teacher = Teacher::create(['name' => 'Original Teacher']);

        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $this->timing2 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 2', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 2]);
        $this->timing3 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 3]);
        $this->timing4 = BellTiming::create(['day_of_week' => 'Tuesday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
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

    private function makeSlot(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ], $overrides));
    }

    /** The grid page itself must still render with a real slot present -- exercises the new data-slot JSON embedding and edit modal markup, not just the empty-grid path other tests already cover. */
    public function test_grid_page_renders_the_edit_modal_when_a_slot_is_present(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot();

        $response = $this->actingAs($admin)->get(route('timetable.index', ['school_class_id' => $this->class->id]));

        $response->assertStatus(200);
        $response->assertSee('Edit Lesson');
        $response->assertSee('editSlotModal', false);
        $response->assertSee('openEditModal', false);
    }

    // --- Test D: valid authorized edit -------------------------------------------------

    public function test_admin_can_edit_an_existing_lesson_in_place(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['room_number' => 'Room 1']);
        $newTeacher = Teacher::create(['name' => 'New Teacher']);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $newTeacher->id,
            'room_number' => 'Room 2',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Same row, not a duplicate.
        $this->assertSame(1, TimetableSlot::count());
        $slot->refresh();
        $this->assertSame($newTeacher->id, $slot->teacher_id);
        $this->assertSame('Room 2', $slot->room_number);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_edited',
        ]);
    }

    public function test_editing_moves_the_same_row_to_a_new_period_instead_of_creating_a_duplicate(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot();

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame(1, TimetableSlot::count());
        $slot->refresh();
        $this->assertSame($this->timing2->id, $slot->bell_timing_id);
        $this->assertDatabaseMissing('timetable_slots', ['id' => $slot->id, 'bell_timing_id' => $this->timing1->id]);
    }

    public function test_editing_preserves_status_academic_year_and_generation_relationship(): void
    {
        $admin = $this->makeAdmin();
        $generation = TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => [$this->class->id],
            'style' => TimetableGeneration::STYLE_ROTATING,
            'status' => TimetableGeneration::STATUS_COMPLETED,
        ]);
        $slot = $this->makeSlot([
            'status' => TimetableSlot::STATUS_DRAFT,
            'academic_year' => '2026-2027',
            'timetable_generation_id' => $generation->id,
        ]);
        $newTeacher = Teacher::create(['name' => 'New Teacher']);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $newTeacher->id,
        ]);

        $response->assertSessionHas('success');
        $slot->refresh();
        $this->assertSame(TimetableSlot::STATUS_DRAFT, $slot->status);
        $this->assertSame('2026-2027', $slot->academic_year);
        $this->assertSame($generation->id, $slot->timetable_generation_id);
    }

    // --- Combined groups / archived status ----------------------------------------------

    public function test_editing_a_combined_group_slot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027-' . uniqid(), 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined ' . uniqid(), 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        $slot = $this->makeSlot(['combined_class_group_id' => $group->id]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $slot->refresh();
        $this->assertSame($this->timing1->id, $slot->bell_timing_id);
    }

    /** Test F: the project's real status semantics -- archived is documented as immutable history, draft/published are both editable. */
    public function test_editing_an_archived_slot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['status' => TimetableSlot::STATUS_ARCHIVED]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $slot->refresh();
        $this->assertSame($this->timing1->id, $slot->bell_timing_id);
    }

    /**
     * Lock Integrity hardening: locking a slot (Phase 5) previously only
     * protected it from the Generator and chain Auto-Fix -- this endpoint
     * had no is_locked check at all, so a locked lesson could be freely
     * retargeted to a new class/section/period/teacher/subject/room from
     * the ordinary edit modal. Proves nothing about the row changes and
     * no false 'timetable_slot_edited' log is written on rejection.
     */
    public function test_editing_a_locked_slot_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $otherTeacher = Teacher::create(['name' => 'Other Teacher']);
        $slot = $this->makeSlot(['is_locked' => true]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('locked', strtolower(session('error')));

        $slot->refresh();
        $this->assertSame($this->timing1->id, $slot->bell_timing_id);
        $this->assertSame($this->teacher->id, $slot->teacher_id);

        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_edited',
        ]);
    }

    /** Regression guard: the new is_locked check must never affect a normal (unlocked) edit. */
    public function test_editing_an_unlocked_slot_still_works_normally(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['is_locked' => false]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame($this->timing2->id, $slot->refresh()->bell_timing_id);
    }

    public function test_editing_a_draft_slot_is_permitted_same_as_published(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['status' => TimetableSlot::STATUS_DRAFT]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('success');
    }

    // --- Test A / authorization ----------------------------------------------------------

    public function test_teacher_with_no_assignment_cannot_edit_the_slot(): void
    {
        [$user] = $this->makeTeacherUser();
        $slot = $this->makeSlot();

        $response = $this->actingAs($user)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertForbidden();
        $slot->refresh();
        $this->assertSame($this->timing1->id, $slot->bell_timing_id);
    }

    public function test_teacher_assigned_to_a_different_class_cannot_edit_this_slot(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        $otherClass = SchoolClass::create(['name' => 'Other Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $otherClass->id, 'subject_id' => $this->subject->id,
        ]);
        $slot = $this->makeSlot();

        $response = $this->actingAs($user)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertForbidden();
    }

    /**
     * Documents the real, existing semantics (TimetableSlotPolicy::update()
     * checks whether the ACTING user is assigned to the slot's class-section
     * -- not whether they happen to be the specific teacher_id already on
     * the row). A teacher assigned to a class-section may manage its whole
     * timetable, exactly as they already could via store()/destroy() -- an
     * "another teacher's lesson" restriction narrower than that does not
     * exist anywhere in the current architecture, so this slice does not
     * invent one.
     */
    public function test_teacher_assigned_to_the_class_section_can_edit_a_lesson_currently_taught_by_someone_else(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $this->class->id, 'section_id' => $this->section->id, 'subject_id' => $this->subject->id,
        ]);
        // The slot is currently taught by $this->teacher, a DIFFERENT person than the acting teacher-user.
        $slot = $this->makeSlot();

        $response = $this->actingAs($user)->patch(route('timetable.update', $slot), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Room 9',
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('Room 9', $slot->refresh()->room_number);
    }

    /** A tampered request pointing at a class the acting teacher has no claim over must be rejected even though the ORIGINAL slot is one they can touch. */
    public function test_tampering_school_class_id_to_move_a_lesson_into_an_unauthorized_class_is_rejected(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $this->class->id, 'section_id' => $this->section->id, 'subject_id' => $this->subject->id,
        ]);
        $slot = $this->makeSlot(['teacher_id' => $teacher->id]);
        $unauthorizedClass = SchoolClass::create(['name' => 'Unauthorized Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $response = $this->actingAs($user)->patch(route('timetable.update', $slot), [
            'school_class_id' => $unauthorizedClass->id,
            'section_id' => null,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertForbidden();
        $slot->refresh();
        $this->assertSame($this->class->id, $slot->school_class_id);
    }

    public function test_editing_a_nonexistent_slot_returns_404(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->patch('/admin/timetable/999999', [
            'school_class_id' => $this->class->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertNotFound();
    }

    // --- Test B: invalid references --------------------------------------------------

    public function test_edit_rejects_a_nonexistent_teacher_class_or_subject(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot();

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => 999999,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => 999999,
            'teacher_id' => 999999,
        ]);

        $response->assertSessionHasErrors(['school_class_id', 'subject_id', 'teacher_id']);
        $slot->refresh();
        $this->assertSame($this->class->id, $slot->school_class_id);
    }

    // --- Test C: conflicting period ---------------------------------------------------

    public function test_edit_rejected_when_the_new_period_creates_a_teacher_conflict(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['teacher_id' => $this->teacher->id, 'bell_timing_id' => $this->timing1->id]);

        $otherClass = SchoolClass::create(['name' => 'Class B ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    // --- Phase C: every TimetableConflictResolver rule, reached through the edit endpoint specifically ---

    /** A co-teacher, not just the primary teacher, must block an edit -- teacherOverlapConflicts checks both columns on existing rows. */
    public function test_edit_rejected_for_a_co_teacher_conflict(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['teacher_id' => $this->teacher->id, 'bell_timing_id' => $this->timing1->id]);
        $busyTeacher = Teacher::create(['name' => 'Busy Co-Teacher']);

        $otherClass = SchoolClass::create(['name' => 'Class D ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $busyTeacher->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'co_teacher_id' => $busyTeacher->id,
        ]);

        $response->assertSessionHas('error');
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    /** A class-wide (section_id null) slot must block editing a section-specific lesson into the same period -- classSectionOverlapConflicts' cross-case. */
    public function test_edit_rejected_for_a_class_section_conflict(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id]);

        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => null, // whole-class slot
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => Teacher::create(['name' => 'Whole Class Teacher'])->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    public function test_edit_rejected_for_a_room_conflict(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id, 'room_number' => 'Lab-1']);

        $otherClass = SchoolClass::create(['name' => 'Class E ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => Teacher::create(['name' => 'Other Room Teacher'])->id,
            'room_number' => 'Lab-1',
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Lab-1',
        ]);

        $response->assertSessionHas('error');
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    public function test_edit_rejected_for_a_teacher_availability_conflict(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id]);

        \App\Models\TeacherAvailability::create([
            'teacher_id' => $this->teacher->id,
            'bell_timing_id' => $this->timing2->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    /** periodTypeConflicts: a lesson cannot be edited into a non-teaching (break) period. */
    public function test_edit_rejected_when_the_new_period_is_not_a_teaching_period(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id]);
        $breakTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Break', 'start_time' => '11:00:00', 'end_time' => '11:15:00',
            'is_active' => true, 'is_break' => true, 'period_type' => BellTiming::PERIOD_TYPE_BREAK, 'order_index' => 5,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $breakTiming->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('error');
        $conflict = session('edit_conflict');
        $this->assertSame('period_type', $conflict['conflicts'][0]['type']);
        $slotA->refresh();
        $this->assertSame($this->timing1->id, $slotA->bell_timing_id);
    }

    /**
     * A busy slot sitting on an OTHER academic year's bell timing (same
     * day/time window, different academic_year tag) must never block an
     * edit into the CURRENT year's equivalent period -- proves the
     * isolation TimetableConflictResolver::overlappingBellTimingIds()
     * already provides is actually reachable through the edit endpoint,
     * not just the resolver's own unit tests.
     */
    public function test_edit_respects_academic_year_isolation(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id, 'academic_year' => '2026-2027']);

        $currentYearTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 9', 'start_time' => '13:00:00', 'end_time' => '14:00:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 9, 'academic_year' => '2026-2027',
        ]);
        $otherYearTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 9', 'start_time' => '13:00:00', 'end_time' => '14:00:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 9, 'academic_year' => '2025-2026',
        ]);

        $otherClass = SchoolClass::create(['name' => 'Class F ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $otherYearTiming->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'academic_year' => '2025-2026',
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $currentYearTiming->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame($currentYearTiming->id, $slotA->refresh()->bell_timing_id);
    }

    /** The backend must not collapse multiple simultaneous violations into just the first message -- the full 'conflicts' array must carry every one. */
    public function test_conflict_response_contains_every_violation_not_just_the_first(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['bell_timing_id' => $this->timing1->id]);
        $busyTeacher = Teacher::create(['name' => 'Double Trouble Teacher']);

        $otherClass = SchoolClass::create(['name' => 'Class G ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        // Two independent violations at the SAME target period: the teacher is busy, AND the room is taken.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $busyTeacher->id,
            'room_number' => 'Lab-2',
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $busyTeacher->id,
            'room_number' => 'Lab-2',
        ]);

        $response->assertSessionHas('error');
        $conflicts = session('edit_conflict')['conflicts'];
        $this->assertGreaterThanOrEqual(2, count($conflicts));
        $types = array_column($conflicts, 'type');
        $this->assertContains('teacher', $types);
        $this->assertContains('room', $types);
    }

    /** A suggestion returned by a rejected edit must be genuinely applicable, not just visually present -- apply it for real and confirm it succeeds. */
    public function test_suggested_alternative_from_a_rejected_edit_actually_succeeds_when_applied(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['teacher_id' => $this->teacher->id, 'bell_timing_id' => $this->timing1->id]);

        $otherClass = SchoolClass::create(['name' => 'Class H ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $rejected = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);
        $rejected->assertSessionHas('error');

        $suggestion = session('edit_conflict')['suggestions'][0];

        $applied = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $suggestion['bell_timing_id'],
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $applied->assertSessionHas('success');
        $this->assertSame($suggestion['bell_timing_id'], $slotA->refresh()->bell_timing_id);
    }

    /** The conflict response must carry TimetableSuggestionService's own already-validated alternatives, not an empty/bare rejection. */
    public function test_conflict_response_includes_validated_suggestions_from_the_suggestion_service(): void
    {
        $admin = $this->makeAdmin();
        $slotA = $this->makeSlot(['teacher_id' => $this->teacher->id, 'bell_timing_id' => $this->timing1->id]);

        $otherClass = SchoolClass::create(['name' => 'Class C ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slotA), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing2->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        // Every Monday candidate other than the slot's own current period
        // (timing1) genuinely conflicts here too: the subject-per-day cap
        // sees slotA's own not-yet-moved row still sitting at timing1 (it
        // physically hasn't moved yet -- this is just the pre-write check)
        // and correctly refuses a second same-subject period on the same
        // Monday. timing4 (Tuesday) is a different day's sameDayIds bucket
        // entirely, so it is the one genuinely free, validated alternative.
        $conflict = session('edit_conflict');
        $this->assertNotNull($conflict);
        $this->assertNotEmpty($conflict['suggestions']);
        $this->assertSame($this->timing4->id, $conflict['suggestions'][0]['bell_timing_id']);
    }

    // --- Test E: atomicity -------------------------------------------------------------

    public function test_a_failure_during_the_update_rolls_back_and_leaves_the_database_unchanged(): void
    {
        $this->withoutExceptionHandling();
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['room_number' => 'Original Room']);
        $newTeacher = Teacher::create(['name' => 'New Teacher']);

        TimetableSlot::updating(function () {
            throw new \RuntimeException('Simulated failure after the proposed update begins');
        });

        try {
            $threw = false;

            try {
                $this->actingAs($admin)->patch(route('timetable.update', $slot), [
                    'school_class_id' => $this->class->id,
                    'section_id' => $this->section->id,
                    'bell_timing_id' => $this->timing1->id,
                    'subject_id' => $this->subject->id,
                    'teacher_id' => $newTeacher->id,
                    'room_number' => 'New Room',
                ]);
            } catch (\RuntimeException $e) {
                $threw = true;
            }

            $this->assertTrue($threw, 'Expected the simulated failure to propagate out of the transaction.');
        } finally {
            \Illuminate\Support\Facades\Event::forget('eloquent.updating: ' . TimetableSlot::class);
        }

        $slot->refresh();
        $this->assertSame($this->teacher->id, $slot->teacher_id);
        $this->assertSame('Original Room', $slot->room_number);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_edited',
        ]);
    }
}
