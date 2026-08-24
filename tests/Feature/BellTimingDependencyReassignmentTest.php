<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Services\Timetable\BellTimingDependencyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B of Dependency Resolution: reassignment forms for blocking
 * dependencies. Deliberately thin -- every write in this feature happens
 * through the pre-existing, unmodified timetable.update /
 * teacher-substitutions.update endpoints; this suite exists to prove that
 * routing through them from the new reassign-slot/reassign-substitution
 * screens behaves exactly as using those endpoints directly already does
 * (regression), plus the handful of things genuinely new in Phase B:
 * ownership checks on the slot/substitution id, the archived/locked
 * refusal, and the live post-reassignment recheck.
 */
class BellTimingDependencyReassignmentTest extends TestCase
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

    private function makeBellTiming(array $overrides = []): BellTiming
    {
        return BellTiming::create(array_merge([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '07:51',
            'end_time' => '08:30',
            'class_section' => null,
            'is_active' => true,
            'is_break' => false,
            'order_index' => 1,
        ], $overrides));
    }

    /** @return array{0: SchoolClass, 1: Section, 2: Subject, 3: Teacher} */
    private function makeFixtures(string $suffix = ''): array
    {
        $schoolClass = SchoolClass::create(['name' => "Reassign Test Class $suffix", 'class_order' => 981, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Reassign Test Subject $suffix", 'code' => "RT-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Reassign Test Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    private function slotReassignPayload(TimetableSlot $slot, int $newBellTimingId): array
    {
        return [
            'school_class_id' => $slot->school_class_id,
            'section_id' => $slot->section_id,
            'bell_timing_id' => $newBellTimingId,
            'subject_id' => $slot->subject_id,
            'teacher_id' => $slot->teacher_id,
            'co_teacher_id' => $slot->co_teacher_id,
            'room_number' => $slot->room_number,
        ];
    }

    private function substitutionReassignPayload(TeacherSubstitution $sub, int $newBellTimingId): array
    {
        return [
            'substitution_date' => $sub->substitution_date->toDateString(),
            'absent_teacher_id' => $sub->absent_teacher_id,
            'class_id' => $sub->class_id,
            'section_id' => $sub->section_id,
            'subject_id' => $sub->subject_id,
            'bell_timing_id' => $newBellTimingId,
            'status' => $sub->status,
            'substitute_teacher_id' => $sub->substitute_teacher_id,
            'reason' => $sub->reason,
        ];
    }

    // ============================================================
    // 1. Authorization
    // ============================================================

    public function test_teacher_cannot_open_reassign_slot_form(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('AUTHZ1');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->teacherUser())
            ->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertForbidden();
    }

    public function test_teacher_cannot_open_reassign_substitution_form(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('AUTHZ2');
        $sub = TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $bellTiming->id, 'created_by' => $admin->id, 'status' => 'approved',
        ]);

        $response = $this->actingAs($this->teacherUser())
            ->get(route('bell-timing.dependencies.reassign-substitution', [$bellTiming, $sub]));

        $response->assertForbidden();
    }

    // ============================================================
    // 2-3. Reused endpoint's own validation (unmodified, still enforced)
    // ============================================================

    public function test_missing_replacement_bell_timing_fails_validation(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('MISS');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $payload = $this->slotReassignPayload($slot, 0);
        unset($payload['bell_timing_id']);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), $payload);

        $response->assertSessionHasErrors('bell_timing_id');
        $slot->refresh();
        $this->assertSame($bellTiming->id, $slot->bell_timing_id);
    }

    public function test_invalid_replacement_bell_timing_fails_validation(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('INVAL');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('timetable.update', $slot),
            $this->slotReassignPayload($slot, 999999)
        );

        $response->assertSessionHasErrors('bell_timing_id');
        $slot->refresh();
        $this->assertSame($bellTiming->id, $slot->bell_timing_id);
    }

    // ============================================================
    // 4, 9. Successful draft reassignment clears the dependency
    // ============================================================

    public function test_draft_timetable_reassignment_succeeds_and_clears_dependency(): void
    {
        $admin = $this->admin();
        $bellTimingOld = $this->makeBellTiming();
        $bellTimingNew = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('OK');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTimingOld->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $this->assertTrue(app(BellTimingDependencyChecker::class)->isBlocked(
            app(BellTimingDependencyChecker::class)->check($bellTimingOld->id)
        ));

        $response = $this->actingAs($admin)->patch(
            route('timetable.update', $slot),
            $this->slotReassignPayload($slot, $bellTimingNew->id)
        );

        $response->assertSessionHas('success');
        $slot->refresh();
        $this->assertSame($bellTimingNew->id, $slot->bell_timing_id);
        $this->assertFalse(app(BellTimingDependencyChecker::class)->isBlocked(
            app(BellTimingDependencyChecker::class)->check($bellTimingOld->id)
        ));
    }

    // ============================================================
    // 5. Published requires explicit confirmation (UI-layer safety net)
    // ============================================================

    public function test_published_slot_reassign_form_requires_explicit_confirmation_before_submit(): void
    {
        // The reused timetable.update endpoint is not modified and has no
        // knowledge of a confirmation checkbox -- confirmation here is a
        // deliberate UI-layer safety net on the new reassign-slot screen
        // (a required checkbox that must be checked to enable Save, plus a
        // JS confirm() dialog), not a new server-side rule duplicating
        // TimetableController's own business logic. This test verifies
        // that UI gate renders for a published slot and is absent for a
        // draft one.
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('PUBUI');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertOk();
        $response->assertSee('confirmPublished', false);
        $response->assertSee('disabled', false);
        $response->assertSee('currently visible to students', false);
    }

    public function test_draft_slot_reassign_form_has_no_confirmation_gate(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('DRAFTUI');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertOk();
        $response->assertDontSee('confirmPublished', false);
    }

    // ============================================================
    // 6. Archived cannot be reassigned
    // ============================================================

    public function test_archived_slot_reassign_form_refuses_and_redirects(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ARCHUI');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertRedirect(route('bell-timing.dependencies', $bellTiming));
        $response->assertSessionHas('error');
    }

    public function test_dependency_detail_screen_offers_no_reassign_link_for_archived_slot(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ARCHLINK');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertOk();
        $response->assertDontSee(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]), false);
        $response->assertSee('Not reassignable', false);
    }

    // ============================================================
    // 7. Locked cannot bypass lock protection
    // ============================================================

    public function test_locked_slot_reassign_form_refuses_and_redirects(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('LOCKUI');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT, 'is_locked' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertRedirect(route('bell-timing.dependencies', $bellTiming));
        $response->assertSessionHas('error');
    }

    public function test_locked_slot_direct_update_attempt_is_still_refused_by_the_reused_endpoint(): void
    {
        // Belt-and-suspenders: even bypassing the reassign-slot screen
        // entirely and posting straight to timetable.update, the reused
        // endpoint's own lock check (unmodified) still refuses the edit.
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        $bellTimingNew = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('LOCKPOST');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT, 'is_locked' => true,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('timetable.update', $slot),
            $this->slotReassignPayload($slot, $bellTimingNew->id)
        );

        $response->assertSessionHas('error');
        $slot->refresh();
        $this->assertSame($bellTiming->id, $slot->bell_timing_id);
    }

    // ============================================================
    // 8. Failed reassignment rolls back (genuine scheduling conflict)
    // ============================================================

    public function test_failed_reassignment_due_to_conflict_rolls_back_completely(): void
    {
        $admin = $this->admin();
        $bellTimingOld = $this->makeBellTiming();
        $bellTimingTarget = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ROLLBACK');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTimingOld->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        // The teacher is explicitly blocked at the target Bell Timing --
        // TimetableConflictResolver must reject the reassignment.
        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $bellTimingTarget->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($admin)->patch(
            route('timetable.update', $slot),
            $this->slotReassignPayload($slot, $bellTimingTarget->id)
        );

        $response->assertSessionHas('error');
        $slot->refresh();
        $this->assertSame($bellTimingOld->id, $slot->bell_timing_id, 'The slot must still point at its original Bell Timing -- nothing partially applied.');
        $this->assertTrue(app(BellTimingDependencyChecker::class)->isBlocked(
            app(BellTimingDependencyChecker::class)->check($bellTimingOld->id)
        ), 'The original Bell Timing must still be blocked -- the rejected reassignment must not have cleared it.');
    }

    // ============================================================
    // 10. Remaining dependencies shown after partial resolution
    // ============================================================

    public function test_remaining_dependencies_shown_after_reassigning_only_one_of_two(): void
    {
        $admin = $this->admin();
        $bellTimingOld = $this->makeBellTiming();
        $bellTimingNew = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('PARTIAL');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTimingOld->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $bellTimingOld->id, 'created_by' => $admin->id, 'status' => 'approved',
        ]);

        $this->actingAs($admin)->patch(route('timetable.update', $slot), $this->slotReassignPayload($slot, $bellTimingNew->id));

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies', $bellTimingOld));

        $response->assertOk();
        $response->assertViewHas('blocked', true);
        $detail = $response->viewData('detail');
        $this->assertCount(0, $detail['timetable_slots']);
        $this->assertCount(1, $detail['teacher_substitutions']);
    }

    // ============================================================
    // 11. Browser-supplied IDs cannot bypass ownership/authorization
    // ============================================================

    public function test_slot_belonging_to_a_different_bell_timing_returns_404(): void
    {
        $admin = $this->admin();
        $bellTimingA = $this->makeBellTiming();
        $bellTimingB = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('OWNSLOT');
        $slotOnB = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTimingB->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        // Craft a request pairing bellTimingA's id with a slot that
        // actually belongs to bellTimingB.
        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$bellTimingA, $slotOnB]));

        $response->assertNotFound();
    }

    public function test_substitution_belonging_to_a_different_bell_timing_returns_404(): void
    {
        $admin = $this->admin();
        $bellTimingA = $this->makeBellTiming();
        $bellTimingB = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('OWNSUB');
        $subOnB = TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $bellTimingB->id, 'created_by' => $admin->id, 'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-substitution', [$bellTimingA, $subOnB]));

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_reach_reassign_slot_form(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('UNAUTH');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->get(route('bell-timing.dependencies.reassign-slot', [$bellTiming, $slot]));

        $response->assertStatus(302);
    }

    // ============================================================
    // 15. Cancelled substitution semantics unchanged (still requires
    //     actual reassignment, not just being offered a Reassign link)
    // ============================================================

    public function test_cancelled_substitution_still_blocks_until_actually_reassigned(): void
    {
        $admin = $this->admin();
        $bellTimingOld = $this->makeBellTiming();
        $bellTimingNew = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('CANCUNCH');
        $sub = TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $bellTimingOld->id, 'created_by' => $admin->id, 'status' => 'cancelled',
        ]);

        // Merely viewing the reassign form (not submitting) must not
        // clear the dependency.
        $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-substitution', [$bellTimingOld, $sub]));
        $this->assertTrue(app(BellTimingDependencyChecker::class)->isBlocked(
            app(BellTimingDependencyChecker::class)->check($bellTimingOld->id)
        ));

        // Actually reassigning it does clear it -- proving the semantics
        // are unchanged (cancellation status is irrelevant either way;
        // only the bell_timing_id foreign key matters to the checker).
        $this->actingAs($admin)->put(
            route('admin.teacher-substitutions.update', $sub),
            $this->substitutionReassignPayload($sub, $bellTimingNew->id)
        );
        $sub->refresh();
        $this->assertSame($bellTimingNew->id, $sub->bell_timing_id);
        $this->assertFalse(app(BellTimingDependencyChecker::class)->isBlocked(
            app(BellTimingDependencyChecker::class)->check($bellTimingOld->id)
        ));
    }
}
