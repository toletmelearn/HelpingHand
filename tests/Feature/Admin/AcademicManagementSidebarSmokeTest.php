<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Smoke-tests every route linked from the sidebar's "Academic Management"
 * dropdown by actually requesting it as an authenticated admin, rather than
 * only checking that the route name resolves. Route-matching can succeed
 * while the underlying view/controller still 500s on real (or empty) data --
 * this catches that class of bug and guards against it regressing later.
 */
class AcademicManagementSidebarSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);
    }

    #[DataProvider('academicRouteProvider')]
    public function test_academic_management_route_does_not_error(string $routeName): void
    {
        if (! \Route::has($routeName)) {
            $this->markTestSkipped("Route [$routeName] is not registered.");
        }

        $response = $this->actingAs($this->adminUser)->get(route($routeName));

        $this->assertLessThan(
            500,
            $response->getStatusCode(),
            "GET " . route($routeName) . " ($routeName) returned a server error: " . $response->getStatusCode()
        );
    }

    public static function academicRouteProvider(): array
    {
        return [
            // Renders the full sidebar partial -- the strongest single check that every
            // route()/Route::has() call added to the Academic Management dropdown is valid.
            ['admin.dashboard'],
            ['admin.school-classes.index'],
            ['admin.sections.index'],
            ['admin.subjects.index'],
            ['admin.academic-sessions.index'],
            ['admin.timetable.index'],
            ['admin.syllabi.index'],
            ['admin.syllabi.progress-report'],
            ['admin.daily-teaching-work.index'],
            ['admin.lesson-plans.index'],
            ['admin.lesson-plans.compliance'],
            ['admin.lesson-plans.subject-progress'],
            ['admin.lesson-plans.export-pdf'],
            ['admin.professional-lesson-plans.index'],
            ['admin.homework.index'],
            ['admin.professional-homework.index'],
            ['admin.homework-notices.index'],
            ['admin.student-promotions.index'],
            ['admin.class-teacher-control.student-records'],
            ['admin.teacher-subject-assignments.index'],
            ['admin.teacher-class-assignments.index'],
            ['admin.class-teacher-assignments.index'],
            ['admin.bell-schedules.index'],
            ['admin.bell-schedules.live-monitor'],
            ['admin.special-day-overrides.index'],
            ['admin.grading-systems.index'],
            ['admin.examination-patterns.index'],
        ];
    }
}
