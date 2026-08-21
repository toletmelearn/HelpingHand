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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 regression coverage for the BellTiming delete-safety fix.
 * BellTimingController::destroy() used to call $bellTiming->delete()
 * unconditionally -- timetable_slots.bell_timing_id and
 * teacher_availabilities.bell_timing_id both cascade on delete (silently
 * destroying dependent rows, including published timetable slots), while
 * teacher_substitutions.bell_timing_id has no cascade at all (a delete
 * would throw a raw, unhandled QueryException). These tests exercise the
 * real HTTP routes/controller/policy together, using isolated fixture
 * data created and torn down entirely within each test's own transaction
 * (RefreshDatabase) -- no real/existing records are touched.
 */
class BellTimingDeleteSafetyTest extends TestCase
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
            'day_of_week' => 'Tuesday',
            'period_name' => 'Period 3',
            'start_time' => '10:15',
            'end_time' => '11:00',
            'class_section' => 'Class 5',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 3,
        ], $overrides));
    }

    /** @return array{0: SchoolClass, 1: Section, 2: Subject, 3: Teacher} */
    private function makeTimetableFixtures(): array
    {
        $schoolClass = SchoolClass::create(['name' => 'UAT Delete Safety Class', 'class_order' => 999, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => 'UAT Subject', 'code' => 'UAT-DEL', 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'UAT Delete Safety Teacher', 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // A. Successful deletion (no dependencies)
    // ============================================================

    public function test_admin_can_delete_bell_timing_with_no_dependencies(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_successful_deletion_removes_only_the_intended_bell_timing(): void
    {
        $admin = $this->admin();
        $target = $this->makeBellTiming(['period_name' => 'Target Period']);
        $other = $this->makeBellTiming(['period_name' => 'Untouched Period', 'order_index' => 4, 'start_time' => '11:00', 'end_time' => '11:40']);

        $this->actingAs($admin)->delete(route('bell-timing.destroy', $target));

        $this->assertDatabaseMissing('bell_timings', ['id' => $target->id]);
        $this->assertDatabaseHas('bell_timings', ['id' => $other->id, 'period_name' => 'Untouched Period']);
    }

    // ============================================================
    // B. Authorization
    // ============================================================

    public function test_teacher_cannot_delete_bell_timing(): void
    {
        $teacher = $this->teacherUser();
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($teacher)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertForbidden();
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_unauthenticated_user_cannot_delete_bell_timing(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertStatus(302);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_parent_guard_user_cannot_delete_bell_timing(): void
    {
        $bellTiming = $this->makeBellTiming();
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887771',
            'email' => 'delete.safety.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->delete(route('bell-timing.destroy', $bellTiming));

        // Not authenticated on the `web` guard the `auth` middleware checks.
        $response->assertStatus(302);
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_teacher_cannot_view_the_delete_confirmation_screen(): void
    {
        $teacher = $this->teacherUser();
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($teacher)->get(route('bell-timing.delete.confirm', $bellTiming));

        $response->assertForbidden();
    }

    // ============================================================
    // C. Blocked deletion -- timetable_slots
    // ============================================================

    public function test_deletion_blocked_when_timetable_slots_reference_it(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeTimetableFixtures();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.delete.confirm', $bellTiming));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id]);
    }

    public function test_deletion_blocked_when_a_published_timetable_slot_references_it(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeTimetableFixtures();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.delete.confirm', $bellTiming));
        $response->assertSessionHas('error');
        $this->assertStringContainsString('published', strtolower(session('error')));
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id, 'status' => 'published']);
    }

    // ============================================================
    // D. Blocked deletion -- teacher_substitutions
    // ============================================================

    public function test_deletion_blocked_when_teacher_substitutions_reference_it(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeTimetableFixtures();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.delete.confirm', $bellTiming));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitution->id]);
    }

    // ============================================================
    // E. Blocked deletion -- teacher_availabilities
    // ============================================================

    public function test_deletion_blocked_when_teacher_availabilities_reference_it(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [, , , $teacher] = $this->makeTimetableFixtures();
        $availability = TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $bellTiming->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.delete.confirm', $bellTiming));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
        $this->assertDatabaseHas('teacher_availabilities', ['id' => $availability->id]);
    }

    // ============================================================
    // F. No raw exceptions / friendly response
    // ============================================================

    public function test_blocked_deletion_returns_a_friendly_redirect_not_a_500(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeTimetableFixtures();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertStatus(302);
        $response->assertSessionDoesntHaveErrors();
        $this->assertStringNotContainsString('SQLSTATE', session('error'));
        $this->assertStringNotContainsString('QueryException', session('error'));
    }

    // ============================================================
    // G. No side effects on a blocked deletion
    // ============================================================

    public function test_blocked_deletion_does_not_modify_the_bell_timing(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming(['period_name' => 'Original Name']);
        [, , , $teacher] = $this->makeTimetableFixtures();
        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $bellTiming->id,
        ]);

        $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $bellTiming->refresh();
        $this->assertSame('Original Name', $bellTiming->period_name);
        $this->assertTrue($bellTiming->is_active);
    }

    public function test_blocked_deletion_does_not_modify_timetable_slots(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeTimetableFixtures();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $slot->refresh();
        $this->assertSame('published', $slot->status);
        $this->assertSame($bellTiming->id, $slot->bell_timing_id);
    }

    public function test_blocked_deletion_does_not_modify_substitutions(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeTimetableFixtures();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->delete(route('bell-timing.destroy', $bellTiming));

        $substitution->refresh();
        $this->assertSame('pending', $substitution->status);
        $this->assertSame($bellTiming->id, $substitution->bell_timing_id);
    }

    // ============================================================
    // H. Confirmation screen content
    // ============================================================

    public function test_confirm_delete_screen_shows_safe_state_when_no_dependencies(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($admin)->get(route('bell-timing.delete.confirm', $bellTiming));

        $response->assertOk();
        $response->assertSee('not currently used', false);
        $response->assertSee('Class 5', false);
        $response->assertSee('Tuesday', false);
    }

    public function test_confirm_delete_screen_shows_blocked_state_with_dependency_counts(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeTimetableFixtures();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.delete.confirm', $bellTiming));

        $response->assertOk();
        $response->assertSee('1 published timetable slot', false);
        $response->assertSee('cannot be deleted', false);
    }
}
