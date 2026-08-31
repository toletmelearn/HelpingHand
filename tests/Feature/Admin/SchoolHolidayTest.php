<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SchoolHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolHolidayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_can_create_holiday(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.school-holidays.store'), [
            'academic_year' => '2026-27',
            'holiday_name' => 'Diwali Break',
            'start_date' => '2026-11-10',
            'end_date' => '2026-11-14',
            'holiday_type' => 'festival',
        ]);

        $response->assertRedirect(route('admin.school-holidays.index'));
        $this->assertDatabaseHas('school_holidays', ['holiday_name' => 'Diwali Break', 'created_by' => $admin->id]);
    }

    public function test_admin_can_edit_holiday(): void
    {
        $admin = $this->admin();
        $holiday = SchoolHoliday::create([
            'academic_year' => '2026-27', 'holiday_name' => 'Winter Break',
            'start_date' => '2026-12-20', 'end_date' => '2026-12-31', 'holiday_type' => 'leave',
        ]);

        $response = $this->actingAs($admin)->put(route('admin.school-holidays.update', $holiday->id), [
            'holiday_name' => 'Winter Vacation',
            'start_date' => '2026-12-20', 'end_date' => '2027-01-02', 'holiday_type' => 'leave',
        ]);

        $response->assertRedirect(route('admin.school-holidays.index'));
        $this->assertDatabaseHas('school_holidays', ['id' => $holiday->id, 'holiday_name' => 'Winter Vacation']);
    }

    public function test_admin_can_delete_holiday(): void
    {
        $admin = $this->admin();
        $holiday = SchoolHoliday::create([
            'academic_year' => '2026-27', 'holiday_name' => 'Republic Day',
            'start_date' => '2027-01-26', 'end_date' => '2027-01-26', 'holiday_type' => 'special',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.school-holidays.destroy', $holiday->id));

        $response->assertRedirect(route('admin.school-holidays.index'));
        $this->assertDatabaseMissing('school_holidays', ['id' => $holiday->id]);
    }

    public function test_is_holiday_on_returns_true_for_holiday_date(): void
    {
        SchoolHoliday::create([
            'academic_year' => '2026-27', 'holiday_name' => 'Diwali',
            'start_date' => '2026-12-25', 'end_date' => '2026-12-27', 'holiday_type' => 'festival',
        ]);

        $this->assertTrue(SchoolHoliday::isHolidayOn('2026-12-25'));
        $this->assertTrue(SchoolHoliday::isHolidayOn('2026-12-26'));
        $this->assertTrue(SchoolHoliday::isHolidayOn('2026-12-27'));
    }

    public function test_is_holiday_on_returns_false_for_non_holiday(): void
    {
        SchoolHoliday::create([
            'academic_year' => '2026-27', 'holiday_name' => 'Diwali',
            'start_date' => '2026-12-25', 'end_date' => '2026-12-27', 'holiday_type' => 'festival',
        ]);

        $this->assertFalse(SchoolHoliday::isHolidayOn('2026-12-24'));
        $this->assertFalse(SchoolHoliday::isHolidayOn('2026-12-28'));
    }

    public function test_get_holidays_in_range(): void
    {
        SchoolHoliday::create(['academic_year' => '2026-27', 'holiday_name' => 'A', 'start_date' => '2026-11-10', 'end_date' => '2026-11-14', 'holiday_type' => 'festival']);
        SchoolHoliday::create(['academic_year' => '2026-27', 'holiday_name' => 'B', 'start_date' => '2027-03-01', 'end_date' => '2027-03-02', 'holiday_type' => 'leave']);

        $found = SchoolHoliday::getHolidaysInRange('2026-01-01', '2026-12-31');

        $this->assertCount(1, $found);
        $this->assertSame('A', $found->first()->holiday_name);
    }

    public function test_only_admin_can_manage_holidays(): void
    {
        $clerk = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $clerk->roles()->attach($role->id);

        $this->actingAs($clerk)->get(route('admin.school-holidays.index'))->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.school-holidays.index'))->assertOk();
    }

    public function test_holiday_validation_rejects_end_before_start(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.school-holidays.store'), [
            'academic_year' => '2026-27', 'holiday_name' => 'Bad Holiday',
            'start_date' => '2026-12-10', 'end_date' => '2026-12-05', 'holiday_type' => 'leave',
        ]);

        $response->assertSessionHasErrors('end_date');
        $this->assertDatabaseMissing('school_holidays', ['holiday_name' => 'Bad Holiday']);
    }

    public function test_single_day_holiday_is_valid(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.school-holidays.store'), [
            'academic_year' => '2026-27', 'holiday_name' => 'Republic Day',
            'start_date' => '2027-01-26', 'end_date' => '2027-01-26', 'holiday_type' => 'special',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_holidays', ['holiday_name' => 'Republic Day']);
    }

    public function test_cannot_create_duplicate_holiday_name_in_same_academic_year(): void
    {
        $admin = $this->admin();
        SchoolHoliday::create(['academic_year' => '2026-27', 'holiday_name' => 'Diwali', 'start_date' => '2026-11-10', 'end_date' => '2026-11-14', 'holiday_type' => 'festival']);

        $response = $this->actingAs($admin)->post(route('admin.school-holidays.store'), [
            'academic_year' => '2026-27', 'holiday_name' => 'Diwali',
            'start_date' => '2026-11-20', 'end_date' => '2026-11-21', 'holiday_type' => 'festival',
        ]);

        $response->assertSessionHasErrors('holiday_name');
        $this->assertSame(1, SchoolHoliday::where('holiday_name', 'Diwali')->count());
    }
}
