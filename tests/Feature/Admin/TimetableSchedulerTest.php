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

class TimetableSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $teacher;
    protected BellTiming $timing1;
    protected BellTiming $timing2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        // Set up entities
        $this->class = SchoolClass::create([
            'name' => 'Class X',
            'class_order' => 10,
        ]);

        $this->section = Section::create([
            'name' => 'Section A',
            'class_id' => $this->class->id,
        ]);

        $this->subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $this->teacher = Teacher::create([
            'name' => 'John Doe',
            'email' => 'john.doe@school.com',
            'phone' => '1234567890',
            'designation' => 'Math Teacher',
            'gender' => 'Male',
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

        $this->timing2 = BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 2',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 2,
        ]);
    }

    public function test_timetable_dashboard_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('timetable.index', ['school_class_id' => $this->class->id]));

        $response->assertStatus(200);
        $response->assertSee('Academic Timetable Scheduler');
        $response->assertSee('Class X');
    }

    public function test_can_schedule_timetable_slot()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'room_number' => 'Room 101',
                'academic_year' => '2026',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $this->class->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Room 101',
        ]);
    }

    public function test_timetable_blocks_overlapping_teacher_slots()
    {
        // First slot assignment
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Room 101',
            'academic_year' => '2026',
        ]);

        // Attempt second overlapping slot with same teacher
        $otherClass = SchoolClass::create(['name' => 'Class XI', 'class_order' => 11]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $otherClass->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'room_number' => 'Room 102',
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    /**
     * T1a's own documented gap, closed at the app level: a class-wide slot
     * (section_id NULL) and a specific-section slot of the SAME class at
     * the SAME period have different section_id_norm values, so the DB
     * unique index alone never caught this cross-case (still true --
     * see TimetableSlotUniqueConstraintsTest's documented-gap test, which
     * exercises the DB layer directly). checkSlotConflicts() now catches
     * it before the write, the same way it already catches teacher/room
     * overlaps.
     */
    public function test_a_new_section_specific_slot_is_blocked_by_an_existing_class_wide_slot()
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => null,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $otherTeacher = Teacher::create(['name' => 'Jane Roe']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $otherTeacher->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    /** The reverse direction: a class-wide save must be blocked by an existing section-specific slot for the same class+period. */
    public function test_a_new_class_wide_slot_is_blocked_by_an_existing_section_specific_slot()
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $otherTeacher = Teacher::create(['name' => 'Jane Roe']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $otherTeacher->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $this->class->id,
            'section_id' => null,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    /** A different section of the same class at the same period must NOT be flagged -- only class-wide-vs-section overlaps, never section-vs-different-section. */
    public function test_two_different_sections_of_the_same_class_do_not_falsely_conflict()
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
        ]);

        $otherSection = Section::create(['name' => 'Section B', 'class_id' => $this->class->id]);
        $otherTeacher = Teacher::create(['name' => 'Jane Roe']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $otherSection->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $otherTeacher->id,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $this->class->id,
            'section_id' => $otherSection->id,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    /**
     * Team teaching (T6 item 4) was previously generator-only -- the
     * manual editor had no co_teacher_id field or conflict checking at
     * all. This confirms the manual store() path now saves a co-teacher
     * cleanly, with no false self-clash between a lesson's own two
     * teachers.
     */
    public function test_manual_store_saves_a_co_teacher_without_a_false_clash()
    {
        $coTeacher = Teacher::create(['name' => 'Co Teacher']);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'co_teacher_id' => $coTeacher->id,
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'teacher_id' => $this->teacher->id,
            'co_teacher_id' => $coTeacher->id,
        ]);
    }

    /** A co-teacher already busy elsewhere (as anyone's primary teacher) at an overlapping period must block the save, exactly like the primary teacher check. */
    public function test_manual_store_blocks_a_co_teacher_already_busy_elsewhere()
    {
        $coTeacher = Teacher::create(['name' => 'Co Teacher']);
        $otherClass = SchoolClass::create(['name' => 'Class XI', 'class_order' => 11]);

        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'section_id' => null,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $coTeacher->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $this->teacher->id,
                'co_teacher_id' => $coTeacher->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $this->class->id,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    /**
     * The cross-case: a person already booked as someone ELSE's co-teacher
     * must block them from being saved as a PRIMARY teacher elsewhere at
     * an overlapping period. The DB's co-teacher unique index only covers
     * co-teacher-vs-co-teacher; this is the app-level guard for the cross
     * case, mirroring what GeneratorService::isHardLegal() already
     * enforces for auto-generation.
     */
    public function test_manual_store_blocks_a_primary_teacher_already_busy_as_someone_elses_co_teacher()
    {
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher']);
        $otherClass = SchoolClass::create(['name' => 'Class XI', 'class_order' => 11]);

        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'section_id' => null,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'co_teacher_id' => $sharedTeacher->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('timetable.store'), [
                'school_class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'bell_timing_id' => $this->timing1->id,
                'subject_id' => $this->subject->id,
                'teacher_id' => $sharedTeacher->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $this->class->id,
            'bell_timing_id' => $this->timing1->id,
        ]);
    }

    public function test_can_clear_scheduled_slot()
    {
        $slot = TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Room 101',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('timetable.destroy', $slot->id));

        $response->assertRedirect();
        $this->assertDatabaseMissing('timetable_slots', ['id' => $slot->id]);
    }

    public function test_check_conflicts_api()
    {
        TimetableSlot::create([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'room_number' => 'Room 101',
        ]);

        // Check api for same teacher
        $response = $this->actingAs($this->adminUser)
            ->get(route('timetable.check-conflicts', [
                'bell_timing_id' => $this->timing1->id,
                'teacher_id' => $this->teacher->id,
                'room_number' => 'Room 102',
            ]));

        $response->assertJson([
            'conflict' => true,
            'type' => 'teacher',
        ]);
    }
}
