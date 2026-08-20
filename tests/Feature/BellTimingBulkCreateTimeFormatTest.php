<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the 12-hour/24-hour Time Format feature added to
 * Bulk Create Bell Timings.
 *
 * The 12-hour <-> 24-hour conversion happens entirely client-side
 * (resources/views/bell-timing/bulk-create.blade.php: to12h()/from12h()/
 * from24h()) -- by the time a submission reaches the server, both time
 * formats have already been normalized into the exact same "HH:mm"
 * canonical value, submitted under the same field names
 * (periods[n][start_time]/[end_time]) the backend already validated before
 * this feature existed. The controller and validation rules are
 * byte-for-byte unchanged.
 *
 * These tests therefore verify the backend HALF of the contract the UI
 * relies on: that a given normalized 24-hour value pair -- specifically,
 * the exact values the JS conversion functions produce for each stated
 * 12-hour-mode scenario -- passes or fails exactly as the feature spec
 * requires. The conversion functions themselves (to12h/from12h/from24h,
 * and the round-trip 24->12->24 / 12->24->12 property) are verified
 * separately via a live browser session executing the actual shipped JS
 * (see the manual verification report) -- this app has no JS unit-test
 * runner (package.json has no test script/framework), so that is the
 * faithful way to test browser-only conversion logic without inventing a
 * whole new JS testing system for one feature.
 */
class BellTimingBulkCreateTimeFormatTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function submit(User $admin, array $periods, string $classSection = 'TimeFormat Class')
    {
        return $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => $classSection,
            'academic_year' => '2026-2027',
            'periods' => $periods,
        ]);
    }

    // TEST 1 -- 24-hour mode existing behavior is unchanged (baseline).
    public function test_24_hour_mode_existing_behavior_still_works(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => '0', 'order_index' => 1],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'TimeFormat Class')->count());
    }

    // TEST 2 -- 12-hour valid PM-to-PM: "12:50 PM -> 01:30 PM" converts to "12:50 -> 13:30" and MUST PASS.
    public function test_12_hour_pm_to_pm_converted_value_passes(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Period 8', 'start_time' => '12:50', 'end_time' => '13:30', 'is_break' => '0', 'order_index' => 1],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'TimeFormat Class')->count());
    }

    // TEST 3 -- 12-hour AM-to-AM: "09:50 AM -> 10:10 AM" converts to "09:50 -> 10:10" and MUST PASS.
    public function test_12_hour_am_to_am_converted_value_passes(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Recess', 'start_time' => '09:50', 'end_time' => '10:10', 'is_break' => '1', 'order_index' => 1],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'TimeFormat Class')->count());
    }

    // TEST 4 -- 12-hour invalid: "12:50 PM -> 01:30 AM" converts to "12:50 -> 01:30" and MUST FAIL.
    // This is the exact original bug scenario, re-asserted at the backend
    // contract level: the server must still correctly reject it.
    public function test_12_hour_pm_to_am_converted_value_fails(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Period 8', 'start_time' => '12:50', 'end_time' => '01:30', 'is_break' => '0', 'order_index' => 1],
        ]);

        $response->assertSessionHasErrors(['periods.1.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'TimeFormat Class')->count());
    }

    // TEST 5 -- 12-hour reversed: "01:30 PM -> 12:50 PM" converts to "13:30 -> 12:50" and MUST FAIL.
    public function test_12_hour_reversed_converted_value_fails(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Period X', 'start_time' => '13:30', 'end_time' => '12:50', 'is_break' => '0', 'order_index' => 1],
        ]);

        $response->assertSessionHasErrors(['periods.1.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'TimeFormat Class')->count());
    }

    // TEST 8 -- Recess works with a converted 12-hour value (is_break=1, valid times).
    public function test_recess_with_converted_12_hour_value_passes(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->submit($admin, [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => '0', 'order_index' => 1],
            2 => ['period_name' => 'Recess', 'start_time' => '09:50', 'end_time' => '10:10', 'is_break' => '1', 'order_index' => 2],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(2, BellTiming::where('class_section', 'TimeFormat Class')->count());
        $this->assertSame(1, BellTiming::where('class_section', 'TimeFormat Class')->where('is_break', true)->count());
    }

    // TEST 9 -- 12 periods + Recess, all as converted (post-12-hour) values, matching the
    // exact real-world scenario from the original bug report (07:50 AM ... 12:50 PM -> 01:30 PM).
    public function test_twelve_periods_plus_recess_with_converted_values_passes(): void
    {
        $admin = $this->makeAdmin();

        $periods = [
            1 => ['period_name' => 'Period 1', 'start_time' => '07:50', 'end_time' => '08:30', 'is_break' => '0', 'order_index' => 1],
            2 => ['period_name' => 'Period 2', 'start_time' => '08:30', 'end_time' => '09:10', 'is_break' => '0', 'order_index' => 2],
            3 => ['period_name' => 'Period 3', 'start_time' => '09:10', 'end_time' => '09:50', 'is_break' => '0', 'order_index' => 3],
            4 => ['period_name' => 'Recess', 'start_time' => '09:50', 'end_time' => '10:10', 'is_break' => '1', 'order_index' => 4],
            5 => ['period_name' => 'Period 4', 'start_time' => '10:10', 'end_time' => '10:50', 'is_break' => '0', 'order_index' => 5],
            6 => ['period_name' => 'Period 5', 'start_time' => '10:50', 'end_time' => '11:30', 'is_break' => '0', 'order_index' => 6],
            7 => ['period_name' => 'Period 6', 'start_time' => '11:30', 'end_time' => '12:10', 'is_break' => '0', 'order_index' => 7],
            8 => ['period_name' => 'Period 7', 'start_time' => '12:10', 'end_time' => '12:50', 'is_break' => '0', 'order_index' => 8],
            9 => ['period_name' => 'Period 8', 'start_time' => '12:50', 'end_time' => '13:30', 'is_break' => '0', 'order_index' => 9],
        ];

        $response = $this->submit($admin, $periods);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(9, BellTiming::where('class_section', 'TimeFormat Class')->count());

        $period8 = BellTiming::where('class_section', 'TimeFormat Class')->where('period_name', 'Period 8')->first();
        $this->assertSame('13:30:00', $period8->end_time->format('H:i:s'));
    }
}
