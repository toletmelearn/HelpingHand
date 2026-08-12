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
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pilot-hardening (Authorization): TimetableSlotPolicy::viewAny() only
 * checks role (admin or teacher), which every read-only single-entity
 * timetable action -- teacher/class PDF+Excel exports, the interactive
 * teacher view, and generation review/status -- previously relied on as
 * its ONLY gate. That meant any authenticated admin-panel account holding
 * the teacher role could view/print/export ANY other teacher's or
 * unassigned class's published timetable, or ANY generation's review
 * data, just by passing an arbitrary id -- a confirmed IDOR (see
 * docs/timetable-system-documentation.md section 15).
 *
 * This proves the new viewTeacherTimetable/viewClassTimetable/
 * viewGenerationReview abilities close that gap for teacher-role accounts
 * while preserving admin's unrestricted access, reusing the exact same
 * teacherAssignedToClassSection() ownership check the write side already
 * relies on -- no new ownership model was introduced.
 */
class TimetableReadAuthorizationTest extends TestCase
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

    /** @return array{class: SchoolClass, section: Section, teacher: Teacher, subject: Subject, slot: TimetableSlot} */
    private function seedPublishedSlotFor(Teacher $teacher, string $label): array
    {
        AcademicSession::firstOrCreate(
            ['code' => '2026-2027'],
            ['name' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true]
        );
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => "P-{$label}", 'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => '2026-2027',
        ]);
        $class = SchoolClass::create(['name' => "Read-Auth Class {$label}", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => $label]);
        $subject = Subject::create(['name' => "Read-Auth Subject {$label}", 'code' => 'RA' . $label . uniqid()]);

        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'academic_year' => '2026-2027',
        ]);

        return compact('class', 'section', 'teacher', 'subject', 'slot');
    }

    // --- Teacher PDF/Excel/interactive view: cross-teacher IDOR ---

    public function test_a_teacher_cannot_print_another_teachers_pdf(): void
    {
        $mine = $this->makeTeacherAccount('Own Teacher');
        $their = $this->makeTeacherAccount('Other Teacher');
        $data = $this->seedPublishedSlotFor($their['teacher'], 'Other');

        $response = $this->actingAs($mine['user'])->get(route('timetable.pdf.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertForbidden();
    }

    public function test_a_teacher_cannot_download_another_teachers_excel(): void
    {
        $mine = $this->makeTeacherAccount('Own Teacher Excel');
        $their = $this->makeTeacherAccount('Other Teacher Excel');
        $data = $this->seedPublishedSlotFor($their['teacher'], 'OtherExcel');

        $response = $this->actingAs($mine['user'])->get(route('timetable.export.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertForbidden();
    }

    public function test_a_teacher_cannot_open_another_teachers_interactive_view(): void
    {
        $mine = $this->makeTeacherAccount('Own Teacher View');
        $their = $this->makeTeacherAccount('Other Teacher View');
        $data = $this->seedPublishedSlotFor($their['teacher'], 'OtherView');

        $response = $this->actingAs($mine['user'])->get(route('timetable.view.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertForbidden();
    }

    public function test_a_teacher_can_still_print_their_own_pdf(): void
    {
        $mine = $this->makeTeacherAccount('Self Teacher');
        $data = $this->seedPublishedSlotFor($mine['teacher'], 'Self');

        $response = $this->actingAs($mine['user'])->get(route('timetable.pdf.teacher', ['teacher_id' => $mine['teacher']->id]));

        $response->assertOk();
    }

    public function test_a_teacher_can_still_open_their_own_interactive_view(): void
    {
        $mine = $this->makeTeacherAccount('Self Teacher View');
        $this->seedPublishedSlotFor($mine['teacher'], 'SelfView');

        $response = $this->actingAs($mine['user'])->get(route('timetable.view.teacher', ['teacher_id' => $mine['teacher']->id]));

        $response->assertOk();
    }

    public function test_teacher_view_with_no_teacher_selected_still_shows_the_picker(): void
    {
        $mine = $this->makeTeacherAccount('Picker Teacher');

        $response = $this->actingAs($mine['user'])->get(route('timetable.view.teacher'));

        $response->assertOk();
    }

    public function test_admin_can_still_print_any_teachers_pdf(): void
    {
        $admin = $this->makeAdmin();
        $their = $this->makeTeacherAccount('Any Teacher For Admin');
        $data = $this->seedPublishedSlotFor($their['teacher'], 'AdminSeesAny');

        $response = $this->actingAs($admin)->get(route('timetable.pdf.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertOk();
    }

    // --- Class PDF/Excel: unassigned-class IDOR ---

    public function test_a_teacher_not_assigned_to_a_class_cannot_print_its_pdf(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Teacher');
        $owner = $this->makeTeacherAccount('Class Owner Teacher');
        $data = $this->seedPublishedSlotFor($owner['teacher'], 'ClassA');

        $response = $this->actingAs($outsider['user'])->get(route('timetable.pdf.class', ['school_class_id' => $data['class']->id]));

        $response->assertForbidden();
    }

    public function test_a_teacher_not_assigned_to_a_class_cannot_download_its_excel(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Teacher Excel');
        $owner = $this->makeTeacherAccount('Class Owner Teacher Excel');
        $data = $this->seedPublishedSlotFor($owner['teacher'], 'ClassExcelA');

        $response = $this->actingAs($outsider['user'])->get(route('timetable.export.class', ['school_class_id' => $data['class']->id]));

        $response->assertForbidden();
    }

    public function test_a_teacher_assigned_to_the_class_can_print_its_pdf(): void
    {
        $assigned = $this->makeTeacherAccount('Assigned Class Teacher');
        $data = $this->seedPublishedSlotFor($assigned['teacher'], 'ClassAssigned');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $assigned['teacher']->id, 'class_id' => $data['class']->id, 'section_id' => $data['section']->id,
            'subject_id' => $data['subject']->id, 'academic_year' => '2026-2027',
        ]);

        // Requesting the SAME section the assignment covers -- a whole-
        // class (no section_id) request would correctly still be refused,
        // mirroring the existing write-side semantics exactly (an
        // assignment scoped to one section is not a claim over the whole
        // class combined).
        $response = $this->actingAs($assigned['user'])->get(route('timetable.pdf.class', [
            'school_class_id' => $data['class']->id, 'section_id' => $data['section']->id,
        ]));

        $response->assertOk();
    }

    public function test_admin_can_still_print_any_classs_pdf(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeTeacherAccount('Any Class Owner For Admin');
        $data = $this->seedPublishedSlotFor($owner['teacher'], 'ClassAdmin');

        $response = $this->actingAs($admin)->get(route('timetable.pdf.class', ['school_class_id' => $data['class']->id]));

        $response->assertOk();
    }

    // --- Generation review/status: unrelated-generation IDOR ---

    private function makeGeneration(array $classIds): TimetableGeneration
    {
        return TimetableGeneration::create([
            'academic_year' => '2026-2027',
            'school_class_ids' => $classIds,
            'style' => TimetableGeneration::STYLE_ROTATING,
            'status' => TimetableGeneration::STATUS_COMPLETED,
            'placed_count' => 0,
            'unplaced_count' => 0,
            'report' => ['placements' => [], 'unplaced' => [], 'warnings' => []],
        ]);
    }

    public function test_a_teacher_unrelated_to_the_generations_classes_cannot_view_its_review(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Gen Teacher');
        $class = SchoolClass::create(['name' => 'Gen Review Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $generation = $this->makeGeneration([$class->id]);

        $response = $this->actingAs($outsider['user'])->get(route('timetable.generation.review', $generation));

        $response->assertForbidden();
    }

    public function test_a_teacher_unrelated_to_the_generations_classes_cannot_poll_its_status(): void
    {
        $outsider = $this->makeTeacherAccount('Outsider Gen Status Teacher');
        $class = SchoolClass::create(['name' => 'Gen Status Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $generation = $this->makeGeneration([$class->id]);

        $response = $this->actingAs($outsider['user'])->getJson(route('timetable.generation.status', $generation));

        $response->assertForbidden();
    }

    public function test_a_teacher_assigned_to_one_of_the_generations_classes_can_view_its_review(): void
    {
        $assigned = $this->makeTeacherAccount('Assigned Gen Teacher');
        $class = SchoolClass::create(['name' => 'Gen Review Assigned Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Gen Review Subject', 'code' => 'GRS' . uniqid()]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $assigned['teacher']->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-2027',
        ]);
        $generation = $this->makeGeneration([$class->id]);

        $response = $this->actingAs($assigned['user'])->get(route('timetable.generation.review', $generation));

        $response->assertOk();
    }

    public function test_admin_can_still_view_any_generations_review(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Gen Review Admin Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $generation = $this->makeGeneration([$class->id]);

        $response = $this->actingAs($admin)->get(route('timetable.generation.review', $generation));

        $response->assertOk();
    }
}
