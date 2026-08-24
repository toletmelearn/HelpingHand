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
 * Phase D of Dependency Resolution: Bulk Delete preview now shows actual
 * dependency identities (not just counts) and links out to the existing
 * Phase A (View Dependencies), Phase B (Reassign), and Phase C
 * (Deactivate) screens -- all reused unmodified. bulkDeleteConfirm()'s
 * own re-check/re-resolve/transactional-delete logic is unchanged by this
 * phase; these tests mostly prove that claim rather than exercise new
 * write logic, since the only new write-adjacent addition here is the
 * blocked-detail attached to the preview response, which is read-only.
 */
class BellTimingBulkDeleteResolutionTest extends TestCase
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

    private function seedSchedule(string $classSection, string $day, int $periodCount, array $overrides = []): void
    {
        $start = 8 * 60;
        for ($i = 1; $i <= $periodCount; $i++) {
            $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $start += 40;
            $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            BellTiming::create(array_merge([
                'day_of_week' => $day, 'period_name' => "Period $i",
                'start_time' => $s, 'end_time' => $e, 'class_section' => $classSection,
                'is_active' => true, 'is_break' => false, 'order_index' => $i,
                'academic_year' => '2026-2027', 'semester' => 'First',
            ], $overrides));
        }
    }

    private function selectionGroups(array $selections): array
    {
        $groups = [];
        foreach ($selections as $i => $sel) {
            $groups[$i] = array_merge(['selected' => '1'], $sel);
        }

        return ['groups' => $groups];
    }

    /** @return array{0: SchoolClass, 1: Section, 2: Subject, 3: Teacher} */
    private function makeFixtures(string $suffix = ''): array
    {
        $schoolClass = SchoolClass::create(['name' => "Bulk Resolution Class $suffix", 'class_order' => 983, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Bulk Resolution Subject $suffix", 'code' => "BR-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Bulk Resolution Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // 1-2. Preview separates safe/blocked and shows real identities
    // ============================================================

    public function test_preview_separates_safe_and_blocked_with_new_wording(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R1', 'Monday', 3);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('SEP');
        $blockedRow = BellTiming::where('class_section', 'Class R1')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R1', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertViewHas('safeCount', 2);
        $blocked = $response->viewData('blocked');
        $this->assertCount(1, $blocked);
        $response->assertSee('ready to delete', false);
        $response->assertSee('requires attention', false);
    }

    public function test_blocked_records_show_actual_dependency_identities_not_just_counts(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R2', 'Monday', 3);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('IDENT');
        $blockedRow = BellTiming::where('class_section', 'Class R2')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R2', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $blocked = $response->viewData('blocked');
        $this->assertSame($schoolClass->name, $blocked[0]['detail']['timetable_slots'][0]['class_name']);
        $this->assertSame($subject->name, $blocked[0]['detail']['timetable_slots'][0]['subject_name']);
        $this->assertSame($teacher->name, $blocked[0]['detail']['timetable_slots'][0]['teacher_name']);
        $response->assertSee($teacher->name, false);
        $response->assertSee($subject->name, false);
    }

    // ============================================================
    // 3-5. View Dependencies / Reassign / Deactivate reachable from Bulk
    // ============================================================

    public function test_view_dependencies_link_is_present_and_functional(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R3', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('VIEW');
        $blockedRow = BellTiming::where('class_section', 'Class R3')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $preview = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $preview->assertSee(route('bell-timing.dependencies', $blockedRow), false);

        $viewDependencies = $this->actingAs($admin)->get(route('bell-timing.dependencies', $blockedRow));
        $viewDependencies->assertOk();
    }

    public function test_reassign_link_is_present_and_reassignment_succeeds_from_bulk_context(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R4', 'Monday', 2);
        $target = $this->seedTargetBellTiming();
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('REASS');
        $blockedRow = BellTiming::where('class_section', 'Class R4')->orderBy('order_index')->first();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $preview = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $preview->assertSee(route('bell-timing.dependencies.reassign-slot', [$blockedRow, $slot]), false);

        // Reuses Phase B's existing endpoint exactly -- no new write path.
        $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $slot->school_class_id, 'section_id' => $slot->section_id,
            'bell_timing_id' => $target->id, 'subject_id' => $slot->subject_id,
            'teacher_id' => $slot->teacher_id, 'co_teacher_id' => $slot->co_teacher_id,
            'room_number' => $slot->room_number,
        ]);

        $slot->refresh();
        $this->assertSame($target->id, $slot->bell_timing_id);
    }

    public function test_deactivate_link_is_present_and_deactivation_succeeds_from_bulk_context(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R5', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('DEACT');
        $blockedRow = BellTiming::where('class_section', 'Class R5')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $preview = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R5', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $preview->assertSee(route('bell-timing.deactivate.confirm', $blockedRow), false);

        // Reuses Phase C's existing endpoint exactly -- no new write path.
        $this->actingAs($admin)->post(route('bell-timing.deactivate', $blockedRow));

        $blockedRow->refresh();
        $this->assertFalse($blockedRow->is_active);
        $this->assertDatabaseHas('timetable_slots', ['bell_timing_id' => $blockedRow->id]);
    }

    // ============================================================
    // 6. Skip leaves the Bell Timing untouched
    // ============================================================

    public function test_skip_performs_no_server_action_record_remains_untouched(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R6', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('SKIP');
        $blockedRow = BellTiming::where('class_section', 'Class R6')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        $beforeUpdatedAt = $blockedRow->updated_at;

        // "Skip" is a pure client-side (JS-only) visual affordance in the
        // new preview view -- it has no server route at all, so simply
        // previewing (without acting) already proves the record is
        // untouched; there is nothing else to invoke.
        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R6', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $blockedRow->refresh();
        $this->assertEquals($beforeUpdatedAt, $blockedRow->updated_at);
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertDatabaseHas('timetable_slots', ['bell_timing_id' => $blockedRow->id]);
    }

    // ============================================================
    // 7-9. Safe deleted, blocked never deleted, mixed selection, new wording
    // ============================================================

    public function test_mixed_selection_deletes_safe_and_protects_blocked_with_new_message_wording(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R7', 'Monday', 3); // all safe
        $this->seedSchedule('Class R8', 'Tuesday', 2); // one blocked
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('MIX');
        $blockedRow = BellTiming::where('class_section', 'Class R8')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R7', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class R8', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertSessionHas('success');
        $this->assertStringContainsString('4 Bell Timing(s) deleted', session('success'));
        $this->assertStringContainsString('1 Bell Timing(s) were protected because dependencies were detected', session('success'));
        $this->assertSame(0, BellTiming::where('class_section', 'Class R7')->count());
        $this->assertSame(1, BellTiming::where('class_section', 'Class R8')->count());
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    // ============================================================
    // 10-11. New/concurrent dependency after preview protects the record
    // ============================================================

    public function test_dependency_appearing_after_preview_protects_the_record_and_does_not_lose_others(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R9', 'Monday', 3);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class R9', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $preview = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $selection);
        $preview->assertViewHas('safeCount', 3);

        // A dependency appears in the gap between preview and confirm.
        [, , , $teacher] = $this->makeFixtures('LATE');
        $nowBlockedRow = BellTiming::where('class_section', 'Class R9')->orderBy('order_index')->first();
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $nowBlockedRow->id, 'is_available' => false]);

        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $selection);

        $confirm->assertSessionHas('success');
        $this->assertStringContainsString('2 Bell Timing(s) deleted', session('success'));
        $this->assertStringContainsString('1 Bell Timing(s) were protected because dependencies were detected', session('success'));
        $this->assertSame(1, BellTiming::where('class_section', 'Class R9')->count(), 'confirm() must re-check, not trust the earlier preview -- the other 2 must still be deleted.');
        $this->assertDatabaseHas('bell_timings', ['id' => $nowBlockedRow->id]);
    }

    // ============================================================
    // 12. Invalid/stale IDs cannot bypass checks
    // ============================================================

    public function test_crafted_ids_and_fabricated_selection_cannot_bypass_dependency_checks(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R10', 'Monday', 3);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('CRAFT');
        $blockedRow = BellTiming::where('class_section', 'Class R10')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $payload = $this->selectionGroups([
            ['class_section' => 'Class R10', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);
        // Selection is always by tuple, never a raw id list -- crafted ids
        // in the request are never even read.
        $payload['bell_timing_ids'] = [$blockedRow->id];
        $payload['skip_dependency_check'] = '1';
        $payload['force'] = 'true';

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $payload);

        // The blocked row must survive; the request's crafted extra
        // fields (bell_timing_ids, skip_dependency_check, force) must be
        // silently ignored, not cause an over- or under-deletion -- only
        // the 2 genuinely safe rows in this 3-row schedule are removed.
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertSame(1, BellTiming::where('class_section', 'Class R10')->count());
    }

    // ============================================================
    // 13-14. Authorization -- bulk delete and bulk resolve actions alike
    // ============================================================

    public function test_teacher_cannot_bulk_delete_or_resolve(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R11', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('AUTHZ');
        $blockedRow = BellTiming::where('class_section', 'Class R11')->orderBy('order_index')->first();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class R11', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $teacherUser = $this->teacherUser();
        $this->actingAs($teacherUser)->post(route('bell-timing.bulk-delete.preview'), $selection)->assertForbidden();
        $this->actingAs($teacherUser)->post(route('bell-timing.bulk-delete.confirm'), $selection)->assertForbidden();
        $this->actingAs($teacherUser)->get(route('bell-timing.dependencies', $blockedRow))->assertForbidden();
        $this->actingAs($teacherUser)->get(route('bell-timing.dependencies.reassign-slot', [$blockedRow, $slot]))->assertForbidden();
        $this->actingAs($teacherUser)->get(route('bell-timing.deactivate.confirm', $blockedRow))->assertForbidden();
        $this->actingAs($teacherUser)->post(route('bell-timing.deactivate', $blockedRow))->assertForbidden();

        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertTrue($blockedRow->fresh()->is_active);
    }

    public function test_unauthenticated_and_parent_cannot_bulk_delete(): void
    {
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'Class R12', 'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class R12', 'day_of_week' => 'Monday', 'academic_year' => null, 'semester' => null],
        ]);

        $this->post(route('bell-timing.bulk-delete.preview'), $selection)->assertStatus(302);
        $this->post(route('bell-timing.bulk-delete.confirm'), $selection)->assertStatus(302);

        $parent = ParentModel::create([
            'name' => 'Test Parent', 'phone' => '9998887740',
            'email' => 'bulkd.parent.' . uniqid() . '@example.com', 'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);
        $this->post(route('bell-timing.bulk-delete.confirm'), $selection)->assertStatus(302);

        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    // ============================================================
    // 15-17. Published / archived / locked remain protected in Bulk Delete
    // ============================================================

    public function test_published_timetable_remains_protected_in_bulk_delete(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R13', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('PUB');
        $blockedRow = BellTiming::where('class_section', 'Class R13')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R13', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertDatabaseHas('timetable_slots', ['bell_timing_id' => $blockedRow->id, 'status' => 'published']);
    }

    public function test_archived_timetable_remains_protected_in_bulk_delete(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R14', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('ARCH');
        $blockedRow = BellTiming::where('class_section', 'Class R14')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_ARCHIVED,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R14', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $blocked = $response->viewData('blocked');
        $this->assertCount(1, $blocked);
        $this->assertFalse($blocked[0]['detail']['timetable_slots'][0]['reassignable']);
        $response->assertDontSee(route('bell-timing.dependencies.reassign-slot', [$blockedRow, $blocked[0]['detail']['timetable_slots'][0]['id']]), false);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R14', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    public function test_locked_timetable_remains_protected_in_bulk_delete(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R15', 'Monday', 2);
        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('LOCK');
        $blockedRow = BellTiming::where('class_section', 'Class R15')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT, 'is_locked' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R15', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $blocked = $response->viewData('blocked');
        $this->assertFalse($blocked[0]['detail']['timetable_slots'][0]['reassignable']);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R15', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    // ============================================================
    // 20. Deactivated Bell Timing behavior in Bulk Delete
    // ============================================================

    public function test_deactivated_bell_timing_still_classified_correctly_in_bulk_preview(): void
    {
        // An inactive Bell Timing is still visible/selectable in Bulk
        // Delete (no is_active filter in bulkDeleteGroups()), and its
        // safe/blocked classification is governed purely by
        // BellTimingDependencyChecker (FK existence), never by is_active.
        $admin = $this->admin();
        $this->seedSchedule('Class R16', 'Monday', 2);
        $safeRow = BellTiming::where('class_section', 'Class R16')->where('period_name', 'Period 1')->first();
        $safeRow->update(['is_active' => false]);

        [$schoolClass, , $subject, $teacher] = $this->makeFixtures('INACTBLK');
        $blockedRow = BellTiming::where('class_section', 'Class R16')->where('period_name', 'Period 2')->first();
        $blockedRow->update(['is_active' => false]);
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class R16', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertViewHas('safeCount', 1);
        $blocked = $response->viewData('blocked');
        $this->assertCount(1, $blocked);
        $this->assertSame($blockedRow->id, $blocked[0]['bellTiming']->id);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R16', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));
        // The inactive-but-safe row was deleted; the inactive-and-blocked
        // row was protected -- is_active had no bearing on either outcome.
        $this->assertDatabaseMissing('bell_timings', ['id' => $safeRow->id]);
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    // ============================================================
    // 21. Deletion is atomic
    // ============================================================

    public function test_multi_record_safe_deletion_is_all_or_nothing(): void
    {
        // bulkDeleteConfirm() deletes all safe ids in a single
        // whereIn(...)->delete() statement inside one DB::transaction() --
        // atomic by construction (a single SQL statement can't partially
        // apply). This proves the observable guarantee: after confirming
        // a multi-record safe selection, every one of them is gone, never
        // some-but-not-all.
        $admin = $this->admin();
        $this->seedSchedule('Class R17', 'Monday', 5);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R17', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $this->assertSame(0, BellTiming::where('class_section', 'Class R17')->count());
    }

    // ============================================================
    // 22. No dependent record is ever deleted by Bulk Delete
    // ============================================================

    public function test_no_dependent_record_is_ever_deleted_by_bulk_delete(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class R18', 'Monday', 1);
        $this->seedSchedule('Class R18', 'Tuesday', 1);
        $this->seedSchedule('Class R18', 'Wednesday', 1);
        [$schoolClass, $section, $subject, $teacher] = $this->makeFixtures('NODEP');

        $slotRow = BellTiming::where('class_section', 'Class R18')->where('day_of_week', 'Monday')->first();
        $slot = TimetableSlot::create([
            'school_class_id' => $schoolClass->id, 'bell_timing_id' => $slotRow->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027', 'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $subRow = BellTiming::where('class_section', 'Class R18')->where('day_of_week', 'Tuesday')->first();
        $sub = TeacherSubstitution::create([
            'substitution_date' => '2026-08-10', 'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $subRow->id, 'created_by' => $admin->id, 'status' => 'approved',
        ]);

        $availRow = BellTiming::where('class_section', 'Class R18')->where('day_of_week', 'Wednesday')->first();
        $avail = TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $availRow->id, 'is_available' => false]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class R18', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class R18', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class R18', 'day_of_week' => 'Wednesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id]);
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $sub->id]);
        $this->assertDatabaseHas('teacher_availabilities', ['id' => $avail->id]);
        $this->assertSame(3, BellTiming::where('class_section', 'Class R18')->count());
    }

    private function seedTargetBellTiming(): BellTiming
    {
        return BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Reassign Target', 'start_time' => '14:00', 'end_time' => '14:40',
            'class_section' => null, 'is_active' => true, 'is_break' => false, 'order_index' => 90,
        ]);
    }
}
