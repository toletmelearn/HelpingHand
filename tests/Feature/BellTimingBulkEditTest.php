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
 * Phase 5A coverage for One-Period Bulk Bell Timing Edit. Selection is by
 * (class_section, day_of_week, academic_year, semester) tuples -- same as
 * Bulk Delete, reusing extractSelectedGroups()/resolveSelectedBellTimings()
 * unmodified. The target period within each selected schedule is matched
 * by (class_section, day_of_week, period_name) only -- never by position --
 * and confirm() independently re-resolves everything and only writes a
 * record whose updated_at still matches what preview showed.
 */
class BellTimingBulkEditTest extends TestCase
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
        $schoolClass = SchoolClass::create(['name' => "Bulk Edit Class $suffix", 'class_order' => 971, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $schoolClass->id]);
        $subject = Subject::create(['name' => "Bulk Edit Subject $suffix", 'code' => "BE-$suffix", 'is_active' => true]);
        $teacher = Teacher::create(['name' => "Bulk Edit Teacher $suffix", 'status' => 'active']);

        return [$schoolClass, $section, $subject, $teacher];
    }

    private function knownStateFor(array $rows): array
    {
        $state = [];
        foreach ($rows as $row) {
            $state[$row->fresh()->id] = $row->fresh()->updated_at->toISOString();
        }

        return ['known_state' => $state];
    }

    // ============================================================
    // 1-5. Authorization
    // ============================================================

    public function test_admin_can_open_bulk_edit_screen(): void
    {
        $this->seedSchedule('Class 3', 'Monday', 9);

        $response = $this->actingAs($this->admin())->get(route('bell-timing.bulk-edit'));

        $response->assertOk();
        $response->assertSee('Class 3', false);
        $response->assertSee('Monday', false);
    }

    public function test_teacher_cannot_access_bulk_edit_screen(): void
    {
        $response = $this->actingAs($this->teacherUser())->get(route('bell-timing.bulk-edit'));

        $response->assertForbidden();
    }

    public function test_parent_guard_user_cannot_access_bulk_edit_screen(): void
    {
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887771',
            'email' => 'bulk.edit.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->get(route('bell-timing.bulk-edit'));

        $response->assertStatus(302);
    }

    public function test_unauthenticated_user_cannot_access_bulk_edit_screen(): void
    {
        $response = $this->get(route('bell-timing.bulk-edit'));

        $response->assertStatus(302);
    }

    public function test_teacher_cannot_target_preview_or_confirm_bulk_edit(): void
    {
        $this->seedSchedule('Class 3', 'Monday', 9);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);
        $editFields = ['target_period_name' => 'Period 1', 'change_period_name' => '1', 'new_period_name' => 'Renamed'];

        $target = $this->actingAs($this->teacherUser())->post(route('bell-timing.bulk-edit.target'), $selection);
        $preview = $this->actingAs($this->teacherUser())->post(route('bell-timing.bulk-edit.preview'), $selection + $editFields);
        $confirm = $this->actingAs($this->teacherUser())->post(route('bell-timing.bulk-edit.confirm'), $selection + $editFields);

        $target->assertForbidden();
        $preview->assertForbidden();
        $confirm->assertForbidden();
        $this->assertDatabaseMissing('bell_timings', ['class_section' => 'Class 3', 'period_name' => 'Renamed']);
    }

    // ============================================================
    // 6-8. Selection / target resolution
    // ============================================================

    public function test_selecting_no_groups_redirects_with_error_on_target(): void
    {
        $this->seedSchedule('Class 3', 'Monday', 6);

        // A group row present but left unchecked -- passes the
        // groups=required|array rule but resolves to zero selections.
        $response = $this->actingAs($this->admin())->post(route('bell-timing.bulk-edit.target'), [
            'groups' => [0 => ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First']],
        ]);

        $response->assertRedirect(route('bell-timing.bulk-edit'));
        $response->assertSessionHas('error');
    }

    public function test_target_screen_lists_period_names_present_in_selection(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.target'), $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]));

        $response->assertOk();
        $response->assertViewHas('periodNames');
        $names = $response->viewData('periodNames');
        $this->assertTrue($names->contains('Period 3'));
        $this->assertCount(6, $names);
    }

    public function test_target_screen_with_no_matching_rows_redirects_with_error(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.target'), $this->selectionGroups([
            ['class_section' => 'Nonexistent', 'day_of_week' => 'Sunday', 'academic_year' => '1999-2000', 'semester' => 'Third'],
        ]));

        $response->assertRedirect(route('bell-timing.bulk-edit'));
        $response->assertSessionHas('error');
    }

    // ============================================================
    // 9-11. Period matching: exact / missing / ambiguous
    // ============================================================

    public function test_exact_single_match_is_editable(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 3',
            'change_period_name' => '1',
            'new_period_name' => 'Period 3 Renamed',
        ]);

        $response->assertOk();
        $preview = $response->viewData('preview');
        $this->assertCount(1, $preview);
        $this->assertEmpty($response->viewData('missing'));
        $this->assertEmpty($response->viewData('ambiguous'));
    }

    public function test_missing_period_is_reported_and_not_updated(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6); // no "Period 9" here
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 9',
            'change_period_name' => '1',
            'new_period_name' => 'Should Never Apply',
        ]);

        $response->assertOk();
        $this->assertEmpty($response->viewData('preview'));
        $missing = $response->viewData('missing');
        $this->assertCount(1, $missing);
        $this->assertSame('Class 3', $missing[0]['class_section']);
    }

    public function test_ambiguous_period_is_reported_and_not_updated(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 3);
        // A second row sharing the same period_name -- the schema has no
        // unique constraint preventing this.
        BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 2',
            'start_time' => '15:00',
            'end_time' => '15:40',
            'class_section' => 'Class 3',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 99,
            'academic_year' => '2026-2027',
            'semester' => 'First',
        ]);

        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 2',
            'change_period_name' => '1',
            'new_period_name' => 'Should Never Apply',
        ]);

        $response->assertOk();
        $this->assertEmpty($response->viewData('preview'));
        $ambiguous = $response->viewData('ambiguous');
        $this->assertCount(1, $ambiguous);
        $this->assertSame(2, $ambiguous[0]['count']);

        // Confirming afterward must not touch either ambiguous row.
        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 2',
            'change_period_name' => '1',
            'new_period_name' => 'Should Never Apply',
        ]);
        $confirm->assertSessionHas('error');
        $this->assertDatabaseMissing('bell_timings', ['class_section' => 'Class 3', 'period_name' => 'Should Never Apply']);
    }

    // ============================================================
    // 12-17. Structure independence
    // ============================================================

    private function assertPeriodThreeMatchesInSchedule(string $classSection, int $periodCount): void
    {
        $admin = $this->admin();
        $this->seedSchedule($classSection, 'Monday', $periodCount);
        $selection = $this->selectionGroups([
            ['class_section' => $classSection, 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 3',
            'change_custom_label' => '1',
            'new_custom_label' => 'Matched',
        ]);

        $response->assertOk();
        $this->assertCount(1, $response->viewData('preview'));
    }

    public function test_six_period_schedule_matches_correctly(): void
    {
        $this->assertPeriodThreeMatchesInSchedule('Class 3', 6);
    }

    public function test_eight_period_schedule_matches_correctly(): void
    {
        $this->assertPeriodThreeMatchesInSchedule('Class 4', 8);
    }

    public function test_nine_period_schedule_matches_correctly(): void
    {
        $this->assertPeriodThreeMatchesInSchedule('Class 6', 9);
    }

    public function test_twelve_period_schedule_matches_correctly(): void
    {
        $this->assertPeriodThreeMatchesInSchedule('Class 9', 12);
    }

    public function test_recess_period_can_be_targeted_and_edited(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Monday', 8, withRecess: true);
        $recess = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Recess')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Recess',
            'change_time' => '1',
            'new_start_time' => '10:45',
            'new_end_time' => '11:00',
        ] + $this->knownStateFor([$recess]));

        $recess->refresh();
        $this->assertSame('10:45:00', $recess->start_time->format('H:i:s'));
        $this->assertSame('11:00:00', $recess->end_time->format('H:i:s'));
    }

    public function test_different_structures_across_classes_all_resolve_independently(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $this->seedSchedule('Class 4', 'Monday', 9, withRecess: true);
        $this->seedSchedule('Class 9', 'Monday', 12);
        // Class 4 has no plain "Period 3" match issue -- withRecess inserts
        // Recess before period index 4, period names are unaffected.

        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 9', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 3',
            'change_custom_label' => '1',
            'new_custom_label' => 'Matched',
        ]);

        $response->assertOk();
        $this->assertCount(3, $response->viewData('preview'));
    }

    // ============================================================
    // 18-22. Editable field whitelist
    // ============================================================

    public function test_time_change_updates_only_start_and_end_time(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $originalName = $row->period_name;
        $originalOrder = $row->order_index;
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_time' => '1',
            'new_start_time' => '09:05',
            'new_end_time' => '09:35',
        ] + $this->knownStateFor([$row]));

        $row->refresh();
        $this->assertSame('09:05:00', $row->start_time->format('H:i:s'));
        $this->assertSame('09:35:00', $row->end_time->format('H:i:s'));
        $this->assertSame($originalName, $row->period_name);
        $this->assertSame($originalOrder, $row->order_index);
    }

    public function test_period_name_change_updates_only_period_name(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $originalStart = $row->start_time->format('H:i:s');
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_period_name' => '1',
            'new_period_name' => 'Science Period',
        ] + $this->knownStateFor([$row]));

        $row->refresh();
        $this->assertSame('Science Period', $row->period_name);
        $this->assertSame($originalStart, $row->start_time->format('H:i:s'));
    }

    public function test_custom_label_change_updates_only_custom_label(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_custom_label' => '1',
            'new_custom_label' => 'Math Block',
        ] + $this->knownStateFor([$row]));

        $row->refresh();
        $this->assertSame('Math Block', $row->custom_label);
        $this->assertSame('Period 3', $row->period_name);
    }

    public function test_color_code_change_updates_only_color_code(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_color_code' => '1',
            'new_color_code' => '#123ABC',
        ] + $this->knownStateFor([$row]));

        $row->refresh();
        $this->assertSame('#123ABC', $row->color_code);
        $this->assertSame('Period 3', $row->period_name);
    }

    public function test_forbidden_fields_are_never_changed(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $originalId = $row->id;
        $originalClassSection = $row->class_section;
        $originalAcademicYear = $row->academic_year;
        $originalSemester = $row->semester;
        $originalOrderIndex = $row->order_index;
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        // Attempt to smuggle forbidden fields into the request -- the
        // controller only ever reads the five whitelisted change_*/new_*
        // pairs, so these must be silently ignored.
        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_period_name' => '1',
            'new_period_name' => 'Renamed',
            'class_section' => 'Hacked Class',
            'academic_year' => '1900-1901',
            'semester' => 'Hacked',
            'order_index' => 999,
            'id' => 999999,
            'created_by' => 999999,
        ] + $this->knownStateFor([$row]));

        $row->refresh();
        $this->assertSame($originalId, $row->id);
        $this->assertSame($originalClassSection, $row->class_section);
        $this->assertSame($originalAcademicYear, $row->academic_year);
        $this->assertSame($originalSemester, $row->semester);
        $this->assertSame($originalOrderIndex, $row->order_index);
        $this->assertSame('Renamed', $row->period_name);
    }

    // ============================================================
    // 23-26. Validation
    // ============================================================

    public function test_no_change_selected_fails_validation(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 3',
        ]);

        $response->assertStatus(422);
        $response->assertViewIs('bell-timing.bulk-edit-target');
        $response->assertViewHas('errors', fn ($errors) => $errors->any());
    }

    public function test_invalid_time_format_fails_validation(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        // A real browser sends a Referer of the page the form was
        // submitted from -- bulk-edit-target, which is POST-only. If a
        // validation failure here fell through to Laravel's default
        // back()-redirect (a GET to that Referer), it would 405. Setting
        // the header explicitly is what reproduces that in a test; without
        // it the test client has no Referer and silently redirects to '/'
        // instead, masking the bug (this is exactly how it slipped past
        // the first version of this test suite).
        $response = $this->actingAs($admin)
            ->from(route('bell-timing.bulk-edit.target'))
            ->post(route('bell-timing.bulk-edit.preview'), $selection + [
                'target_period_name' => 'Period 3',
                'change_time' => '1',
                'new_start_time' => '25:99',
                'new_end_time' => '09:35',
            ]);

        $response->assertStatus(422);
        $response->assertViewIs('bell-timing.bulk-edit-target');
        $response->assertViewHas('errors', fn ($errors) => $errors->has('new_start_time'));
    }

    public function test_end_time_before_start_time_fails_validation(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('bell-timing.bulk-edit.target'))
            ->post(route('bell-timing.bulk-edit.preview'), $selection + [
                'target_period_name' => 'Period 3',
                'change_time' => '1',
                'new_start_time' => '10:00',
                'new_end_time' => '09:00',
            ]);

        $response->assertStatus(422);
        $response->assertViewIs('bell-timing.bulk-edit-target');
        $response->assertViewHas('errors', fn ($errors) => $errors->has('new_end_time'));
    }

    public function test_invalid_time_on_preview_never_redirects_to_the_post_only_target_route(): void
    {
        // Direct regression test for the discovered defect: submitting
        // invalid data from the real target page (Referer set, exactly as
        // a browser would send it) must never produce a 405 by redirecting
        // back to the POST-only /bulk-edit/target route.
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('bell-timing.bulk-edit.target'))
            ->post(route('bell-timing.bulk-edit.preview'), $selection + [
                'target_period_name' => 'Period 3',
                'change_time' => '1',
                'new_start_time' => '10:00',
                'new_end_time' => '09:00',
            ]);

        $response->assertStatus(422);
        $this->assertNotSame(405, $response->getStatusCode());

        // The form's own values must be preserved, and the selection must
        // still be intact -- nothing resets and nothing was lost.
        $response->assertSee('Class 3', false);
    }

    public function test_tampered_payload_on_confirm_redirects_safely_instead_of_405ing(): void
    {
        // Same POST-only-route hazard as above, but for bulkEditConfirm(),
        // which is reached from the equally POST-only bulk-edit-preview
        // screen. This path is only reachable via a tampered request (the
        // UI never submits invalid values here), so a safe redirect to the
        // GET-accessible selection screen -- not a 405 -- is correct.
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('bell-timing.bulk-edit.preview'))
            ->post(route('bell-timing.bulk-edit.confirm'), $selection + [
                'target_period_name' => 'Period 3',
                'change_time' => '1',
                'new_start_time' => '10:00',
                'new_end_time' => '09:00',
            ]);

        $this->assertNotSame(405, $response->getStatusCode());
        $response->assertRedirect(route('bell-timing.bulk-edit'));
        $response->assertSessionHas('error');
    }

    public function test_missing_target_period_name_fails_validation(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $response->assertStatus(422);
        $response->assertViewIs('bell-timing.bulk-edit-target');
        $response->assertViewHas('errors', fn ($errors) => $errors->has('target_period_name'));
    }

    // ============================================================
    // 27-31. Dependency warnings (warn, never block)
    // ============================================================

    public function test_draft_timetable_slot_shows_warning_but_does_not_block(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 6);
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('DRAFT');
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 2')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $row->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_DRAFT,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 2',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $preview = $response->viewData('preview');
        $this->assertTrue($preview->first()['warning']);

        // Draft dependency warns but must not block confirm.
        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 2',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ] + $this->knownStateFor([$row]));
        $confirm->assertSessionHas('success');
        $row->refresh();
        $this->assertSame('X', $row->custom_label);
    }

    public function test_published_timetable_slot_warning_says_published(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 6);
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('PUB');
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 2')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $row->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 2',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $response->assertSee('published', false);
        $preview = $response->viewData('preview');
        $this->assertTrue($preview->first()['warning']);
    }

    public function test_teacher_substitution_dependency_shows_warning(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        [$schoolClass, $section, $subject, $teacher] = $this->makeDependencyFixtures('SUB');
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 1')->first();
        TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $teacher->id,
            'class_id' => $schoolClass->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'period_number' => 1,
            'bell_timing_id' => $row->id,
            'created_by' => $admin->id,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 1',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $preview = $response->viewData('preview');
        $this->assertTrue($preview->first()['warning']);
        $this->assertStringContainsString('substitution', $preview->first()['reason']);
    }

    public function test_teacher_availability_dependency_shows_warning(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        [, , , $teacher] = $this->makeDependencyFixtures('AVAIL');
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 1')->first();
        TeacherAvailability::create(['teacher_id' => $teacher->id, 'bell_timing_id' => $row->id]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 1',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $preview = $response->viewData('preview');
        $this->assertTrue($preview->first()['warning']);
        $this->assertStringContainsString('availability', $preview->first()['reason']);
    }

    public function test_mixed_warned_and_unwarned_selection_all_included_in_preview(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 3); // clean
        $this->seedSchedule('Class 5', 'Monday', 3); // will get a dependency
        [$schoolClass, , $subject, $teacher] = $this->makeDependencyFixtures('MIX');
        $warnedRow = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 1')->first();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $warnedRow->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 5', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.preview'), $selection + [
            'target_period_name' => 'Period 1',
            'change_custom_label' => '1',
            'new_custom_label' => 'X',
        ]);

        $preview = $response->viewData('preview');
        $this->assertCount(2, $preview);
        $warnings = $preview->pluck('warning')->values()->all();
        $this->assertContains(true, $warnings);
        $this->assertContains(false, $warnings);
    }

    // ============================================================
    // 32-36. Confirm safety: re-resolution, staleness, tampering
    // ============================================================

    public function test_confirm_updates_only_matched_ready_records(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $this->seedSchedule('Class 4', 'Tuesday', 6); // not selected
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 3',
            'change_period_name' => '1',
            'new_period_name' => 'Renamed 3',
        ] + $this->knownStateFor([$row]));

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionHas('success');
        $row->refresh();
        $this->assertSame('Renamed 3', $row->period_name);
        $this->assertDatabaseMissing('bell_timings', ['class_section' => 'Class 4', 'period_name' => 'Renamed 3']);
    }

    public function test_stale_updated_at_since_preview_is_excluded_and_not_overwritten(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 1')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        // Capture "preview" known_state, then mutate the record afterward
        // (simulating another admin editing it in the gap between preview
        // and confirm) before sending confirm. Timestamps in MySQL/MariaDB
        // (and the test DB) only have whole-second precision, so the clock
        // is advanced explicitly -- otherwise two updates issued
        // back-to-back within the same second would be indistinguishable.
        $known = $this->knownStateFor([$row]);
        \Carbon\Carbon::setTestNow(now()->addSeconds(2));
        $row->update(['custom_label' => 'Changed in between']);
        \Carbon\Carbon::setTestNow();

        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 1',
            'change_period_name' => '1',
            'new_period_name' => 'Should Not Apply',
        ] + $known);

        $confirm->assertSessionHas('error');
        $row->refresh();
        $this->assertSame('Period 1', $row->period_name);
        $this->assertSame('Changed in between', $row->custom_label);
    }

    public function test_missing_known_state_for_a_record_excludes_it(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 5', 'Tuesday', 3);
        $row = BellTiming::where('class_section', 'Class 5')->where('period_name', 'Period 1')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 5', 'day_of_week' => 'Tuesday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        // No known_state sent at all -- confirm() must treat this the same
        // as a stale record and refuse to write.
        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 1',
            'change_period_name' => '1',
            'new_period_name' => 'Should Not Apply',
        ]);

        $confirm->assertSessionHas('error');
        $row->refresh();
        $this->assertSame('Period 1', $row->period_name);
    }

    public function test_all_stale_or_missing_confirm_shows_error_and_updates_nothing(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 3);
        $this->seedSchedule('Class 4', 'Monday', 3);
        $row3 = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 1')->first();
        $row4 = BellTiming::where('class_section', 'Class 4')->where('period_name', 'Period 1')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
            ['class_section' => 'Class 4', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $known = $this->knownStateFor([$row3, $row4]);
        \Carbon\Carbon::setTestNow(now()->addSeconds(2));
        $row3->update(['custom_label' => 'stale3']);
        $row4->update(['custom_label' => 'stale4']);
        \Carbon\Carbon::setTestNow();

        $confirm = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $selection + [
            'target_period_name' => 'Period 1',
            'change_period_name' => '1',
            'new_period_name' => 'Should Not Apply',
        ] + $known);

        $confirm->assertRedirect(route('bell-timing.bulk-edit'));
        $confirm->assertSessionHas('error');
        $this->assertDatabaseMissing('bell_timings', ['period_name' => 'Should Not Apply']);
    }

    // ============================================================
    // 37-38. Tampering / fabricated selections
    // ============================================================

    public function test_crafted_bell_timing_ids_in_confirm_request_are_ignored(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);
        $unrelated = BellTiming::create([
            'day_of_week' => 'Friday',
            'period_name' => 'Untouched Period',
            'start_time' => '15:00',
            'end_time' => '15:40',
            'class_section' => 'UAT Class Alpha',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 0,
        ]);
        $row = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Period 3')->first();
        $selection = $this->selectionGroups([
            ['class_section' => 'Class 3', 'day_of_week' => 'Monday', 'academic_year' => '2026-2027', 'semester' => 'First'],
        ]);

        $payload = $selection + [
            'target_period_name' => 'Period 3',
            'change_period_name' => '1',
            'new_period_name' => 'Renamed',
        ] + $this->knownStateFor([$row]);
        // Smuggle a raw id the controller never reads.
        $payload['bell_timing_ids'] = [$unrelated->id];

        $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $payload);

        $unrelated->refresh();
        $this->assertSame('Untouched Period', $unrelated->period_name);
    }

    public function test_fabricated_selection_matching_nothing_produces_no_changes(): void
    {
        $admin = $this->admin();
        $this->seedSchedule('Class 3', 'Monday', 6);

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-edit.confirm'), $this->selectionGroups([
            ['class_section' => 'Nonexistent Class', 'day_of_week' => 'Sunday', 'academic_year' => '1999-2000', 'semester' => 'Third'],
        ]) + [
            'target_period_name' => 'Period 1',
            'change_period_name' => '1',
            'new_period_name' => 'Should Not Apply',
        ]);

        $response->assertRedirect(route('bell-timing.bulk-edit'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('bell_timings', ['period_name' => 'Should Not Apply']);
    }
}
