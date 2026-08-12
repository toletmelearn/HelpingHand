<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pilot-hardening (Workspace authorization): index()/workspace() previously
 * only checked viewAny (role), so any authenticated teacher-role account
 * could view -- or, via ?status=draft, see the in-progress draft of -- ANY
 * class's grid by supplying an arbitrary school_class_id. This proves the
 * new buildGridViewData() authorization check (reusing the existing
 * viewClassTimetable ability / teacherAssignedToClassSection()) closes
 * that gap for both entry points while preserving admin's unrestricted
 * access and legitimate teacher access to their own class-sections.
 */
class TimetableWorkspaceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /** @return array{user: User, teacher: Teacher} */
    private function makeTeacherAccount(string $name): array
    {
        $user = User::factory()->create(['name' => $name]);
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);
        $teacher = Teacher::create(['name' => $name, 'status' => 'active', 'user_id' => $user->id]);

        return compact('user', 'teacher');
    }

    /** @return array{class: SchoolClass, section: Section, subject: Subject, timing: BellTiming} */
    private function seedClass(string $label): array
    {
        AcademicSession::firstOrCreate(
            ['code' => '2026-2027'],
            ['name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]
        );
        $timing = BellTiming::firstOrCreate(
            ['day_of_week' => 'Monday', 'period_name' => "P-{$label}"],
            ['start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => '2026-2027']
        );
        $class = SchoolClass::create(['name' => "Workspace Authz Class {$label}", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => $label]);
        $subject = Subject::create(['name' => "Workspace Authz Subject {$label}", 'code' => 'WSA' . $label . uniqid()]);

        return compact('class', 'section', 'subject', 'timing');
    }

    private function assignTeacher(Teacher $teacher, SchoolClass $class, Section $section, Subject $subject): void
    {
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'subject_id' => $subject->id, 'academic_year' => '2026-2027',
        ]);
    }

    // --- Assigned teacher: allowed ---

    public function test_a_teacher_can_open_the_index_grid_for_their_own_assigned_class_and_section(): void
    {
        $mine = $this->makeTeacherAccount('Assigned Grid Teacher');
        $data = $this->seedClass('Own');
        $this->assignTeacher($mine['teacher'], $data['class'], $data['section'], $data['subject']);

        $response = $this->actingAs($mine['user'])->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertOk();
    }

    public function test_a_teacher_can_open_the_workspace_review_tab_for_their_own_assigned_class(): void
    {
        $mine = $this->makeTeacherAccount('Assigned Workspace Teacher');
        $data = $this->seedClass('OwnWs');
        $this->assignTeacher($mine['teacher'], $data['class'], $data['section'], $data['subject']);

        $response = $this->actingAs($mine['user'])->get(route('timetable.workspace', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertOk();
    }

    // --- Unassigned teacher: denied ---

    public function test_a_teacher_cannot_open_the_index_grid_for_another_classs_section(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Grid Teacher');
        $owner = $this->makeTeacherAccount('Grid Owner Teacher');
        $data = $this->seedClass('Other');
        $this->assignTeacher($owner['teacher'], $data['class'], $data['section'], $data['subject']);

        $response = $this->actingAs($outsider['user'])->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertForbidden();
    }

    public function test_a_teacher_cannot_open_the_workspace_for_another_classs_section(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Workspace Teacher');
        $owner = $this->makeTeacherAccount('Workspace Owner Teacher');
        $data = $this->seedClass('OtherWs');
        $this->assignTeacher($owner['teacher'], $data['class'], $data['section'], $data['subject']);

        $response = $this->actingAs($outsider['user'])->get(route('timetable.workspace', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertForbidden();
    }

    public function test_a_teacher_cannot_open_a_wholly_unassigned_classs_grid_with_no_section_filter(): void
    {
        $outsider = $this->makeTeacherAccount('No Assignment Teacher');
        $data = $this->seedClass('Unassigned');

        $response = $this->actingAs($outsider['user'])->get(route('timetable.index', [
            'school_class_id' => $data['class']->id,
        ]));

        $response->assertForbidden();
    }

    // --- Draft grid: same boundary ---

    public function test_a_teacher_cannot_view_another_classs_draft_grid_via_status_draft(): void
    {
        $outsider = $this->makeTeacherAccount('Draft Outsider Teacher');
        $owner = $this->makeTeacherAccount('Draft Owner Teacher');
        $data = $this->seedClass('Draft');
        $this->assignTeacher($owner['teacher'], $data['class'], $data['section'], $data['subject']);
        TimetableSlot::create([
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id, 'bell_timing_id' => $data['timing']->id,
            'subject_id' => $data['subject']->id, 'teacher_id' => $owner['teacher']->id, 'status' => TimetableSlot::STATUS_DRAFT,
            'academic_year' => '2026-2027',
        ]);

        $response = $this->actingAs($outsider['user'])->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id, 'status' => 'draft',
        ]));

        $response->assertForbidden();
    }

    public function test_the_assigned_teacher_can_still_view_their_own_classs_draft_grid(): void
    {
        $mine = $this->makeTeacherAccount('Draft Self Teacher');
        $data = $this->seedClass('DraftSelf');
        $this->assignTeacher($mine['teacher'], $data['class'], $data['section'], $data['subject']);
        TimetableSlot::create([
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id, 'bell_timing_id' => $data['timing']->id,
            'subject_id' => $data['subject']->id, 'teacher_id' => $mine['teacher']->id, 'status' => TimetableSlot::STATUS_DRAFT,
            'academic_year' => '2026-2027',
        ]);

        $response = $this->actingAs($mine['user'])->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id, 'status' => 'draft',
        ]));

        $response->assertOk();
    }

    // --- Cannot bypass by changing school_class_id ---

    public function test_a_teacher_assigned_to_one_class_cannot_bypass_authorization_by_switching_school_class_id(): void
    {
        $mine = $this->makeTeacherAccount('Switcher Teacher');
        $ownData = $this->seedClass('SwitcherOwn');
        $this->assignTeacher($mine['teacher'], $ownData['class'], $ownData['section'], $ownData['subject']);
        $otherData = $this->seedClass('SwitcherOther');

        // Their own class: allowed.
        $this->actingAs($mine['user'])->get(route('timetable.index', [
            'school_class_id' => $ownData['class']->id, 'section_id' => $ownData['section']->id,
        ]))->assertOk();

        // Simply changing school_class_id to a class they have no
        // assignment for must not bypass the check.
        $this->actingAs($mine['user'])->get(route('timetable.index', [
            'school_class_id' => $otherData['class']->id, 'section_id' => $otherData['section']->id,
        ]))->assertForbidden();
    }

    // --- Admin: unrestricted ---

    public function test_admin_can_still_open_the_index_grid_for_any_class(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedClass('AdminGrid');

        $response = $this->actingAs($admin)->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_still_open_the_workspace_for_any_class_including_draft(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedClass('AdminWs');

        $response = $this->actingAs($admin)->get(route('timetable.workspace', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id, 'status' => 'draft',
        ]));

        $response->assertOk();
    }

    // --- No class selected: pickers/empty states unaffected ---

    public function test_index_with_no_school_class_id_still_renders_the_picker_for_a_teacher(): void
    {
        $mine = $this->makeTeacherAccount('Picker Only Teacher');

        $response = $this->actingAs($mine['user'])->get(route('timetable.index'));

        $response->assertOk();
    }

    public function test_workspace_with_no_school_class_id_still_renders_for_a_teacher(): void
    {
        $mine = $this->makeTeacherAccount('Picker Only Workspace Teacher');

        $response = $this->actingAs($mine['user'])->get(route('timetable.workspace'));

        $response->assertOk();
    }
}
