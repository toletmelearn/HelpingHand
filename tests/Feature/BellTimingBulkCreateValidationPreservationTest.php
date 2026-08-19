<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Regression coverage for the Bulk Create Bell Timings usability defect:
 * when one period in a multi-period submission failed validation (e.g. an
 * end_time before its start_time), the user lost every period they had
 * already typed -- the Blade view never read old()/$errors for the
 * dynamically-built period rows, so a validation-failure reload always
 * rendered a single blank row regardless of what was flashed to the
 * session.
 *
 * Fix is Blade-only (resources/views/bell-timing/bulk-create.blade.php):
 * period rows, day checkboxes, and the class/year/semester selects are now
 * rendered server-side from old('periods')/old('days')/old(...), with
 * inline @error() feedback per field. Validation rules themselves are
 * unchanged.
 */
class BellTimingBulkCreateValidationPreservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function tenPeriodsWithOneInvalid(): array
    {
        $periods = [];
        $start = 8 * 60; // 08:00 in minutes
        for ($i = 1; $i <= 9; $i++) {
            $s = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
            $start += 40;
            $e = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);

            $periods[$i] = [
                'period_name' => "Period $i",
                'start_time' => $s,
                'end_time' => $e,
                'is_break' => '0',
                'order_index' => $i,
            ];
        }

        // Period 10: deliberately invalid -- end before start.
        $periods[10] = [
            'period_name' => 'Period 10',
            'start_time' => '11:00',
            'end_time' => '10:30',
            'is_break' => '0',
            'order_index' => 10,
        ];

        return $periods;
    }

    // TEST A -- invalid period is still rejected, no record created.
    public function test_invalid_end_time_is_still_rejected_and_nothing_is_saved(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => 'Preservation Class',
            'academic_year' => '2026-2027',
            'periods' => $this->tenPeriodsWithOneInvalid(),
        ]);

        $response->assertSessionHasErrors(['periods.10.end_time']);
        $this->assertSame(0, BellTiming::where('class_section', 'Preservation Class')->count());
    }

    // TEST D -- the error is associated with the exact field, not a vague top-level message.
    public function test_validation_error_identifies_the_exact_period_and_field(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => 'Preservation Class',
            'academic_year' => '2026-2027',
            'periods' => $this->tenPeriodsWithOneInvalid(),
        ]);

        $response->assertSessionHasErrors(['periods.10.end_time']);

        $errors = session('errors')->getBag('default');
        $this->assertSame(
            'The periods.10.end_time field must be a date after periods.10.start_time.',
            $errors->first('periods.10.end_time')
        );

        // Only period 10 is invalid -- periods 1-9 must not have been flagged.
        for ($i = 1; $i <= 9; $i++) {
            $this->assertFalse($errors->has("periods.$i.start_time"), "periods.$i.start_time should not have an error");
            $this->assertFalse($errors->has("periods.$i.end_time"), "periods.$i.end_time should not have an error");
        }
    }

    /**
     * TEST B/C -- simulates exactly what the Blade view now does: renders
     * the bulk-create GET page with the session in the same state Laravel
     * leaves it in after a failed $request->validate() redirect (old
     * input + $errors flashed). This isolates the fix itself from
     * Laravel's redirect/cookie plumbing, which this task did not change.
     */
    public function test_all_previously_entered_periods_and_selections_are_redisplayed_after_validation_failure(): void
    {
        $admin = $this->makeAdmin();

        // The Class/Section dropdown is populated from distinct Student::class
        // values (BellTimingController@bulkCreate GET branch) -- seed one so
        // "Preservation Class" is an option old() can mark selected.
        Student::create([
            'name' => 'Preservation Student',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01',
            'address' => 'Test Address',
            'phone' => '9000000000',
            'admission_no' => 'ADM-PRES-' . uniqid(),
            'class' => 'Preservation Class',
        ]);

        $periods = $this->tenPeriodsWithOneInvalid();

        $errorBag = new ViewErrorBag();
        $errorBag = $errorBag->put('default', new MessageBag([
            'periods.10.end_time' => ['The periods.10.end_time field must be a date after periods.10.start_time.'],
        ]));

        $response = $this->actingAs($admin)
            ->withSession([
                '_old_input' => [
                    'days' => ['Monday', 'Tuesday'],
                    'class_section' => 'Preservation Class',
                    'academic_year' => '2026-2027',
                    'semester' => 'First',
                    'periods' => $periods,
                ],
                'errors' => $errorBag,
            ])
            ->get(route('bell-timing.bulk-create'));

        $response->assertOk();

        // Days restored.
        $response->assertSee('name="days[]" value="Monday" id="day_Monday"', false);
        $response->assertSee('checked', false);

        // Class/year/semester restored.
        $response->assertSee('<option value="Preservation Class" selected>Preservation Class</option>', false);
        $response->assertSee('<option value="2026-2027" selected>2026-2027</option>', false);
        $response->assertSee('<option value="First" selected>First</option>', false);

        // All 10 periods present with their exact original values (valid AND invalid).
        for ($i = 1; $i <= 10; $i++) {
            $p = $periods[$i];
            $response->assertSee('id="period_' . $i . '"', false);
            $response->assertSee('name="periods[' . $i . '][period_name]"', false);
            $response->assertSee('value="' . $p['period_name'] . '"', false);
            $response->assertSee('value="' . $p['start_time'] . '"', false);
            $response->assertSee('value="' . $p['end_time'] . '"', false);
        }

        // Period 10's own (invalid) values are still exactly what was typed.
        $response->assertSee('name="periods[10][start_time]"', false);
        $response->assertSee('value="11:00"', false);
        $response->assertSee('name="periods[10][end_time]"', false);
        $response->assertSee('value="10:30"', false);

        // The exact error message is shown, inline.
        $response->assertSee('The periods.10.end_time field must be a date after periods.10.start_time.', false);
    }

    // TEST E -- correcting the invalid period allows successful submission.
    public function test_correcting_the_invalid_period_succeeds(): void
    {
        $admin = $this->makeAdmin();

        // Periods 1-9 already occupy 08:00-14:00 back-to-back (see
        // tenPeriodsWithOneInvalid()), so the correction must land after
        // that, not just satisfy end > start, or it would collide with
        // period 9 and be silently skipped as a conflict instead of
        // proving the validation-failure round-trip actually succeeds.
        $periods = $this->tenPeriodsWithOneInvalid();
        $periods[10]['start_time'] = '14:00';
        $periods[10]['end_time'] = '14:40'; // corrected: after start_time, and after period 9 ends

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => 'Preservation Class',
            'academic_year' => '2026-2027',
            'periods' => $periods,
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(10, BellTiming::where('class_section', 'Preservation Class')->count());
    }

    // TEST F -- existing valid bulk creation is unaffected by the fix.
    public function test_existing_valid_bulk_creation_still_works(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday', 'Tuesday'],
            'class_section' => 'Valid Class',
            'academic_year' => '2026-2027',
            'periods' => [
                1 => [
                    'period_name' => 'Period 1',
                    'start_time' => '09:00',
                    'end_time' => '09:45',
                    'is_break' => '0',
                    'order_index' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(2, BellTiming::where('class_section', 'Valid Class')->count());
    }

    // A genuinely fresh visit (no old input at all) still shows exactly one blank period row, as before.
    public function test_fresh_visit_with_no_old_input_still_shows_a_single_blank_period_row(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('bell-timing.bulk-create'));

        $response->assertOk();
        $response->assertSee('id="period_1"', false);
        $response->assertDontSee('id="period_2"', false);
    }
}
