<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\ParentModel;
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
 * Phase C of Dependency Resolution: deactivate (is_active=false) as an
 * alternative to delete. This suite never touches the real LKG/ANJALI
 * fixture (BellTiming id=2 / TimetableSlot id=7) -- every test builds
 * its own isolated BellTiming/dependency records.
 */
class BellTimingDeactivateTest extends TestCase
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
        $schoolClass = SchoolClass::create(['name' => "Deactivate Test Class $suffix", 'class_order' => 982, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Deactivate Test Subject $suffix", 'code' => "DA-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Deactivate Test Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // 1-4. Authorization
    // ============================================================

    public function test_admin_can_deactivate_active_bell_timing(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($this->admin())->post(route('bell-timing.deactivate', $bellTiming));

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionHas('success');
        $bellTiming->refresh();
        $this->assertFalse($bellTiming->is_active);
    }

    public function test_teacher_cannot_deactivate(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($this->teacherUser())->post(route('bell-timing.deactivate', $bellTiming));

        $response->assertForbidden();
        $bellTiming->refresh();
        $this->assertTrue($bellTiming->is_active);
    }

    public function test_parent_cannot_deactivate(): void
    {
        $bellTiming = $this->makeBellTiming();
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887750',
            'email' => 'deactivate.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->post(route('bell-timing.deactivate', $bellTiming));

        $response->assertStatus(302);
        $bellTiming->refresh();
        $this->assertTrue($bellTiming->is_active);
    }

    public function test_unauthenticated_user_cannot_deactivate(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->post(route('bell-timing.deactivate', $bellTiming));

        $response->assertStatus(302);
        $bellTiming->refresh();
        $this->assertTrue($bellTiming->is_active);
    }

    public function test_teacher_cannot_view_confirm_screen(): void
    {
        $bellTiming = $this->makeBellTiming();

        $this->actingAs($this->teacherUser())->get(route('bell-timing.deactivate.confirm', $bellTiming))->assertForbidden();
    }

    public function test_parent_cannot_view_confirm_screen(): void
    {
        $bellTiming = $this->makeBellTiming();
        $parent = ParentModel::create([
            'name' => 'Test Parent 2',
            'phone' => '9998887751',
            'email' => 'deactivate.parent2.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $this->get(route('bell-timing.deactivate.confirm', $bellTiming))->assertStatus(302);
    }

    public function test_unauthenticated_user_cannot_view_confirm_screen(): void
    {
        $bellTiming = $this->makeBellTiming();

        $this->get(route('bell-timing.deactivate.confirm', $bellTiming))->assertStatus(302);
    }

    // ============================================================
    // 5. Becomes inactive
    // ============================================================

    public function test_bell_timing_becomes_inactive(): void
    {
        $bellTiming = $this->makeBellTiming();
        $this->assertTrue($bellTiming->is_active);

        $this->actingAs($this->admin())->post(route('bell-timing.deactivate', $bellTiming));

        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id, 'is_active' => false]);
    }

    // ============================================================
    // 6-8. Existing dependencies preserved, not deleted
    // ============================================================

    public function test_existing_timetable_dependency_remains_intact(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('SLOT');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id, 'bell_timing_id' => $bellTiming->id]);
        $bellTiming->refresh();
        $this->assertFalse($bellTiming->is_active);
    }

    public function test_existing_substitution_dependency_remains_intact(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('SUB');
        $sub = TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $bellTiming->id, 'created_by' => $admin->id, 'status' => 'approved',
        ]);

        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $this->assertDatabaseHas('teacher_substitutions', ['id' => $sub->id, 'bell_timing_id' => $bellTiming->id, 'status' => 'approved']);
    }

    public function test_existing_availability_dependency_remains_intact(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [, , , $teacher] = $this->makeFixtures('AVAIL');
        $avail = TeacherAvailability::create([
            'teacher_id' => $teacher->id, 'bell_timing_id' => $bellTiming->id, 'is_available' => false,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $this->assertDatabaseHas('teacher_availabilities', ['id' => $avail->id, 'bell_timing_id' => $bellTiming->id]);
    }

    // ============================================================
    // 9-10. Inactive Bell Timing not offered for new work
    // ============================================================

    public function test_inactive_bell_timing_not_offered_for_new_timetable_creation(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming(['period_name' => 'Deactivate Dropdown Test', 'academic_year' => '2026-2027']);
        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $response = $this->actingAs($admin)->get(route('timetable.index'));

        $response->assertOk();
        $bellTimings = $response->viewData('bellTimings');
        $this->assertFalse($bellTimings->contains('id', $bellTiming->id));
    }

    public function test_inactive_bell_timing_not_offered_as_reassignment_target(): void
    {
        $admin = $this->admin();
        $blockedBellTiming = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        $inactiveTarget = $this->makeBellTiming(['period_name' => 'Deactivated Target', 'start_time' => '09:11', 'end_time' => '09:50', 'order_index' => 3]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('TARGET');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedBellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.deactivate', $inactiveTarget));

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$blockedBellTiming, $slot]));

        $response->assertOk();
        $response->assertDontSee('Deactivated Target', false);
    }

    // ============================================================
    // 11. Historical display still works
    // ============================================================

    public function test_historical_timetable_continues_displaying_the_inactive_bell_timing(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('HIST');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        // The slot's own relationship to its (now inactive) Bell Timing
        // must still resolve normally -- deactivating never breaks the
        // FK or hides the record from its own historical display.
        $slot->refresh();
        $this->assertNotNull($slot->bellTiming);
        $this->assertSame($bellTiming->id, $slot->bellTiming->id);
        $this->assertSame('Period 1', $slot->bellTiming->period_name);
    }

    // ============================================================
    // 12. Template functionality intact
    // ============================================================

    public function test_template_dependency_blocking_still_works_for_inactive_bell_timings(): void
    {
        // Deactivating doesn't change FK-based dependency blocking --
        // Template Replace's excess-row removal still respects real
        // dependencies exactly as before, regardless of is_active.
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('TPL');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $checker = app(BellTimingDependencyChecker::class);
        $dependencies = $checker->check($bellTiming->id);
        $this->assertTrue($checker->isBlocked($dependencies), 'Dependency blocking must be unaffected by is_active.');
    }

    // ============================================================
    // 13-14. Bulk Edit / Bulk Delete unaffected
    // ============================================================

    public function test_inactive_bell_timing_still_appears_and_is_manageable_in_bulk_delete_selection(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming(['class_section' => 'Deactivate Bulk Class', 'academic_year' => '2026-2027', 'semester' => 'First']);
        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $response = $this->actingAs($admin)->get(route('bell-timing.bulk-delete'));

        $response->assertOk();
        $response->assertSee('Deactivate Bulk Class', false);
    }

    public function test_inactive_bell_timing_still_appears_and_is_manageable_in_bulk_edit_selection(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming(['class_section' => 'Deactivate Bulk Edit Class', 'academic_year' => '2026-2027', 'semester' => 'First']);
        $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $response = $this->actingAs($admin)->get(route('bell-timing.bulk-edit'));

        $response->assertOk();
        $response->assertSee('Deactivate Bulk Edit Class', false);
    }

    // ============================================================
    // 15-16. Idempotency / stale request safety
    // ============================================================

    public function test_repeated_deactivation_is_a_safe_no_op(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();

        $first = $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));
        $first->assertSessionHas('success');

        $second = $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));
        $second->assertSessionHas('success');
        $this->assertStringContainsString('already inactive', session('success'));

        $bellTiming->refresh();
        $this->assertFalse($bellTiming->is_active);
    }

    public function test_stale_confirmation_page_after_concurrent_deactivation_is_handled_safely(): void
    {
        // Admin A loads the confirm page (bellTiming still active at that
        // moment); before A submits, Admin B deactivates it directly.
        // A's stale submission must not error or double-process.
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();

        $confirmPage = $this->actingAs($admin)->get(route('bell-timing.deactivate.confirm', $bellTiming));
        $confirmPage->assertOk();

        // Simulate the concurrent deactivation.
        $bellTiming->update(['is_active' => false]);

        $staleSubmit = $this->actingAs($admin)->post(route('bell-timing.deactivate', $bellTiming));

        $staleSubmit->assertRedirect(route('bell-timing.index'));
        $staleSubmit->assertSessionHas('success');
        $bellTiming->refresh();
        $this->assertFalse($bellTiming->is_active);
    }

    // ============================================================
    // 17-18. Existing delete/reassign behavior unchanged
    // ============================================================

    public function test_delete_behavior_remains_unchanged_for_a_blocked_bell_timing(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('DELUNCH');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.delete.confirm', $bellTiming));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_reassign_behavior_remains_unchanged(): void
    {
        $admin = $this->admin();
        $bellTimingOld = $this->makeBellTiming();
        $bellTimingNew = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('REASSIGNUNCH');
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $bellTimingOld->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $slot->school_class_id,
            'section_id' => $slot->section_id,
            'bell_timing_id' => $bellTimingNew->id,
            'subject_id' => $slot->subject_id,
            'teacher_id' => $slot->teacher_id,
            'co_teacher_id' => $slot->co_teacher_id,
            'room_number' => $slot->room_number,
        ]);

        $response->assertSessionHas('success');
        $slot->refresh();
        $this->assertSame($bellTimingNew->id, $slot->bell_timing_id);
    }
}
