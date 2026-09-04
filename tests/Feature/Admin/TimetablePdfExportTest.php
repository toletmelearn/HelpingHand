<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable-module T1c: printable PDF outputs, policy-gated identically
 * to timetable viewing.
 */
class TimetablePdfExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeParent(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'parent'], ['display_name' => 'Parent']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function seedOneSlot(): array
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => '2026-2027']);
        $class = SchoolClass::create(['name' => 'Class PDF', 'class_order' => 1, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH1']);
        $teacher = Teacher::create(['name' => 'PDF Teacher', 'status' => 'active']);

        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'academic_year' => '2026-2027',
        ]);

        return compact('class', 'section', 'teacher', 'subject', 'timing', 'slot');
    }

    // --- Class PDF ---

    public function test_class_pdf_returns_pdf_content_with_magic_bytes(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.class', ['school_class_id' => $data['class']->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_class_pdf_shows_friendly_message_when_no_slots_exist(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Empty Class', 'class_order' => 5, 'is_active' => true]);

        $response = $this->actingAs($admin)->get(route('timetable.pdf.class', ['school_class_id' => $class->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_class_pdf_unauthorized_role_gets_403(): void
    {
        $parent = $this->makeParent();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($parent)->get(route('timetable.pdf.class', ['school_class_id' => $data['class']->id]));

        $response->assertForbidden();
    }

    // --- Teacher PDF ---

    public function test_teacher_pdf_returns_pdf_content_with_magic_bytes(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_teacher_pdf_shows_friendly_message_when_no_slots_exist(): void
    {
        $admin = $this->makeAdmin();
        $teacher = Teacher::create(['name' => 'Idle Teacher', 'status' => 'active']);

        $response = $this->actingAs($admin)->get(route('timetable.pdf.teacher', ['teacher_id' => $teacher->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_teacher_pdf_unauthorized_role_gets_403(): void
    {
        $parent = $this->makeParent();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($parent)->get(route('timetable.pdf.teacher', ['teacher_id' => $data['teacher']->id]));

        $response->assertForbidden();
    }

    // --- Master PDF ---

    public function test_master_pdf_returns_pdf_content_with_magic_bytes(): void
    {
        $admin = $this->makeAdmin();
        $this->seedOneSlot();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.master'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_master_pdf_shows_friendly_message_when_no_slots_exist_anywhere(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.master'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_master_pdf_unauthorized_role_gets_403(): void
    {
        $parent = $this->makeParent();
        $this->seedOneSlot();

        $response = $this->actingAs($parent)->get(route('timetable.pdf.master'));

        $response->assertForbidden();
    }

    /**
     * Production-hardening: published slots can exist while every
     * BellTiming for the current academic year is deactivated (or was
     * never re-created after a year rollover) -- masterTimetableData()
     * only guarded "zero slots", not "zero active periods", so
     * $data['days'] came back empty and the master PDF view's own
     * @foreach($days as $day) (which owns the ENTIRE page body) silently
     * rendered nothing: a "successful" download of a blank PDF with no
     * error shown anywhere. Must now be a clear, friendly rejection
     * instead, matching masterExcelExport()'s twin fix.
     */
    public function test_master_pdf_shows_friendly_message_when_no_active_bell_timings_exist(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();
        $data['timing']->update(['is_active' => false]);

        $response = $this->actingAs($admin)->get(route('timetable.pdf.master'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Bell Timings', session('error'));
    }

    // --- Filename pattern ---

    public function test_class_pdf_filename_follows_the_documented_pattern(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.class', ['school_class_id' => $data['class']->id]));

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('timetable_class_', $disposition);
        $this->assertStringContainsString('2026-2027', $disposition);
    }

    public function test_teacher_pdf_filename_follows_the_documented_pattern(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.teacher', ['teacher_id' => $data['teacher']->id]));

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('timetable_teacher_', $disposition);
    }

    // --- Room PDF (Item 6: class/teacher/master already had this, room never did) ---

    public function test_room_pdf_export_returns_pdf(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedOneSlot();
        $data['slot']->update(['room_number' => 'Room 9']);

        $response = $this->actingAs($admin)->get(route('timetable.pdf.room', ['room' => 'Room 9']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    /** Same viewAny-only gate as roomView()/roomExcelExport() -- rooms have no ownership concept in this codebase. */
    public function test_room_pdf_export_respects_same_authorization_as_room_view(): void
    {
        $parent = $this->makeParent();
        $data = $this->seedOneSlot();
        $data['slot']->update(['room_number' => 'Room 9']);

        $response = $this->actingAs($parent)->get(route('timetable.pdf.room', ['room' => 'Room 9']));

        $response->assertForbidden();
    }

    public function test_room_pdf_export_empty_state_when_room_has_no_slots(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.pdf.room', ['room' => 'Nonexistent Room']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
