<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\Timetable\SubstitutionDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T5 item 4: today's substitution count + unfilled arrangements on the
 * admin dashboard. Same try/catch degrade pattern as the existing
 * upcoming-events card (AdminDashboardUpcomingEventsTest) -- a service
 * hiccup here must never break the whole dashboard.
 */
class AdminDashboardSubstitutionCardTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_dashboard_shows_todays_substitution_count(): void
    {
        $admin = $this->makeAdmin();

        $this->mock(SubstitutionDashboardService::class, function ($mock) {
            $mock->shouldReceive('getTodaysSummary')->andReturn(['count' => 4, 'unfilled' => 1]);
        });

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Substitutions Today');
        $response->assertSee('4');
        $response->assertSee('1 unfilled');
    }

    public function test_dashboard_still_returns_200_when_substitution_service_throws(): void
    {
        $admin = $this->makeAdmin();

        $this->mock(SubstitutionDashboardService::class, function ($mock) {
            $mock->shouldReceive('getTodaysSummary')->andThrow(new \Exception('boom'));
        });

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Substitutions Today');
    }
}
