<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT Test 14 defect fix: BellTimingController::index()/create() sourced
 * the Class/Section dropdown from Student::distinct('class') instead of
 * the canonical SchoolClass list -- a schedule correctly created (e.g. via
 * the Bell Template bulk-apply feature) for a class with no matching
 * students.class string was completely unfilterable/unmanageable, even
 * though the data existed. Fixed by applying the same
 * SchoolClass + existing bell_timings.class_section pattern already
 * proven (with its own test) on edit().
 */
class BellTimingIndexCreateClassSectionOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_index_dropdown_includes_school_class_absent_from_students(): void
    {
        $admin = $this->makeAdmin();
        SchoolClass::create(['name' => 'IndexOptionsSchoolClass', 'class_order' => 970101, 'is_active' => true]);
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'IndexOptionsSchoolClass', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.index'));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return $classSections->contains('IndexOptionsSchoolClass');
        });
    }

    public function test_index_dropdown_does_not_depend_on_students_class(): void
    {
        $admin = $this->makeAdmin();
        // A students.class value that is NOT a SchoolClass name and has no
        // matching BellTiming -- must NOT leak into the dropdown just
        // because a student happens to use it.
        Student::create([
            'name' => 'Test Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'address' => 'Test Address', 'phone' => '9999999999',
            'class' => 'ZZZ-Not-A-Real-Class',
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.index'));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return ! $classSections->contains('ZZZ-Not-A-Real-Class');
        });
    }

    public function test_index_dropdown_preserves_legacy_class_section_with_no_matching_school_class(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'class_section' => 'LegacyOrphanClassSection', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('bell-timing.index'));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return $classSections->contains('LegacyOrphanClassSection');
        });
    }

    public function test_create_dropdown_includes_school_class_absent_from_students(): void
    {
        $admin = $this->makeAdmin();
        SchoolClass::create(['name' => 'CreateOptionsSchoolClass', 'class_order' => 970102, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('bell-timing.create'));

        $response->assertOk();
        $response->assertViewHas('classSections', function ($classSections) {
            return $classSections->contains('CreateOptionsSchoolClass');
        });
    }
}
