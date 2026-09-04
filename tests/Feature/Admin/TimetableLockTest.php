<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
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
 * Phase 5 (Locked Lessons): lock/unlock a slot -- gated the same as editing
 * it (TimetableSlotPolicy::update()), since locking is a property of the
 * row an editor already has a claim over, not a new administrative
 * capability of its own.
 */
class TimetableLockTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $teacher;
    protected BellTiming $timing1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'Lock Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'LOCKT5' . uniqid()]);
        $this->teacher = Teacher::create(['name' => 'Original Teacher']);
        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
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

    public function test_admin_can_lock_and_unlock_a_slot(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot();

        $lockResponse = $this->actingAs($admin)->post(route('timetable.lock', $slot));
        $lockResponse->assertRedirect();
        $lockResponse->assertSessionHas('success');
        $this->assertTrue($slot->fresh()->is_locked);

        $unlockResponse = $this->actingAs($admin)->post(route('timetable.unlock', $slot));
        $unlockResponse->assertRedirect();
        $this->assertFalse($slot->fresh()->is_locked);
    }

    public function test_teacher_assigned_to_the_class_section_can_lock_a_slot(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        $slot = $this->makeSlot(['teacher_id' => $teacher->id]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $this->class->id, 'section_id' => $this->section->id, 'subject_id' => $this->subject->id,
        ]);

        $response = $this->actingAs($user)->post(route('timetable.lock', $slot));

        $response->assertRedirect();
        $this->assertTrue($slot->fresh()->is_locked);
    }

    public function test_teacher_without_assignment_cannot_lock_a_slot(): void
    {
        [$user] = $this->makeTeacherUser();
        $slot = $this->makeSlot();

        $response = $this->actingAs($user)->post(route('timetable.lock', $slot));

        $response->assertForbidden();
        $this->assertFalse($slot->fresh()->is_locked);
    }

    public function test_guest_cannot_lock_a_slot(): void
    {
        $slot = $this->makeSlot();

        $response = $this->post(route('timetable.lock', $slot));

        $response->assertRedirect(route('login'));
        $this->assertFalse($slot->fresh()->is_locked);
    }

    public function test_locking_writes_an_activity_log_entry(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot();

        $this->actingAs($admin)->post(route('timetable.lock', $slot));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_locked',
        ]);
    }

    public function test_unlocking_writes_an_activity_log_entry(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['is_locked' => true]);

        $this->actingAs($admin)->post(route('timetable.unlock', $slot));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_unlocked',
        ]);
    }

    public function test_a_combined_group_slot_cannot_be_locked_from_a_single_cell(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined', 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $this->class->id]);
        $slot = $this->makeSlot(['combined_class_group_id' => $group->id]);

        $response = $this->actingAs($admin)->post(route('timetable.lock', $slot));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($slot->fresh()->is_locked);
    }

    public function test_an_archived_slot_cannot_be_locked(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['status' => 'archived']);

        $response = $this->actingAs($admin)->post(route('timetable.lock', $slot));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertFalse($slot->fresh()->is_locked);
    }

    public function test_locked_state_is_visible_on_the_grid_page(): void
    {
        $admin = $this->makeAdmin();
        $this->makeSlot(['is_locked' => true]);

        $response = $this->actingAs($admin)->get(route('timetable.index', ['school_class_id' => $this->class->id]));

        $response->assertOk();
        $response->assertSee('is_locked', false);
    }

    // --- Lock Integrity hardening -------------------------------------------

    public function test_teacher_without_assignment_cannot_unlock_a_slot(): void
    {
        [$user] = $this->makeTeacherUser();
        $slot = $this->makeSlot(['is_locked' => true]);

        $response = $this->actingAs($user)->post(route('timetable.unlock', $slot));

        $response->assertForbidden();
        $this->assertTrue($slot->fresh()->is_locked);
    }

    /**
     * destroy() previously had no is_locked check at all -- the "Clear
     * this slot" button sits right next to the lock toggle in the grid
     * and worked on a locked slot with zero resistance. No false
     * 'timetable_slot_cleared' log on the rejected attempt.
     */
    public function test_a_locked_slot_cannot_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['is_locked' => true]);

        $response = $this->actingAs($admin)->delete(route('timetable.destroy', $slot->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('locked', strtolower(session('error')));
        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id]);

        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_cleared',
        ]);
    }

    /** Regression guard: the new is_locked check must never affect a normal (unlocked) delete. */
    public function test_an_unlocked_slot_can_still_be_deleted_normally(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['is_locked' => false]);

        $response = $this->actingAs($admin)->delete(route('timetable.destroy', $slot->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('timetable_slots', ['id' => $slot->id]);
    }

    /**
     * Defense-in-depth for the combined-group branch of destroy(): a
     * combined slot can never actually be locked via lockSlot() itself
     * (it rejects combined rows outright), so this exercises the sibling
     * check directly against the database rather than through the UI --
     * clicking clear on an UNLOCKED member of a group must still be
     * blocked if a DIFFERENT sibling row in that same occurrence has
     * somehow ended up locked (e.g. a future generator change), proving
     * the per-sibling check inside destroy()'s combined branch, not just
     * the top-level check on the clicked row itself.
     */
    public function test_a_locked_combined_group_sibling_blocks_the_whole_group_clear(): void
    {
        $admin = $this->makeAdmin();
        $otherClass = SchoolClass::create(['name' => 'Combined Sibling Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined', 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $this->class->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $otherClass->id]);

        $clickedSlot = $this->makeSlot(['combined_class_group_id' => $group->id, 'is_locked' => false]);
        $lockedSibling = TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'combined_class_group_id' => $group->id,
            'is_locked' => true,
        ]);

        $response = $this->actingAs($admin)->delete(route('timetable.destroy', $clickedSlot->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('timetable_slots', ['id' => $clickedSlot->id]);
        $this->assertDatabaseHas('timetable_slots', ['id' => $lockedSibling->id]);
    }

    /**
     * Item 2 audit finding: lockSlot() rejects archived rows outright, but
     * an archived+locked row is still reachable in practice --
     * publishGeneration()'s bulk archive of a class's previously-published
     * slots has no is_locked check the way the single-slot publish() path
     * does. update()/destroy()/publish() all tell the caller to "unlock it
     * first" when they hit a locked row, so unlockSlot() must remain
     * available for EVERY status -- mirroring lockSlot()'s archived guard
     * onto unlockSlot() would permanently strand any row that reaches this
     * state, for no actual benefit (is_locked has no effect on an archived
     * row's behaviour anywhere else in the codebase).
     */
    public function test_an_archived_locked_slot_can_still_be_unlocked(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['status' => 'archived', 'is_locked' => true]);

        $response = $this->actingAs($admin)->post(route('timetable.unlock', $slot));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($slot->fresh()->is_locked);
    }

    /**
     * Same reasoning as above for the combined-group case -- lockSlot()
     * itself can never create a combined+locked row, but if one ever
     * arises through another path, unlockSlot() must still be able to
     * undo it rather than stranding it behind destroy()'s "unlock it
     * first" check with no way to satisfy that check.
     */
    public function test_a_combined_group_locked_slot_can_still_be_unlocked(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2028', 'code' => '2026-2028-lock', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined Unlock', 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $this->class->id]);
        $slot = $this->makeSlot(['combined_class_group_id' => $group->id, 'is_locked' => true]);

        $response = $this->actingAs($admin)->post(route('timetable.unlock', $slot));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertFalse($slot->fresh()->is_locked);
    }
}
