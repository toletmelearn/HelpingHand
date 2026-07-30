<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Navigation gap fix: Teacher-Subject Assignment, Class Teacher Assignment,
 * Teacher Availability, Combined Class Groups, Bell Timings, and Timetable
 * either had no sidebar link at all, or were buried in an unrelated System
 * Configuration section. Moved into Academic Management, each gated to
 * match its own controller's policy exactly -- same rule as the existing
 * Timetable Feasibility link -- so a visible link never leads to a 403.
 */
class SidebarAcademicAssignmentLinksTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_sees_every_newly_linked_page_and_none_403(): void
    {
        $admin = $this->makeUserWithRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Teacher-Subject Assignment');
        $response->assertSee('Class Teacher Assignment');
        $response->assertSee('Teacher Availability');
        $response->assertSee('Combined Class Groups');
        $response->assertSee('Bell Timings');
        $response->assertSee('Timetable');
        $response->assertSee('Timetable Feasibility');

        foreach ([
            'admin.teacher-subject-assignments.index',
            'admin.teacher-class-assignments.index',
            'teacher-availability.index',
            'combined-class-groups.index',
            'bell-timing.index',
            'timetable.index',
            'timetable.feasibility',
        ] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }

    public function test_teacher_sees_teacher_gated_links_but_not_staff_only_ones(): void
    {
        $teacher = $this->makeUserWithRole('teacher');

        $response = $this->actingAs($teacher)->get(route('admin.dashboard'));

        $response->assertOk();
        // Teacher-role-gated pages: visible, and reachable without a 403.
        $response->assertSee('Teacher Availability');
        $response->assertSee('Combined Class Groups');
        $response->assertSee('Bell Timings');
        $response->assertSee('Timetable');
        $response->assertSee('Timetable Feasibility');
        $this->actingAs($teacher)->get(route('teacher-availability.index'))->assertOk();
        $this->actingAs($teacher)->get(route('combined-class-groups.index'))->assertOk();
        $this->actingAs($teacher)->get(route('bell-timing.index'))->assertOk();
        $this->actingAs($teacher)->get(route('timetable.index'))->assertOk();
        $this->actingAs($teacher)->get(route('timetable.feasibility'))->assertOk();

        // Staff-only / permission-gated pages: link hidden, and the
        // underlying route genuinely forbids this teacher -- proves the
        // link's absence matches real unauthorized access, not a guess.
        $response->assertDontSee('Class Teacher Assignment');
        $response->assertDontSee('Teacher-Subject Assignment');
        $this->actingAs($teacher)->get(route('admin.teacher-class-assignments.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.teacher-subject-assignments.index'))->assertForbidden();
    }

    public function test_staff_sees_class_teacher_assignment_link(): void
    {
        $staff = $this->makeUserWithRole('staff');

        $response = $this->actingAs($staff)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Class Teacher Assignment');
        $this->actingAs($staff)->get(route('admin.teacher-class-assignments.index'))->assertOk();
    }
}
