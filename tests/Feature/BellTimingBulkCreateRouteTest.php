<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B9: POST bell-timing/bulk-create pointed at a nonexistent
 * processBulkCreate method (fatal BadMethodCallException on every hit).
 * bulkCreate() already handles both GET (show form) and POST (create the
 * records) internally via $request->isMethod('get') -- repointed the POST
 * route to it instead of adding a new method.
 */
class BellTimingBulkCreateRouteTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_get_still_shows_the_bulk_create_form(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('bell-timing.bulk-create'))->assertOk();
    }

    public function test_post_creates_bell_timings_instead_of_fataling(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday', 'Tuesday'],
            'class_section' => 'Class 5',
            'academic_year' => '2026-2027',
            'periods' => [
                [
                    'period_name' => 'Period 1',
                    'start_time' => '09:00',
                    'end_time' => '09:45',
                    'is_break' => false,
                    'order_index' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(2, BellTiming::where('class_section', 'Class 5')->count());
    }

    /**
     * Same root cause, discovered while writing the smoke test above:
     * Route::resource() was registered before these literal-path routes,
     * so its show route (GET bell-timing/{bell_timing}) matched first and
     * swallowed weekly/daily/bulk-create/bare-print as if they were IDs,
     * 404ing on the failed model lookup. Reordered registration to put
     * the literal routes first.
     */
    public function test_weekly_route_no_longer_collides_with_show(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('bell-timing.weekly'))->assertOk();
    }

    public function test_daily_route_no_longer_collides_with_show(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('bell-timing.daily'))->assertOk();
    }

    public function test_show_route_still_resolves_a_real_record(): void
    {
        $admin = $this->makeAdmin();
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '09:00',
            'end_time' => '09:45',
            'class_section' => 'Class 5',
            'is_active' => true,
            'order_index' => 1,
        ]);

        $this->actingAs($admin)->get(route('bell-timing.show', $bellTiming))->assertOk();
    }
}
