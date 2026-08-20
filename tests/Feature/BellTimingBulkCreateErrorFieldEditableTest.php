<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for: after a Bulk Create Bell Timings validation
 * failure, the invalid period's Hour/Minute number inputs became visually
 * unreadable/uneditable.
 *
 * Root cause (confirmed via live browser DOM inspection, not assumption):
 * Bootstrap's `.is-invalid` class adds a background-image (a red circled
 * exclamation icon) plus right-padding to `.form-control`. The Start/End
 * Time widgets' Hour/Minute inputs (resources/views/bell-timing/bulk-
 * create.blade.php: `.time-h`, `.time-m`) are only 4.2em wide -- at that
 * width the icon fully overlaps the 1-2 digit value, visually replacing it
 * with a red circle icon. The underlying `.value` was never actually
 * empty, disabled, or readonly at any point -- confirmed live via
 * `element.value`, `element.disabled`, `element.readOnly`, and
 * `element.validity.valid`, all correct throughout. The field was always
 * functionally editable; it only *looked* broken/blank, which is why the
 * user reported being unable to "properly edit" it.
 *
 * Fix: a CSS-only override (`background-image: none !important;
 * padding-right: 0.5rem !important;`) scoped to `.time-h.is-invalid`,
 * `.time-m.is-invalid`, `.time-ampm.is-invalid` -- the red border (already
 * present) plus the existing inline error message remain the invalid
 * indicator. No JavaScript, controller, or validation-rule change was
 * needed or made.
 *
 * Because the defect was purely visual (CSS), it cannot be directly
 * asserted by a PHPUnit HTTP test (no rendering engine here) -- these
 * tests instead assert two things: (1) the CSS fix is actually present in
 * the served page (a regression guard against it being silently removed),
 * and (2) the full "invalid submission -> corrected resubmission ->
 * success" round trip works end-to-end at the HTTP/validation layer for
 * several different period indexes, proving nothing about the recovery
 * workflow itself is broken. The visual fix itself was verified live in a
 * real browser (see the manual verification report).
 */
class BellTimingBulkCreateErrorFieldEditableTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function submitBulk(User $admin, array $periods, string $classSection = 'ErrorFieldEditable Class')
    {
        return $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => $classSection,
            'academic_year' => '2026-2027',
            'periods' => $periods,
        ]);
    }

    // Regression guard: the CSS fix must actually be present in the served page.
    public function test_the_invalid_state_background_icon_override_is_present(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('bell-timing.bulk-create'));

        $response->assertOk();
        $response->assertSee('.time-h.is-invalid, .time-m.is-invalid, .time-ampm.is-invalid', false);
        $response->assertSee('background-image: none !important', false);
    }

    // TEST 1/3 -- invalid periods.1, corrected AM/PM-equivalent value, succeeds.
    public function test_invalid_periods_1_can_be_corrected_and_resubmitted_successfully(): void
    {
        $admin = $this->makeAdmin();

        $invalid = $this->submitBulk($admin, [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '07:00', 'is_break' => '0', 'order_index' => 1],
        ]);
        $invalid->assertSessionHasErrors(['periods.1.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $corrected = $this->submitBulk($admin, [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => '0', 'order_index' => 1],
        ]);
        $corrected->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());
    }

    // TEST -- invalid periods.4 (a Recess row), corrected, succeeds.
    public function test_invalid_periods_4_recess_can_be_corrected_and_resubmitted_successfully(): void
    {
        $admin = $this->makeAdmin();

        $periodsInvalid = [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => '0', 'order_index' => 1],
            2 => ['period_name' => 'Period 2', 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => '0', 'order_index' => 2],
            3 => ['period_name' => 'Period 3', 'start_time' => '09:20', 'end_time' => '10:00', 'is_break' => '0', 'order_index' => 3],
            4 => ['period_name' => 'Recess', 'start_time' => '10:00', 'end_time' => '09:00', 'is_break' => '1', 'order_index' => 4],
        ];
        $invalid = $this->submitBulk($admin, $periodsInvalid);
        $invalid->assertSessionHasErrors(['periods.4.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $periodsInvalid[4]['end_time'] = '10:20';
        $corrected = $this->submitBulk($admin, $periodsInvalid);
        $corrected->assertRedirect(route('bell-timing.index'));
        $this->assertSame(4, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());
        $this->assertSame(1, BellTiming::where('class_section', 'ErrorFieldEditable Class')->where('is_break', true)->count());
    }

    // TEST 4 -- invalid periods.8 (the exact index from the original bug report), corrected, succeeds.
    public function test_invalid_periods_8_can_be_corrected_and_resubmitted_successfully(): void
    {
        $admin = $this->makeAdmin();

        $periods = [];
        for ($i = 1; $i <= 7; $i++) {
            $start = 480 + ($i - 1) * 40; // minutes from 08:00
            $end = $start + 40;
            $periods[$i] = [
                'period_name' => "Period $i",
                'start_time' => sprintf('%02d:%02d', intdiv($start, 60), $start % 60),
                'end_time' => sprintf('%02d:%02d', intdiv($end, 60), $end % 60),
                'is_break' => '0',
                'order_index' => $i,
            ];
        }
        // periods.8: starts right after period 7 ends (12:40), deliberately
        // invalid end time (before its own start), matching the bug report's
        // "end before start" shape without colliding with periods 1-7.
        $periods[8] = ['period_name' => 'Period 8', 'start_time' => '12:40', 'end_time' => '12:10', 'is_break' => '0', 'order_index' => 8];

        $invalid = $this->submitBulk($admin, $periods);
        $invalid->assertSessionHasErrors(['periods.8.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $periods[8]['end_time'] = '13:20';
        $corrected = $this->submitBulk($admin, $periods);
        $corrected->assertRedirect(route('bell-timing.index'));
        $this->assertSame(8, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());
    }

    // TEST 5 -- invalid periods.9 (the last row), corrected, succeeds.
    public function test_invalid_periods_9_can_be_corrected_and_resubmitted_successfully(): void
    {
        $admin = $this->makeAdmin();

        $periods = [
            1 => ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => '0', 'order_index' => 1],
            9 => ['period_name' => 'Period 8', 'start_time' => '12:50', 'end_time' => '01:30', 'is_break' => '0', 'order_index' => 9],
        ];

        $invalid = $this->submitBulk($admin, $periods);
        $invalid->assertSessionHasErrors(['periods.9.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $periods[9]['end_time'] = '13:30';
        $corrected = $this->submitBulk($admin, $periods);
        $corrected->assertRedirect(route('bell-timing.index'));
        $this->assertSame(2, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $p9 = BellTiming::where('class_section', 'ErrorFieldEditable Class')->where('period_name', 'Period 8')->first();
        $this->assertSame('13:30:00', $p9->end_time->format('H:i:s'));
    }

    // TEST 2 -- invalid 24-hour-mode value, corrected, succeeds. (Time format
    // is a client-side-only concern; by the time either mode reaches the
    // server both are already the same normalized HH:mm -- this proves the
    // 24-hour-mode correction path is not blocked by anything server-side.)
    public function test_invalid_24_hour_mode_value_can_be_corrected_and_resubmitted_successfully(): void
    {
        $admin = $this->makeAdmin();

        $invalid = $this->submitBulk($admin, [
            1 => ['period_name' => 'Period X', 'start_time' => '12:50', 'end_time' => '01:30', 'is_break' => '0', 'order_index' => 1],
        ]);
        $invalid->assertSessionHasErrors(['periods.1.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());

        $corrected = $this->submitBulk($admin, [
            1 => ['period_name' => 'Period X', 'start_time' => '12:50', 'end_time' => '13:30', 'is_break' => '0', 'order_index' => 1],
        ]);
        $corrected->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'ErrorFieldEditable Class')->count());
    }
}
