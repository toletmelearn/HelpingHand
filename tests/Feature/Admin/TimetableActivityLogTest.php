<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\CombinedClassGroup;
use App\Models\CombinedClassGroupMember;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app already has a real audit-log system (spatie/activitylog, used
 * via activity()->log(...) in AdminStudentController, PaymentClaimMatching
 * Controller, etc.) -- no timetable action used it before this. Confirms
 * the key mutations (manual slot create/update/clear, combined-group
 * place/clear, generate/publish/discard) each leave a real activity_log
 * row, following the exact same assertDatabaseHas('activity_log', [...])
 * pattern FrontOfficeTest already uses for the admissions module.
 */
class TimetableActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_manual_store_logs_slot_created_then_slot_updated(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Class Log', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOGM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Log Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $slot = TimetableSlot::where('school_class_id', $class->id)->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_created',
        ]);

        // Re-saving the SAME class+period+status cell is an update, not a create.
        $otherTeacher = Teacher::create(['name' => 'Other Log Teacher']);
        $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $otherTeacher->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_updated',
        ]);
    }

    public function test_clearing_a_slot_logs_slot_cleared(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Class Log', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOGM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Log Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($admin)->delete(route('timetable.destroy', $slot->id));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $slot->id,
            'description' => 'timetable_slot_cleared',
        ]);
    }

    public function test_combined_group_place_and_clear_are_both_logged(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'LOGS' . uniqid()]);
        $classA = SchoolClass::create(['name' => 'Class LogA', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class LogB', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $group = CombinedClassGroup::create(['name' => 'Log Combined', 'subject_id' => $subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classA->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classB->id]);
        $teacher = Teacher::create(['name' => 'Combined Log Teacher']);
        $timing = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $this->actingAs($admin)->post(route('timetable.combined.store'), [
            'combined_class_group_id' => $group->id,
            'teacher_id' => $teacher->id,
            'bell_timing_id' => $timing->id,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => CombinedClassGroup::class,
            'subject_id' => $group->id,
            'description' => 'combined_group_placed',
        ]);

        $classASlot = TimetableSlot::where('combined_class_group_id', $group->id)->where('school_class_id', $classA->id)->firstOrFail();
        $this->actingAs($admin)->delete(route('timetable.destroy', $classASlot->id));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => CombinedClassGroup::class,
            'subject_id' => $group->id,
            'description' => 'combined_group_cleared',
        ]);
    }

    public function test_generate_publish_and_discard_are_all_logged(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Class Log', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOGM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Log Teacher']);
        \App\Models\TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'periods_per_week' => 1,
        ]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$class->id],
        ]);
        $generation = TimetableGeneration::findOrFail($response->json('generation_id'));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableGeneration::class,
            'subject_id' => $generation->id,
            'description' => 'timetable_generation_requested',
        ]);

        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableGeneration::class,
            'subject_id' => $generation->id,
            'description' => 'timetable_generation_published',
        ]);
    }

    public function test_discard_is_logged(): void
    {
        $admin = $this->makeAdmin();
        $class = SchoolClass::create(['name' => 'Class Log', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'LOGM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Log Teacher']);
        \App\Models\TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id, 'periods_per_week' => 1,
        ]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$class->id],
        ]);
        $generation = TimetableGeneration::findOrFail($response->json('generation_id'));

        $this->actingAs($admin)->post(route('timetable.generation.discard', $generation));

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableGeneration::class,
            'subject_id' => $generation->id,
            'description' => 'timetable_generation_discarded',
        ]);
    }
}
