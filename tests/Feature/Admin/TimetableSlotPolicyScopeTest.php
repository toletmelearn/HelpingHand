<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Remediation deferred register item, folded into timetable-module T1a:
 * TimetableSlotPolicy::create()/update() previously let ANY teacher write
 * ANY class's timetable. Narrowed so a teacher may only write slots for
 * class-sections they hold a teacher_class_subject_assignments row for;
 * admin stays unrestricted.
 */
class TimetableSlotPolicyScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTeacherUser(): array
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);
        $teacher = Teacher::create([
            'name' => 'Test Teacher ' . uniqid(),
            'user_id' => $user->id,
        ]);

        return [$user, $teacher];
    }

    private function makeClassSection(): array
    {
        $class = SchoolClass::create(['name' => 'Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);

        return [$class, $section];
    }

    private function makeSubjectAndTiming(): array
    {
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH' . uniqid()]);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 1,
        ]);

        return [$subject, $timing];
    }

    public function test_teacher_without_assignment_cannot_write_the_class_timetable(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        [$class, $section] = $this->makeClassSection();
        [$subject, $timing] = $this->makeSubjectAndTiming();

        $response = $this->actingAs($user)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('timetable_slots', ['school_class_id' => $class->id]);
    }

    public function test_teacher_assigned_to_the_class_section_can_write_its_timetable(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        [$class, $section] = $this->makeClassSection();
        [$subject, $timing] = $this->makeSubjectAndTiming();

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($user)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $class->id, 'section_id' => $section->id]);
    }

    public function test_teacher_assigned_to_a_different_class_cannot_write_this_class_timetable(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        [$class, $section] = $this->makeClassSection();
        [$otherClass, $otherSection] = $this->makeClassSection();
        [$subject, $timing] = $this->makeSubjectAndTiming();

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($user)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertForbidden();
    }

    public function test_teacher_with_a_class_wide_assignment_can_write_any_section_of_that_class(): void
    {
        [$user, $teacher] = $this->makeTeacherUser();
        [$class, $section] = $this->makeClassSection();
        [$subject, $timing] = $this->makeSubjectAndTiming();

        // Assignment with no section_id -- covers the whole class.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'section_id' => null,
            'subject_id' => $subject->id,
        ]);

        $response = $this->actingAs($user)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $class->id]);
    }

    public function test_admin_can_write_any_class_timetable_regardless_of_assignment(): void
    {
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        [$class, $section] = $this->makeClassSection();
        [$subject, $timing] = $this->makeSubjectAndTiming();
        $teacher = Teacher::create(['name' => 'Unassigned Teacher']);

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_slots', ['school_class_id' => $class->id]);
    }
}
