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
 * match its own controller's policy exactly -- so a visible link never
 * leads to a 403.
 *
 * Sidebar cleanup: "Set Up Timetable", "Timetable", "Generate Timetable
 * (Beta)", and "Timetable Feasibility" used to be four separate tabs here.
 * They're now a single "Timetable Editor" entry (pointing at the grid,
 * timetable.index) -- the other three routes/controllers/views are NOT
 * removed, just no longer independently linked from the sidebar, so this
 * test still confirms they're reachable directly while asserting their old
 * standalone labels are gone.
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
        $response->assertSee('Timetable Editor');

        // The old standalone tabs are gone -- exactly one timetable entry,
        // not four.
        $response->assertDontSee('Set Up Timetable');
        $response->assertDontSee('Generate Timetable (Beta)');
        $response->assertDontSee('Timetable Feasibility');

        foreach ([
            'admin.teacher-subject-assignments.index',
            'admin.teacher-class-assignments.index',
            'teacher-availability.index',
            'combined-class-groups.index',
            'bell-timing.index',
            // Phase B: the sidebar now points here, not timetable.index directly.
            'timetable.workspace',
            // Still fully functional and directly reachable, even though only
            // the workspace is linked from the sidebar now.
            'timetable.index',
            'timetable.wizard.index',
            'timetable.generate.form',
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
        $response->assertSee('Timetable Editor');
        $response->assertDontSee('Set Up Timetable');
        $response->assertDontSee('Generate Timetable (Beta)');
        $response->assertDontSee('Timetable Feasibility');
        $this->actingAs($teacher)->get(route('teacher-availability.index'))->assertOk();
        $this->actingAs($teacher)->get(route('combined-class-groups.index'))->assertOk();
        $this->actingAs($teacher)->get(route('bell-timing.index'))->assertOk();
        // Phase B: the sidebar now points here, not timetable.index directly.
        $this->actingAs($teacher)->get(route('timetable.workspace'))->assertOk();
        $this->actingAs($teacher)->get(route('timetable.index'))->assertOk();
        // Not linked from the sidebar (admin-only, unchanged), but the route
        // itself must still correctly forbid a teacher -- not vanish.
        $this->actingAs($teacher)->get(route('timetable.wizard.index'))->assertForbidden();
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
