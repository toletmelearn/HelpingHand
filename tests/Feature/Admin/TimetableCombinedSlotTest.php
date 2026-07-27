<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2b item 3: placing a combined-class group's slot writes one
 * TimetableSlot row per member class, all sharing the same
 * teacher/period, and clash logic still catches real double-bookings.
 */
class TimetableCombinedSlotTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeGroupWithTwoMembers(): array
    {
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS' . uniqid()]);
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $group = CombinedClassGroup::create(['name' => 'Sanskrit Combined', 'subject_id' => $subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classA->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classB->id]);

        return compact('subject', 'session', 'classA', 'classB', 'group');
    }

    public function test_admin_can_place_a_combined_slot_writing_one_row_per_member(): void
    {
        $admin = $this->admin();
        $data = $this->makeGroupWithTwoMembers();
        $teacher = Teacher::create(['name' => 'Combined Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $response = $this->actingAs($admin)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $data['group']->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
            'room_number' => 'Hall 1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $data['classA']->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
            'combined_class_group_id' => $data['group']->id,
            'subject_id' => $data['subject']->id,
        ]);
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $data['classB']->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
            'combined_class_group_id' => $data['group']->id,
        ]);
        $this->assertSame(2, TimetableSlot::where('combined_class_group_id', $data['group']->id)->count());
    }

    public function test_placement_rejected_when_teacher_already_booked_elsewhere_that_period(): void
    {
        $admin = $this->admin();
        $data = $this->makeGroupWithTwoMembers();
        $teacher = Teacher::create(['name' => 'Busy Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $otherClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($admin)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $data['group']->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, TimetableSlot::where('combined_class_group_id', $data['group']->id)->count());
    }

    public function test_placement_rejected_when_a_member_class_already_has_a_slot_that_period(): void
    {
        $admin = $this->admin();
        $data = $this->makeGroupWithTwoMembers();
        $teacher = Teacher::create(['name' => 'Combined Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $otherTeacher = Teacher::create(['name' => 'Other Teacher']);

        // Class B already has a (solo, different-teacher) slot this period.
        TimetableSlot::create([
            'school_class_id' => $data['classB']->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        $response = $this->actingAs($admin)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $data['group']->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0, TimetableSlot::where('combined_class_group_id', $data['group']->id)->count());
    }

    public function test_teacher_not_assigned_to_all_member_classes_is_forbidden(): void
    {
        $teacherUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $teacherUser->roles()->attach($role->id);

        $data = $this->makeGroupWithTwoMembers();
        $teacherModel = Teacher::create(['name' => 'Half Assigned', 'user_id' => $teacherUser->id]);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        // Assigned to Class A only, not Class B -- the group needs both.
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacherModel->id,
            'class_id' => $data['classA']->id,
            'subject_id' => $data['subject']->id,
        ]);

        $response = $this->actingAs($teacherUser)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $data['group']->id,
            'teacher_id' => $teacherModel->id,
            'bell_timing_id' => $timing->id,
        ]);

        $response->assertForbidden();
    }

    public function test_teacher_assigned_to_all_member_classes_can_place_the_slot(): void
    {
        $teacherUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $teacherUser->roles()->attach($role->id);

        $data = $this->makeGroupWithTwoMembers();
        $teacherModel = Teacher::create(['name' => 'Fully Assigned', 'user_id' => $teacherUser->id]);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        foreach ([$data['classA'], $data['classB']] as $class) {
            TeacherClassSubjectAssignment::create([
                'teacher_id' => $teacherModel->id,
                'class_id' => $class->id,
                'subject_id' => $data['subject']->id,
            ]);
        }

        $response = $this->actingAs($teacherUser)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $data['group']->id,
            'teacher_id' => $teacherModel->id,
            'bell_timing_id' => $timing->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame(2, TimetableSlot::where('combined_class_group_id', $data['group']->id)->count());
    }
}
