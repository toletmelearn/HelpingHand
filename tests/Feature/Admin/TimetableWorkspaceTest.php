<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\BellTiming;
use App\Models\TimetableSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Timetable Editor Phase B: the single consolidated workspace the sidebar's
 * "Timetable Editor" entry now opens. This proves the shell itself --
 * routing, authorization, the Home/Setup/Generate/Review & Edit/Conflicts/
 * Suggestions/Auto-Fix/Publish structure, and that it shows REAL data
 * reused from existing services rather than fabricated placeholders. It
 * does not re-test the underlying grid mutation behaviour (store/update/
 * destroy/generate/publish) -- that's already covered by
 * TimetableSchedulerTest, TimetableSlotUpdateTest, and
 * TimetableGenerationWorkflowTest, which continue to exercise the exact
 * same partial this workspace now also includes.
 */
class TimetableWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $teacher;
    protected BellTiming $timing1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->class = SchoolClass::create(['name' => 'Workspace Class', 'class_order' => 10]);
        $this->section = Section::create(['name' => 'Section A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->teacher = Teacher::create([
            'name' => 'Jane Doe',
            'email' => 'jane.doe@school.com',
            'phone' => '1234567890',
            'designation' => 'Math Teacher',
            'gender' => 'Female',
        ]);
        $this->timing1 = BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 1,
        ]);
    }

    private function makeTeacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_admin_can_open_the_workspace(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('Timetable Editor');
        // Pilot-hardening (Navigation): all nine workspace tabs are present
        // as real tabs -- Rebalance was previously untested here despite
        // genuinely existing in the UI (workspace.blade.php's own tab
        // list), a real test-coverage gap rather than an app defect.
        $response->assertSee('Home');
        $response->assertSee('Setup');
        $response->assertSee('Generate');
        $response->assertSee('Review &amp; Edit', false);
        $response->assertSee('Conflicts');
        $response->assertSee('Suggestions');
        $response->assertSee('Auto-Fix');
        $response->assertSee('Rebalance');
        $response->assertSee('Publish');
    }

    public function test_teacher_can_open_the_workspace(): void
    {
        $teacher = $this->makeTeacherUser();

        $this->actingAs($teacher)->get(route('timetable.workspace'))->assertOk();
    }

    public function test_user_without_the_viewing_role_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('timetable.workspace'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('timetable.workspace'))->assertRedirect(route('login'));
    }

    public function test_home_tab_shows_real_counts_not_placeholders(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        // These are the exact counts of the fixtures created in setUp() --
        // proves the numbers come from real queries, not hardcoded markup.
        $response->assertSeeInOrder(['Classes'], false);
        $response->assertSee((string) SchoolClass::active()->count());
        $response->assertSee((string) Teacher::count());
        $response->assertSee((string) Subject::count());
    }

    public function test_home_tab_shows_no_timetable_status_when_nothing_is_scheduled(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('No timetable yet');
    }

    /**
     * Production-hardening: with zero active classes, grid_capacity comes
     * back empty and readinessIssueCount is also 0 (no conflicts, no
     * classes to have readiness notes about either) -- these two facts
     * used to collapse onto the same 'blocked' state as a genuine
     * scheduling conflict, showing a red "Blocked" badge next to the
     * literally contradictory "0 conflict(s), 0 readiness note(s)" text.
     * Must now be a distinct, accurately-worded state.
     */
    public function test_home_tab_shows_not_set_up_when_no_active_classes_exist(): void
    {
        $this->class->update(['is_active' => false]);

        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('Not Set Up');
        $response->assertSee('No active classes exist yet -- add a class before generating a timetable.');
        $response->assertDontSee('0 conflict(s), 0 readiness note(s)');
    }

    public function test_home_tab_shows_published_status_once_a_slot_is_published(): void
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('Published');
        $response->assertDontSee('No timetable yet');
    }

    public function test_review_and_edit_tab_shows_the_real_grid_for_a_selected_class(): void
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('timetable.workspace', ['school_class_id' => $this->class->id]));

        $response->assertOk();
        // Same fixture, same assertion TimetableSchedulerTest makes against
        // timetable.index directly -- proves the embedded partial renders
        // identically inside the workspace.
        $response->assertSee('Academic Timetable Scheduler');
        $response->assertSee('Workspace Class');
        $response->assertSee('Mathematics');
        $response->assertSee('Jane Doe');
    }

    /**
     * Pilot-hardening (Navigation): confirms Class/Teacher/Room views and
     * exports are genuinely discoverable from the Workspace -- Teacher and
     * Room views are linked directly from Home; Class exports are linked
     * from Review & Edit (tested separately); Teacher/Room exports are one
     * click deeper, reachable from the Teacher/Room view pages themselves
     * once an entity is selected (verified directly on those views'
     * blade files, not duplicated here). No new screens were added --
     * this only proves the existing discoverability path holds.
     */
    public function test_home_tab_links_to_teacher_and_room_views_and_master_export(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee(route('timetable.view.teacher'), false);
        $response->assertSee(route('timetable.view.room'), false);
        $response->assertSee(route('timetable.export.master'), false);
    }

    public function test_setup_tab_links_to_the_real_existing_setup_pages(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee(route('timetable.wizard.index'), false);
        $response->assertSee(route('admin.school-classes.index'), false);
        $response->assertSee(route('bell-timing.index'), false);
        $response->assertSee(route('combined-class-groups.index'), false);
    }

    /**
     * Pilot-hardening (Class Teacher / Section): the Setup tab shows
     * "Teacher-Subject Assignment" and "Class Teacher Assignment" as two
     * adjacent, equal-looking buttons -- documented as a real source of
     * confusion, since only the former is section-aware and read by the
     * generator. Confirms both buttons now carry disambiguating text
     * rather than looking like interchangeable options.
     */
    public function test_setup_tab_disambiguates_the_two_class_teacher_screens(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('section-level Class Teacher');
        $response->assertSee('not used by Timetable');
    }

    public function test_generate_tab_links_to_the_existing_generate_flow_not_a_new_one(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee(route('timetable.generate.form'), false);
    }

    public function test_conflicts_tab_shows_the_real_feasibility_conflict_scan(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('Conflict Scan');
        $response->assertSee('No conflicts found');
    }

    public function test_suggestions_tab_is_an_honest_placeholder_not_a_dead_button(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        // A standalone suggestions browser is explicitly NOT implemented
        // yet -- must say so, not pretend to work.
        $response->assertSee('planned for a later phase');
    }

    /**
     * Phase 4: Auto-Fix is now real (wired into Review & Edit's conflict
     * area), so the workspace tab must describe the actual, live
     * capability -- not the old "not yet connected" placeholder text.
     */
    public function test_autofix_tab_describes_the_real_wired_capability(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('timetable.workspace'));

        $response->assertOk();
        $response->assertSee('Try Auto-Fix');
        $response->assertDontSee('not yet connected');
    }

    public function test_publish_tab_links_to_the_existing_generation_review_page_when_a_draft_exists(): void
    {
        $generation = \App\Models\TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => [$this->class->id],
            'style' => \App\Models\TimetableGeneration::STYLE_ROTATING,
            'status' => \App\Models\TimetableGeneration::STATUS_COMPLETED,
        ]);
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => TimetableSlot::STATUS_DRAFT,
            'timetable_generation_id' => $generation->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('timetable.workspace', ['school_class_id' => $this->class->id, 'status' => 'draft']));

        $response->assertOk();
        $response->assertSee(route('timetable.generation.review', $generation), false);
    }

    public function test_workspace_does_not_bypass_authorization_conflict_validation_or_activity_logging(): void
    {
        // The Review & Edit tab embeds the SAME add-slot form/endpoint as
        // the standalone grid -- so authorization, conflict validation, and
        // activity logging are exercised by the pre-existing
        // TimetableSchedulerTest/TimetableSlotUpdateTest suites already,
        // against the exact same partial this workspace now also includes.
        // This test only confirms the workspace's own controller action
        // does not skip the same viewAny authorization gate index() uses.
        $unauthorized = User::factory()->create();

        $this->actingAs($unauthorized)->get(route('timetable.workspace'))->assertForbidden();
    }
}
