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
 * Phase 4 regression coverage for Bulk Delete. Selection is always by
 * (class_section, day_of_week, academic_year, semester) tuples, never by
 * raw BellTiming id -- both bulkDeletePreview() and bulkDeleteConfirm()
 * independently re-resolve the actual ids server-side on every request,
 * and both run the shared BellTimingDependencyChecker (in its new
 * per-id checkEach() form) before anything is shown or deleted.
 */
class BellTimingBulkDeleteTest extends TestCase
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

    private function seedSchedule(string $classSection, string $day, int $periodCount, bool $withRecess = false, array $overrides = []): void
    {
        $order = 0;
        $start = 8 * 60;

        for ($i = 1; $i <= $periodCount; $i++) {
            if ($withRecess && $i === 4) {
                $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
                $start += 20;
                $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
                BellTiming::create(array_merge([
                    'day_of_week' => $day,
                    'period_name' => 'Recess',
                    'start_time' => $s,
                    'end_time' => $e,
                    'class_section' => $classSection,
                    'is_active' => true,
                    'is_break' => true,
                    'order_index' => $order++,
                    'academic_year' => '2026-2027',
                    'semester' => 'First',
                ], $overrides));
            }

            $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $start += 40;
            $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            BellTiming::create(array_merge([
                'day_of_week' => $day,
                'period_name' => "Period $i",
                'start_time' => $s,
                'end_time' => $e,
                'class_section' => $classSection,
                'is_active' => true,
                'is_break' => false,
                'order_index' => $order++,
                'academic_year' => '2026-2027',
                'semester' => 'First',
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
    private function makeDependencyFixtures(string $suffix = ''): array
    {
        $schoolClass = SchoolClass::create(['name' => "Bulk Delete Class $suffix", 'class_order' => 970, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Bulk Delete Subject $suffix", 'code' => "BD-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Bulk Delete Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    // ============================================================
    // 1-4. Authorization
    // ============================================================

    public function test_admin_can_open_bulk_delete_screen(): void
    {
        $this->seedSchedule('Class 3', 'Monday', 9);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.bulk-delete'));

        $response->assertOk();
        $response->assertSee('Class 3', false);
        $response->assertSee('Monday', false);
        $response->assertSee('9 periods', false);
    }

    public function test_teacher_cannot_access_bulk_delete_screen(): void
    {
        $response = $this->actingAs($this->teacherUser())->get(route('bell-timing.bulk-delete'));

        $response->assertForbidden();
    }

    public function test_parent_guard_user_cannot_access_bulk_delete_screen(): void
    {
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887772',
            'email' => 'bulk.delete.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->get(route('bell-timing.bulk-delete'));

        $response->assertStatus(302);
    }

    public function test_unauthenticated_user_cannot_access_bulk_delete_screen(): void
    {
        $response = $this->get(route('bell-timing.bulk-delete'));

        $response->assertStatus(302);
    }

    public function test_teacher_cannot_preview_or_confirm_bulk_delete(): void
    {
        $this->seedSchedule('Class 3', 'Monday', 9);
        $payload = $this->selectionGroups([['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First']]);

        $preview = $this->actingAs($this->teacherUser())->post(route('bell-timing.bulk-delete.preview'), $payload);
        $confirm = $this->actingAs($this->teacherUser())->post(route('bell-timing.bulk-delete.confirm'), $payload);

        $preview->assertForbidden();
        $confirm->assertForbidden();
        $this->assertSame(9, BellTiming::where('class_section', 'Class 3')->count());
    }

    // ============================================================
    // 5-6. Selection resolution
    // ============================================================

    public function test_one_selection_resolves_the_correct_bell_timing_ids(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9);
        $this->seedSchedule('Class 3', 'Tuesday', 9); // not selected -- must be untouched

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertSee('9', false);
        $response->assertViewHas('safeCount', 9);
    }

    public function test_multiple_selections_resolve_correctly(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9);
        $this->seedSchedule('Class 4', 'Monday', 9);
        $this->seedSchedule('Class 5', 'Tuesday', 6);
        $this->seedSchedule('Class 6', 'Wednesday', 8); // not selected

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertViewHas('safeCount', 24); // 9 + 9 + 6
    }

    // ============================================================
    // 7-11. Period count / structure independence
    // ============================================================

    public function test_six_period_schedule_is_correctly_selected_and_deleted(): void
    {
        $this->assertBulkDeleteRemovesWholeSchedule('Class 3', 'Monday', 6);
    }

    public function test_eight_period_schedule_is_correctly_selected_and_deleted(): void
    {
        $this->assertBulkDeleteRemovesWholeSchedule('Class 4', 'Monday', 8);
    }

    public function test_twelve_period_schedule_is_correctly_selected_and_deleted(): void
    {
        $this->assertBulkDeleteRemovesWholeSchedule('Class 9', 'Monday', 12);
    }

    public function test_recess_containing_schedule_is_correctly_selected_and_deleted(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Monday', 8, withRecess: true);
        $this->assertSame(9, BellTiming::where('class_section', 'Class 5')->count()); // 8 periods + 1 recess

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $this->assertSame(0, BellTiming::where('class_section', 'Class 5')->count());
        $this->assertDatabaseMissing('bell_timings', ['class_section' => 'Class 5', 'period_name' => 'Recess']);
    }

    public function test_mixed_class_structures_in_one_selection_all_resolve_correctly(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $this->seedSchedule('Class 4', 'Monday', 9, withRecess: true); // 10 rows
        $this->seedSchedule('Class 9', 'Monday', 12);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 9', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertViewHas('safeCount', 6 + 10 + 12);
    }

    private function assertBulkDeleteRemovesWholeSchedule(string $classSection, string $day, int $periodCount): void
    {
        $admin = $this->admin();
        $this->seedSchedule($classSection, $day, $periodCount);
        $this->assertSame($periodCount, BellTiming::where('class_section', $classSection)->count());

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => $classSection, 'day_of_week' => $day, 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $this->assertSame(0, BellTiming::where('class_section', $classSection)->count());
    }

    // ============================================================
    // 12. All-safe deletion
    // ============================================================

    public function test_all_safe_selection_deletes_every_selected_bell_timing(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9);
        $this->seedSchedule('Class 4', 'Monday', 9);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionHas('success');
        $this->assertSame(0, BellTiming::where('class_section', 'Class 3')->count());
        $this->assertSame(0, BellTiming::where('class_section', 'Class 4')->count());
    }

    // ============================================================
    // 13-16. Dependency blocking, per type
    // ============================================================

    public function test_timetable_slot_dependency_blocks_only_the_affected_record(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 6);
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('TS');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertSessionHas('success');
        $this->assertSame(1, BellTiming::where('class_section', 'Class 5')->count(), 'Only the blocked row should remain.');
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    public function test_published_timetable_dependency_is_clearly_identified_in_preview(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 6);
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('PUB');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertSee('published', false);
        $response->assertViewHas('safeCount', 5);
        $blocked = $response->viewData('blocked');
        $this->assertCount(1, $blocked);
        $this->assertSame($blockedRow->id, $blocked[0]['bellTiming']->id);
    }

    public function test_teacher_substitution_dependency_blocks_the_affected_record(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures('SUB');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $blockedRow->id,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertSessionHas('success');
        $this->assertSame(1, BellTiming::where('class_section', 'Class 5')->count());
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertDatabaseHas('teacher_substitutions', ['id' => $substitution->id]);
    }

    public function test_teacher_availability_dependency_blocks_the_affected_record(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        [, , , $teacher] = $this->makeDependencyFixtures('AVAIL');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        $availability = TeacherAvailability::create([
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $blockedRow->id,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertSessionHas('success');
        $this->assertSame(1, BellTiming::where('class_section', 'Class 5')->count());
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
        $this->assertDatabaseHas('teacher_availabilities', ['id' => $availability->id]);
    }

    // ============================================================
    // 17-19. Mixed safe/blocked outcome + no side effects
    // ============================================================

    public function test_mixed_safe_and_blocked_selection_deletes_only_safe_records(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9); // all safe
        $this->seedSchedule('Class 5', 'Tuesday', 3); // one blocked
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('MIX');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertSessionHas('success');
        $this->assertSame(0, BellTiming::where('class_section', 'Class 3')->count(), 'All of Class 3 (no dependencies) should be deleted.');
        $this->assertSame(1, BellTiming::where('class_section', 'Class 5')->count(), 'Only the blocked Class 5 row should remain.');
        $this->assertDatabaseHas('bell_timings', ['id' => $blockedRow->id]);
    }

    public function test_blocked_records_are_not_modified_by_a_bulk_delete_attempt(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3, overrides: ['period_name' => 'Period X']);
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('NOMOD');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $blockedRow->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $blockedRow->refresh();
        $this->assertTrue($blockedRow->is_active);
        $this->assertNotNull($blockedRow->period_name);
    }

    public function test_dependent_records_are_not_modified_by_a_bulk_delete_attempt(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures('DEPMOD');
        $blockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        $substitution = TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $blockedRow->id,
            'created_by' => $admin->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $substitution->refresh();
        $this->assertSame('pending', $substitution->status);
        $this->assertSame($blockedRow->id, $substitution->bell_timing_id);
    }

    // ============================================================
    // 20-21. Selection tampering cannot bypass tuple resolution
    // ============================================================

    public function test_crafted_raw_bell_timing_ids_in_the_request_are_ignored(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9);
        $unrelated = $this->seedUnrelatedRecord();

        $payload = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);
        // The controller never reads a raw id field at all -- confirm that
        // smuggling one in the request changes nothing.
        $payload['bell_timing_ids'] = [$unrelated->id];
        $payload['ids'] = [$unrelated->id];

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $payload);

        $this->assertSame(0, BellTiming::where('class_section', 'Class 3')->count());
        $this->assertDatabaseHas('bell_timings', ['id' => $unrelated->id]);
    }

    public function test_a_fabricated_class_day_selection_matching_no_real_schedule_deletes_nothing(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 9);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $this->selectionGroups([
            ['class_section' => 'Nonexistent Class', 'day_of_week' => 'Sunday', 'academic_year' => '1999-2000', 'semester' => 'Third'],
        ]));

        $response->assertRedirect(route('bell-timing.bulk-delete'));
        $response->assertSessionHas('error');
        $this->assertSame(9, BellTiming::where('class_section', 'Class 3')->count(), 'Nothing matched the fabricated selection, so nothing real should be touched.');
    }

    private function seedUnrelatedRecord(): BellTiming
    {
        return BellTiming::create([
            'day_of_week' => 'Friday',
            'period_name' => 'Untouched Period',
            'start_time' => '15:00',
            'end_time' => '15:40',
            'class_section' => 'UAT Class Alpha',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 0,
        ]);
    }

    // ============================================================
    // 22. Stale preview / re-check at confirm time
    // ============================================================

    public function test_a_dependency_created_after_preview_but_before_confirm_is_still_caught(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $preview = $this->actingAs($admin)->post(route('bell-timing.bulk-delete.preview'), $selection);
        $preview->assertViewHas('safeCount', 3);

        // A dependency appears in the gap between preview and confirm.
        [, , , $teacher] = $this->makeDependencyFixtures('STALE');
        $nowBlockedRow = BellTiming::where('class_section', 'Class 5')->orderBy('order_index')->first();
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $nowBlockedRow->id]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-delete.confirm'), $selection);

        $this->assertSame(1, BellTiming::where('class_section', 'Class 5')->count(), 'confirm() must re-check, not trust the earlier preview.');
        $this->assertDatabaseHas('bell_timings', ['id' => $nowBlockedRow->id]);
    }
}
