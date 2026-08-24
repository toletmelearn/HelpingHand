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
 * Phase A of Dependency Resolution: the read-only dependency-detail screen
 * (GET bell-timing/{id}/dependencies) and BellTimingDependencyChecker::describe().
 * Purely additive -- check()/checkEach()/isBlocked()/summarize() and every
 * existing caller (destroy(), Bulk Delete, Bulk Edit, Template Replace)
 * must keep behaving exactly as before; that regression coverage already
 * exists in BellTimingDeleteSafetyTest/BellTimingBulkDeleteTest/
 * BellTimingBulkEditTest/BellTimingTemplateTest and is not duplicated here.
 */
class BellTimingDependencyDetailTest extends TestCase
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
        $schoolClass = SchoolClass::create(['name' => "Dependency Detail Class $suffix", 'class_order' => 980, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Dependency Detail Subject $suffix", 'code' => "DD-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Dependency Detail Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // Screen: authorization
    // ============================================================

    public function test_admin_can_view_dependency_detail_screen(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertOk();
    }

    public function test_teacher_cannot_view_dependency_detail_screen(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($this->teacherUser())->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertForbidden();
    }

    public function test_parent_guard_user_cannot_view_dependency_detail_screen(): void
    {
        $bellTiming = $this->makeBellTiming();
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887760',
            'email' => 'dependency.detail.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertStatus(302);
    }

    public function test_unauthenticated_user_cannot_view_dependency_detail_screen(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertStatus(302);
    }

    // ============================================================
    // Screen: no dependencies
    // ============================================================

    public function test_screen_shows_safe_to_delete_when_no_dependencies(): void
    {
        $bellTiming = $this->makeBellTiming();

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertOk();
        $response->assertViewHas('blocked', false);
        $response->assertSee('no dependencies', false);
    }

    // ============================================================
    // Screen: draft / published / archived / locked distinction
    // ============================================================

    public function test_draft_timetable_slot_shown_with_full_identity_and_reassignable(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('DRAFT');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertOk();
        $response->assertViewHas('blocked', true);
        $detail = $response->viewData('detail');
        $this->assertCount(1, $detail['timetable_slots']);
        $this->assertSame($schoolClass->name, $detail['timetable_slots'][0]['class_name']);
        $this->assertSame($subject->name, $detail['timetable_slots'][0]['subject_name']);
        $this->assertSame($teacher->name, $detail['timetable_slots'][0]['teacher_name']);
        $this->assertSame('draft', $detail['timetable_slots'][0]['status']);
        $this->assertTrue($detail['timetable_slots'][0]['reassignable']);
        $response->assertSee($schoolClass->name, false);
        $response->assertSee($teacher->name, false);
    }

    public function test_published_timetable_slot_shown_and_flagged_reassignable_with_warning(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('PUB');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $detail = $response->viewData('detail');
        $this->assertSame('published', $detail['timetable_slots'][0]['status']);
        // Published is not archived and not locked -- still reassignable,
        // just requires the UI's extra confirmation (a later phase), not a
        // hard block here.
        $this->assertTrue($detail['timetable_slots'][0]['reassignable']);
        $response->assertSee('published', false);
        $response->assertSee('currently visible', false);
    }

    public function test_archived_timetable_slot_marked_not_reassignable(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ARCH');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $detail = $response->viewData('detail');
        $this->assertSame('archived', $detail['timetable_slots'][0]['status']);
        $this->assertFalse($detail['timetable_slots'][0]['reassignable']);
        $response->assertSee('cannot be edited', false);
    }

    public function test_locked_timetable_slot_marked_not_reassignable(): void
    {
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('LOCK');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
            'is_locked' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $detail = $response->viewData('detail');
        $this->assertTrue($detail['timetable_slots'][0]['is_locked']);
        $this->assertFalse($detail['timetable_slots'][0]['reassignable']);
    }

    // ============================================================
    // Screen: substitution / availability identity
    // ============================================================

    public function test_teacher_substitution_shown_with_full_identity(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('SUB');
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-10',
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies', $bellTiming));

        $detail = $response->viewData('detail');
        $this->assertCount(1, $detail['teacher_substitutions']);
        $this->assertSame('2026-08-10', $detail['teacher_substitutions'][0]['substitution_date']);
        $this->assertSame($teacher->name, $detail['teacher_substitutions'][0]['absent_teacher_name']);
        $this->assertSame($schoolClass->name, $detail['teacher_substitutions'][0]['class_name']);
        $this->assertSame($subject->name, $detail['teacher_substitutions'][0]['subject_name']);
        $this->assertSame('approved', $detail['teacher_substitutions'][0]['status']);
        $response->assertSee($teacher->name, false);
    }

    public function test_cancelled_substitution_still_shown_as_a_dependency(): void
    {
        // Locks in the finding from the design phase: a merely-cancelled
        // substitution still counts (BellTimingDependencyChecker performs
        // no status filter) -- describe() must reflect that faithfully,
        // not hide cancelled rows as if they were already resolved.
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('CANC');
        TeacherSubstitution::create([
            'substitution_date' => '2026-08-10',
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $bellTiming->id,
            'created_by' => $admin->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.dependencies', $bellTiming));

        $response->assertViewHas('blocked', true);
        $detail = $response->viewData('detail');
        $this->assertCount(1, $detail['teacher_substitutions']);
        $this->assertSame('cancelled', $detail['teacher_substitutions'][0]['status']);
    }

    public function test_teacher_availability_shown_with_teacher_identity(): void
    {
        $bellTiming = $this->makeBellTiming();
        [, , , $teacher] = $this->makeFixtures('AVAIL');
        TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $bellTiming->id,
            'is_available' => false,
        ]);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.dependencies', $bellTiming));

        $detail = $response->viewData('detail');
        $this->assertCount(1, $detail['teacher_availabilities']);
        $this->assertSame($teacher->name, $detail['teacher_availabilities'][0]['teacher_name']);
        $response->assertSee($teacher->name, false);
    }

    // ============================================================
    // Link from the existing blocked confirm-delete screen
    // ============================================================

    public function test_blocked_confirm_delete_screen_links_to_dependency_detail(): void
    {
        $admin = $this->admin();
        $bellTiming = $this->makeBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('LINK');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTiming->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.delete.confirm', $bellTiming));

        $response->assertOk();
        $response->assertSee(route('bell-timing.dependencies', $bellTiming), false);
    }

    // ============================================================
    // BellTimingDependencyChecker::describe() -- direct service tests
    // ============================================================

    public function test_describe_returns_empty_arrays_for_bell_timing_with_no_dependencies(): void
    {
        $bellTiming = $this->makeBellTiming();

        $result = app(BellTimingDependencyChecker::class)->describe([$bellTiming->id]);

        $this->assertSame([], $result[$bellTiming->id]['timetable_slots']);
        $this->assertSame([], $result[$bellTiming->id]['teacher_substitutions']);
        $this->assertSame([], $result[$bellTiming->id]['teacher_availabilities']);
    }

    public function test_describe_isolates_records_per_bell_timing_id(): void
    {
        $bellTimingA = $this->makeBellTiming(['period_name' => 'Period 1']);
        $bellTimingB = $this->makeBellTiming(['period_name' => 'Period 2', 'start_time' => '08:31', 'end_time' => '09:10', 'order_index' => 2]);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ISOL');
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $bellTimingA->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $result = app(BellTimingDependencyChecker::class)->describe([$bellTimingA->id, $bellTimingB->id]);

        $this->assertCount(1, $result[$bellTimingA->id]['timetable_slots']);
        $this->assertCount(0, $result[$bellTimingB->id]['timetable_slots']);
    }

    public function test_describe_returns_empty_array_for_empty_input(): void
    {
        $result = app(BellTimingDependencyChecker::class)->describe([]);

        $this->assertSame([], $result);
    }
}
