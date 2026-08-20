<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defect fix: BellTimingController::edit() sourced the class_section
 * dropdown from Student::distinct('class'), not from bell_timings/
 * school_classes. When a BellTiming row's class_section had no matching
 * <option>, the select silently fell back to its first option ("All
 * Classes"), and saving the form blanked class_section to null. Fixed by
 * sourcing the edit-form dropdown from SchoolClass + the distinct
 * class_section values already used in bell_timings, so the row's own
 * value is always present.
 */
class BellTimingEditClassSectionOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_edit_dropdown_includes_class_section_absent_from_students(): void
    {
        $admin = $this->makeAdmin();
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'NoStudentsYetClass', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.edit', $bellTiming));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return $classSections->contains('NoStudentsYetClass');
        });
        $response->assertSee('NoStudentsYetClass', false);
    }

    public function test_edit_dropdown_includes_active_school_classes(): void
    {
        $admin = $this->makeAdmin();
        SchoolClass::create(['name' => 'EditOptionsSchoolClass', 'class_order' => 970001, 'is_active' => true]);
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'EditOptionsSchoolClass', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.edit', $bellTiming));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return $classSections->contains('EditOptionsSchoolClass');
        });
    }

    public function test_saving_the_edit_form_preserves_a_class_section_missing_from_students(): void
    {
        $admin = $this->makeAdmin();
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'PreservedClass', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->put(route('bell-timing.update', $bellTiming), [
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:40',
            'class_section' => 'PreservedClass',
            'order_index' => 1,
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame('PreservedClass', $bellTiming->fresh()->class_section);
    }
}
