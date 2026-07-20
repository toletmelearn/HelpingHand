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
