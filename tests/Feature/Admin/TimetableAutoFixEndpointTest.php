<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Auto-Fix) HTTP endpoint: admin-only (moves a class-section the
 * caller may have no claim over), and a successful apply leaves a real
 * activity_log trail, matching this session's earlier activity-log work.
 */
class TimetableAutoFixEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeGrid(): array
    {
        return [
            'Monday1' => BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING]),
            'Monday2' => BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_active' => true, 'is_break' => false, 'order_index' => 2, 'period_type' => BellTiming::PERIOD_TYPE_TEACHING]),
        ];
    }

    public function test_non_admin_cannot_apply_an_auto_fix(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFE' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($this->teacherUser())->post(route('timetable.auto-fix.relocate-blocker'), [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'blocking_slot_id' => $blocker->id,
            'blocker_new_bell_timing_id' => $timings['Monday2']->id,
        ]);

        $response->assertForbidden();
    }

    public function test_admin_applying_an_auto_fix_leaves_an_activity_log_entry(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFE' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($this->admin())->post(route('timetable.auto-fix.relocate-blocker'), [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'blocking_slot_id' => $blocker->id,
            'blocker_new_bell_timing_id' => $timings['Monday2']->id,
        ]);

        $response->assertOk();
        $response->assertJson(['applied' => true]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $blocker->id,
            'description' => 'timetable_autofix_applied',
        ]);
    }

    /** Lock Integrity hardening, HTTP level: mirrors the service-level test in TimetableAutoFixServiceTest.php, proving the route wires the new guard through end to end. */
    public function test_admin_cannot_relocate_a_locked_blocker(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AFE' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'is_locked' => true,
        ]);

        $response = $this->actingAs($this->admin())->post(route('timetable.auto-fix.relocate-blocker'), [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'blocking_slot_id' => $blocker->id,
            'blocker_new_bell_timing_id' => $timings['Monday2']->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['applied' => false]);
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id);
        $this->assertDatabaseMissing('activity_log', [
            'subject_type' => TimetableSlot::class,
            'subject_id' => $blocker->id,
            'description' => 'timetable_autofix_applied',
        ]);
    }
}
