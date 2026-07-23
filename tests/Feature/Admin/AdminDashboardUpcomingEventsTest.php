<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicEvent;
use App\Models\Role;
use App\Models\User;
use App\Services\ProfessionalDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardUpcomingEventsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_dashboard_shows_a_seeded_upcoming_event(): void
    {
        \Carbon\Carbon::setTestNow('2026-07-21 00:00:00');

        $admin = $this->makeAdmin();

        AcademicEvent::create([
            'title' => 'Founders Day Celebration',
            'type' => 'event',
            'start_date' => '2026-07-23',
            'end_date' => '2026-07-23',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Founders Day Celebration');
        $response->assertSee('Upcoming Events');
    }

    public function test_admin_dashboard_still_returns_200_when_dashboard_service_throws(): void
    {
        $admin = $this->makeAdmin();

        $this->mock(ProfessionalDashboardService::class, function ($mock) {
            $mock->shouldReceive('getUpcomingEvents')->andThrow(new \Exception('boom'));
        });

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('No upcoming events this week.');
    }
}
