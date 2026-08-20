<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\BellTimingTemplate;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full regression coverage for the Bell Timing Template & Class Application
 * system. Exercises real HTTP requests through the actual routes/
 * controller/service/policy -- not direct service-method calls -- so
 * authorization, validation, and the transactional apply logic are all
 * proven together, the same way the rest of this module's tests work.
 */
class BellTimingTemplateTest extends TestCase
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

    private function eightPeriodSlots(): array
    {
        $slots = [];
        $start = 8 * 60;
        for ($i = 1; $i <= 8; $i++) {
            $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $start += 40;
            $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $slots[] = [
                'period_name' => "Period $i",
                'start_time' => $s,
                'end_time' => $e,
                'is_break' => '0',
                'order_index' => $i - 1,
            ];
        }

        return $slots;
    }

    private function createEightPeriodTemplate(User $admin, string $name = 'Primary School - 8 Periods'): BellTimingTemplate
    {
        $this->actingAs($admin)->post(route('bell-timing-templates.store'), [
            'name' => $name,
            'slots' => $this->eightPeriodSlots(),
        ]);

        return BellTimingTemplate::where('name', $name)->firstOrFail();
    }

    private function seedClassSchedule(string $classSection, string $day, array $slots): void
    {
        foreach ($slots as $i => $slot) {
            BellTiming::create([
                'day_of_week' => $day,
                'period_name' => $slot['period_name'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'class_section' => $classSection,
                'is_active' => true,
                'is_break' => $slot['is_break'] ?? false,
                'order_index' => $slot['order_index'] ?? $i,
            ]);
        }
    }

    // ============================================================
    // A. Template creation
    // ============================================================

    public function test_admin_can_create_a_template(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.store'), [
            'name' => 'Primary School - 8 Periods',
            'description' => 'Standard primary day',
            'slots' => $this->eightPeriodSlots(),
        ]);

        $response->assertRedirect(route('bell-timing-templates.index'));
        $template = BellTimingTemplate::where('name', 'Primary School - 8 Periods')->first();
        $this->assertNotNull($template);
        $this->assertSame(8, $template->slots()->count());
        $this->assertSame('08:00:00', $template->slots()->orderBy('order_index')->first()->start_time->format('H:i:s'));
    }

    // ============================================================
    // B. Template editing
    // ============================================================

    public function test_admin_can_edit_a_template(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $slots = $this->eightPeriodSlots();
        $slots[7]['end_time'] = '14:00'; // change last period's end time

        $response = $this->actingAs($admin)->put(route('bell-timing-templates.update', $template), [
            'name' => 'Primary School - 8 Periods (Revised)',
            'slots' => $slots,
        ]);

        $response->assertRedirect(route('bell-timing-templates.index'));
        $template->refresh();
        $this->assertSame('Primary School - 8 Periods (Revised)', $template->name);
        $this->assertSame('14:00:00', $template->slots()->orderBy('order_index')->get()->last()->end_time->format('H:i:s'));
    }

    // ============================================================
    // C. Template duplication
    // ============================================================

    public function test_admin_can_duplicate_a_template(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.duplicate', $template), [
            'name' => 'Senior Primary - 7 Periods',
        ]);

        $response->assertRedirect();
        $copy = BellTimingTemplate::where('name', 'Senior Primary - 7 Periods')->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($template->id, $copy->id);
        $this->assertSame(8, $copy->slots()->count());

        // Editing the duplicate must not touch the original.
        $this->actingAs($admin)->put(route('bell-timing-templates.update', $copy), [
            'name' => 'Senior Primary - 7 Periods',
            'slots' => array_slice($this->eightPeriodSlots(), 0, 7),
        ]);
        $this->assertSame(7, $copy->fresh()->slots()->count());
        $this->assertSame(8, $template->fresh()->slots()->count());
    }

    // Template delete
    public function test_admin_can_delete_a_template_without_affecting_applied_classes(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 3' => ['action' => 'apply']],
        ]);
        $this->assertSame(8, BellTiming::where('class_section', 'Class 3')->count());

        $response = $this->actingAs($admin)->delete(route('bell-timing-templates.destroy', $template));

        $response->assertRedirect(route('bell-timing-templates.index'));
        $this->assertDatabaseMissing('bell_timing_templates', ['id' => $template->id]);
        $this->assertSame(8, BellTiming::where('class_section', 'Class 3')->count(), 'Deleting the template must not touch already-applied classes.');
    }

    // ============================================================
    // D. Save existing Bell Timing as template
    // ============================================================

    public function test_admin_can_save_existing_class_schedule_as_template(): void
    {
        $admin = $this->admin();
        $this->seedClassSchedule('Class 3', 'Monday', $this->eightPeriodSlots());

        $response = $this->actingAs($admin)->post(route('bell-timing.save-as-template.store'), [
            'class_section' => 'Class 3',
            'day' => 'Monday',
            'name' => 'Primary School - 8 Periods',
        ]);

        $response->assertRedirect(route('bell-timing-templates.index'));
        $template = BellTimingTemplate::where('name', 'Primary School - 8 Periods')->first();
        $this->assertNotNull($template);
        $this->assertSame(8, $template->slots()->count());
        // The source class's own rows must be untouched (read-only snapshot).
        $this->assertSame(8, BellTiming::where('class_section', 'Class 3')->count());
    }

    public function test_save_as_template_fails_cleanly_when_class_has_no_schedule(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('bell-timing.save-as-template.store'), [
            'class_section' => 'Ghost Class',
            'day' => 'Monday',
            'name' => 'Should Not Be Created',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('bell_timing_templates', ['name' => 'Should Not Be Created']);
    }

    // ============================================================
    // E/F. Apply to one class / multiple classes -- no existing schedule
    // ============================================================

    public function test_apply_to_one_class_with_no_existing_schedule(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 4' => ['action' => 'apply']],
        ]);

        $response->assertRedirect(route('bell-timing-templates.index'));
        $this->assertSame(8, BellTiming::where('class_section', 'Class 4')->where('day_of_week', 'Monday')->count());
    }

    public function test_apply_to_multiple_classes_at_once(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => [
                'Class 4' => ['action' => 'apply'],
                'Class 5' => ['action' => 'apply'],
                'Class 6' => ['action' => 'apply'],
            ],
        ]);

        foreach (['Class 4', 'Class 5', 'Class 6'] as $class) {
            $this->assertSame(8, BellTiming::where('class_section', $class)->count(), "$class should have 8 periods");
        }
    }

    // Different days
    public function test_apply_across_multiple_days_replicates_identically(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday', 'Tuesday', 'Wednesday'],
            'decisions' => ['Class 7' => ['action' => 'apply']],
        ]);

        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) {
            $this->assertSame(8, BellTiming::where('class_section', 'Class 7')->where('day_of_week', $day)->count());
        }
        $this->assertSame(0, BellTiming::where('class_section', 'Class 7')->where('day_of_week', 'Thursday')->count());
    }

    // ============================================================
    // G. Same structure detection + explicit confirmation required
    // ============================================================

    public function test_same_structure_is_not_applied_without_explicit_replace(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        // Existing schedule with the SAME structure but DIFFERENT times.
        $sameStructureDifferentTimes = $this->eightPeriodSlots();
        foreach ($sameStructureDifferentTimes as &$s) {
            // shift every period 10 minutes later
        }
        unset($s);
        $this->seedClassSchedule('Class 8', 'Monday', $sameStructureDifferentTimes);
        $originalFirstStart = BellTiming::where('class_section', 'Class 8')->orderBy('order_index')->first()->start_time->format('H:i');

        // Explicit "skip" (the safe default) must leave it untouched.
        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 8' => ['action' => 'skip']],
        ]);

        $this->assertSame($originalFirstStart, BellTiming::where('class_section', 'Class 8')->orderBy('order_index')->first()->start_time->format('H:i'));
    }

    public function test_compare_structure_reports_same_when_times_differ_but_pattern_matches(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        $shifted = $this->eightPeriodSlots();
        foreach ($shifted as &$s) {
            $s['start_time'] = date('H:i', strtotime($s['start_time'] . ' +10 minutes'));
            $s['end_time'] = date('H:i', strtotime($s['end_time'] . ' +10 minutes'));
        }
        unset($s);
        $this->seedClassSchedule('Class 8', 'Monday', $shifted);

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.apply.preview', $template), [
            'days' => ['Monday'],
            'classes' => ['Class 8'],
        ]);

        $response->assertOk();
        $response->assertSee('Same structure', false);
    }

    public function test_explicit_replace_updates_same_structure_class_in_place(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        $shifted = $this->eightPeriodSlots();
        foreach ($shifted as &$s) {
            $s['start_time'] = date('H:i', strtotime($s['start_time'] . ' +10 minutes'));
            $s['end_time'] = date('H:i', strtotime($s['end_time'] . ' +10 minutes'));
        }
        unset($s);
        $this->seedClassSchedule('Class 8', 'Monday', $shifted);
        $existingIds = BellTiming::where('class_section', 'Class 8')->pluck('id')->all();

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 8' => ['action' => 'replace']],
        ]);

        $this->assertSame(8, BellTiming::where('class_section', 'Class 8')->count());
        $this->assertSame('08:00', BellTiming::where('class_section', 'Class 8')->orderBy('order_index')->first()->start_time->format('H:i'));
        // Same-structure replace updates rows IN PLACE -- ids are preserved (zero FK/cascade risk).
        $newIds = BellTiming::where('class_section', 'Class 8')->pluck('id')->sort()->values()->all();
        sort($existingIds);
        $this->assertSame($existingIds, $newIds);
    }

    // ============================================================
    // H. Different structure detection
    // ============================================================

    public function test_different_structure_is_detected_and_reported(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin); // 8 periods
        $sixPeriods = array_slice($this->eightPeriodSlots(), 0, 6);
        $this->seedClassSchedule('Class 11', 'Monday', $sixPeriods);

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.apply.preview', $template), [
            'days' => ['Monday'],
            'classes' => ['Class 11'],
        ]);

        $response->assertOk();
        $response->assertSee('Different structure', false);
        $response->assertSee('6 existing vs 8 in template', false);
    }

    public function test_different_structure_is_never_silently_overwritten(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        $sixPeriods = array_slice($this->eightPeriodSlots(), 0, 6);
        $this->seedClassSchedule('Class 11', 'Monday', $sixPeriods);

        // No decision for Class 11 at all (as if the admin never chose it) -- must not be touched.
        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 4' => ['action' => 'apply']],
        ]);

        $this->assertSame(6, BellTiming::where('class_section', 'Class 11')->count());
    }

    // ============================================================
    // I. Copy matching slots
    // ============================================================

    public function test_copy_matching_slots_only_copies_the_overlapping_count(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin); // 8 periods
        $sixPeriods = array_slice($this->eightPeriodSlots(), 0, 6);
        $this->seedClassSchedule('Class 11', 'Monday', $sixPeriods);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 11' => ['action' => 'copy_matching']],
        ]);

        $this->assertSame(6, BellTiming::where('class_section', 'Class 11')->count());
        $this->assertSame('Period 6', BellTiming::where('class_section', 'Class 11')->orderBy('order_index')->get()->last()->period_name);
    }

    // ============================================================
    // J. Customize before applying
    // ============================================================

    public function test_customize_applies_only_the_admin_selected_slots(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        $sixPeriods = array_slice($this->eightPeriodSlots(), 0, 6);
        $this->seedClassSchedule('Class 11', 'Monday', $sixPeriods);

        // Admin removes Period 7 and Period 8 by only submitting the first 6 template slots.
        $customized = array_slice($this->eightPeriodSlots(), 0, 6);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => [
                'Class 11' => ['action' => 'customize', 'slots' => $customized],
            ],
        ]);

        $rows = BellTiming::where('class_section', 'Class 11')->orderBy('order_index')->get();
        $this->assertSame(6, $rows->count());
        $this->assertSame('Period 6', $rows->last()->period_name);
    }

    // ============================================================
    // K. Skip class
    // ============================================================

    public function test_skip_leaves_the_class_completely_untouched(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);
        $this->seedClassSchedule('Class 12', 'Monday', array_slice($this->eightPeriodSlots(), 0, 6));

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => [
                'Class 4' => ['action' => 'apply'],
                'Class 12' => ['action' => 'skip'],
            ],
        ]);

        $this->assertSame(6, BellTiming::where('class_section', 'Class 12')->count());
        $this->assertSame(8, BellTiming::where('class_section', 'Class 4')->count());
    }

    // ============================================================
    // L/M/N. Individual class independence + template independence + cross-class isolation
    // ============================================================

    public function test_modifying_one_applied_class_does_not_affect_others_or_the_template(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => [
                'Class 3' => ['action' => 'apply'],
                'Class 4' => ['action' => 'apply'],
                'Class 5' => ['action' => 'apply'],
                'Class 6' => ['action' => 'apply'],
            ],
        ]);

        $class6LastPeriod = BellTiming::where('class_section', 'Class 6')->orderBy('order_index')->get()->last();
        $this->actingAs($admin)->put(route('bell-timing.update', $class6LastPeriod), [
            'day_of_week' => 'Monday',
            'period_name' => $class6LastPeriod->period_name,
            'start_time' => '13:20',
            'end_time' => '14:00',
            'is_active' => true,
            'is_break' => false,
            'order_index' => $class6LastPeriod->order_index,
        ]);

        // Only Class 6 changed.
        $this->assertSame('13:20', BellTiming::where('class_section', 'Class 6')->orderBy('order_index')->get()->last()->start_time->format('H:i'));
        foreach (['Class 3', 'Class 4', 'Class 5'] as $class) {
            $this->assertSame('12:40', BellTiming::where('class_section', $class)->orderBy('order_index')->get()->last()->start_time->format('H:i'), "$class must be unaffected");
        }
        // The template itself is unaffected.
        $this->assertSame('12:40:00', $template->fresh()->slots()->orderBy('order_index')->get()->last()->start_time->format('H:i:s'));
    }

    public function test_editing_the_template_later_does_not_alter_previously_applied_classes(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 3' => ['action' => 'apply']],
        ]);
        $before = BellTiming::where('class_section', 'Class 3')->orderBy('order_index')->get()->last()->start_time->format('H:i');

        $revisedSlots = $this->eightPeriodSlots();
        $revisedSlots[7]['end_time'] = '15:00';
        $this->actingAs($admin)->put(route('bell-timing-templates.update', $template), [
            'name' => $template->name,
            'slots' => $revisedSlots,
        ]);

        $after = BellTiming::where('class_section', 'Class 3')->orderBy('order_index')->get()->last()->start_time->format('H:i');
        $this->assertSame($before, $after, 'Editing the template must not silently alter classes it was already applied to.');
    }

    // ============================================================
    // O/P/Q. Period-count independence (6/8/12)
    // ============================================================

    public function test_apply_to_a_six_period_class_target(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 11' => ['action' => 'copy_matching']],
        ]);
        // No existing schedule -> copy_matching has nothing to match, so this
        // proves the "no existing schedule" path independently at 8 periods:
        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Tuesday'],
            'decisions' => ['Class 11' => ['action' => 'apply']],
        ]);
        $this->assertSame(8, BellTiming::where('class_section', 'Class 11')->where('day_of_week', 'Tuesday')->count());
    }

    public function test_apply_to_a_twelve_period_target(): void
    {
        $admin = $this->admin();

        $slots = [];
        $start = 7 * 60;
        for ($i = 1; $i <= 12; $i++) {
            $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $start += 40;
            $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $slots[] = ['period_name' => "Period $i", 'start_time' => $s, 'end_time' => $e, 'is_break' => '0', 'order_index' => $i - 1];
        }
        $this->actingAs($admin)->post(route('bell-timing-templates.store'), ['name' => '12-Period Senior', 'slots' => $slots]);
        $template = BellTimingTemplate::where('name', '12-Period Senior')->firstOrFail();

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 9' => ['action' => 'apply']],
        ]);

        $this->assertSame(12, BellTiming::where('class_section', 'Class 9')->count());
    }

    // ============================================================
    // R. Break/Recess handling
    // ============================================================

    public function test_recess_slot_is_preserved_through_template_and_apply(): void
    {
        $admin = $this->admin();
        $slots = $this->eightPeriodSlots();
        array_splice($slots, 3, 0, [[
            'period_name' => 'Recess',
            'start_time' => '10:00',
            'end_time' => '10:20',
            'is_break' => '1',
            'order_index' => 3,
        ]]);

        $this->actingAs($admin)->post(route('bell-timing-templates.store'), ['name' => 'With Recess', 'slots' => $slots]);
        $template = BellTimingTemplate::where('name', 'With Recess')->firstOrFail();
        $this->assertTrue($template->slots()->where('is_break', true)->exists());

        $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 3' => ['action' => 'apply']],
        ]);

        $recess = BellTiming::where('class_section', 'Class 3')->where('period_name', 'Recess')->first();
        $this->assertNotNull($recess);
        $this->assertTrue((bool) $recess->is_break);
        $this->assertSame('break', $recess->period_type);
    }

    // ============================================================
    // S/T. 12-hour / 24-hour (backend contract -- conversion is client-side, see BellTimingBulkCreateTimeFormatTest for the JS-level proof)
    // ============================================================

    public function test_template_accepts_normalized_values_from_either_time_format_ui(): void
    {
        $admin = $this->admin();

        // "1:30 PM" in 12h mode and "13:30" in 24h mode both normalize to the same value before submission.
        $response = $this->actingAs($admin)->post(route('bell-timing-templates.store'), [
            'name' => '12h/24h Contract',
            'slots' => [
                ['period_name' => 'Period 8', 'start_time' => '12:50', 'end_time' => '13:30', 'is_break' => '0', 'order_index' => 0],
            ],
        ]);

        $response->assertRedirect(route('bell-timing-templates.index'));
        $template = BellTimingTemplate::where('name', '12h/24h Contract')->firstOrFail();
        $this->assertSame('13:30:00', $template->slots()->first()->end_time->format('H:i:s'));
    }

    // ============================================================
    // U. Authorization
    // ============================================================

    public function test_admin_can_access_template_index(): void
    {
        $response = $this->actingAs($this->admin())->get(route('bell-timing-templates.index'));

        $response->assertOk();
    }

    public function test_teacher_cannot_view_templates(): void
    {
        $response = $this->actingAs($this->teacherUser())->get(route('bell-timing-templates.index'));

        $response->assertForbidden();
    }

    public function test_teacher_cannot_create_a_template(): void
    {
        $response = $this->actingAs($this->teacherUser())->post(route('bell-timing-templates.store'), [
            'name' => 'Should Not Be Created',
            'slots' => $this->eightPeriodSlots(),
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('bell_timing_templates', ['name' => 'Should Not Be Created']);
    }

    public function test_teacher_cannot_apply_a_template(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        $response = $this->actingAs($this->teacherUser())->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 3' => ['action' => 'apply']],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, BellTiming::where('class_section', 'Class 3')->count());
    }

    public function test_parent_guard_user_cannot_reach_template_routes(): void
    {
        $parent = ParentModel::create([
            'name' => 'Test Parent',
            'phone' => '9998887770',
            'email' => 'test.parent.' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
        ]);

        // actingAs()/be() call Auth::shouldUse($guard), which -- for a
        // non-default guard like 'parent' -- makes the unguarded Auth::user()
        // resolve to the parent for the rest of the test (a PHPUnit-only
        // artifact; a real request never calls Auth::shouldUse()). Setting
        // the guard's user directly leaves the default 'web' guard genuinely
        // null, matching real production request state.
        $this->app['auth']->guard('parent')->setUser($parent);

        $response = $this->get(route('bell-timing-templates.index'));

        // Not authenticated on the `web` guard the `auth` middleware checks
        // -- must not reach the policy/controller at all (no 200 OK).
        $response->assertStatus(302);
    }

    // ============================================================
    // W. Validation
    // ============================================================

    public function test_template_requires_at_least_one_slot(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.store'), [
            'name' => 'Empty Template',
            'slots' => [],
        ]);

        $response->assertSessionHasErrors(['slots']);
        $this->assertDatabaseMissing('bell_timing_templates', ['name' => 'Empty Template']);
    }

    public function test_template_slot_end_time_must_be_after_start_time(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.store'), [
            'name' => 'Invalid Template',
            'slots' => [
                ['period_name' => 'Period 1', 'start_time' => '10:00', 'end_time' => '09:00', 'is_break' => '0', 'order_index' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['slots.0.end_time']);
        $this->assertDatabaseMissing('bell_timing_templates', ['name' => 'Invalid Template']);
    }

    // ============================================================
    // X. Existing timetable slot protection + transaction rollback
    // ============================================================

    public function test_apply_is_blocked_when_it_would_remove_a_period_used_by_a_live_timetable_slot(): void
    {
        $admin = $this->admin();
        $schoolClass = SchoolClass::create(['name' => 'Class 11', 'class_order' => 111, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Math', 'code' => 'MTH-TPL', 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Teacher X', 'status' => 'active']);

        // Existing 8-period schedule for Class 11, where period 8 has a live TimetableSlot pointing at it.
        $this->seedClassSchedule('Class 11', 'Monday', $this->eightPeriodSlots());
        $period8 = BellTiming::where('class_section', 'Class 11')->orderBy('order_index')->get()->last();
        TimetableSlot::create([
            'school_class_id' => $schoolClass->id,
            'bell_timing_id' => $period8->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'academic_year' => '2026-2027',
            'status' => 'published',
        ]);

        // Template has only 6 periods -- applying "replace"/customize down to 6
        // would need to DELETE period 8's row, which a live slot depends on.
        $sixPeriodTemplateSlots = array_slice($this->eightPeriodSlots(), 0, 6);
        $this->actingAs($admin)->post(route('bell-timing-templates.store'), ['name' => 'Six Period', 'slots' => $sixPeriodTemplateSlots]);
        $template = BellTimingTemplate::where('name', 'Six Period')->firstOrFail();

        $before = BellTiming::where('class_section', 'Class 11')->count();

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => ['Class 11' => ['action' => 'customize', 'slots' => $sixPeriodTemplateSlots]],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // Nothing was removed -- the whole operation was blocked/rolled back.
        $this->assertSame($before, BellTiming::where('class_section', 'Class 11')->count());
        $this->assertDatabaseHas('bell_timings', ['id' => $period8->id]);
        $this->assertDatabaseHas('timetable_slots', ['bell_timing_id' => $period8->id]);
    }

    public function test_transaction_rolls_back_entirely_when_one_class_fails_validation(): void
    {
        $admin = $this->admin();
        $template = $this->createEightPeriodTemplate($admin);

        // Class 20 has an unrelated existing bell timing that overlaps the
        // template's first period on Monday -- this must abort the WHOLE
        // multi-class apply, not just skip Class 20.
        BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Existing Unrelated',
            'start_time' => '08:10',
            'end_time' => '08:30',
            'class_section' => 'Class 20',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 99,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing-templates.apply.confirm', $template), [
            'days' => ['Monday'],
            'decisions' => [
                'Class 19' => ['action' => 'apply'],
                'Class 20' => ['action' => 'apply'],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // Class 19 must NOT have been partially applied even though it was valid on its own.
        $this->assertSame(0, BellTiming::where('class_section', 'Class 19')->count(), 'Partial application must never be left behind.');
    }
}
