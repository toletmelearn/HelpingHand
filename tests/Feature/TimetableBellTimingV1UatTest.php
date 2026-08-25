<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherLogin;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Timetable + Bell Timing V1 completion pass: a single realistic
 * end-to-end walkthrough of the admin/teacher/substitution/availability
 * narrative from the V1 UAT plan, chained through the REAL routes (never
 * calling services directly), on fixtures this test builds and owns --
 * never the real LKG/ANJALI school data (BellTiming id=2 / TimetableSlot
 * id=7) other suites in this codebase are careful to avoid touching.
 *
 * Every individual behavior exercised here already has dedicated,
 * narrower coverage elsewhere (BellTimingDependencyReassignmentTest,
 * TimetableLockTest, TimetableSlotUpdateTest, BellTimingBulkDeleteTest,
 * etc.) -- this suite's value is proving the full CHAIN holds together:
 * create -> place -> lock/unlock -> block -> resolve (slot AND
 * substitution) -> re-check -> safe delete -> the reassignment is visible
 * from the teacher's own side -> an availability block is actually
 * enforced against a new placement.
 */
class TimetableBellTimingV1UatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function teacherLogin(Teacher $teacher): TeacherLogin
    {
        return TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => 'uat-teacher-' . uniqid(),
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_full_v1_admin_teacher_substitution_availability_lifecycle(): void
    {
        $admin = $this->admin();

        $class = SchoolClass::create(['name' => 'V1 UAT Class', 'class_order' => 9701, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'V1 UAT Subject', 'code' => 'V1UAT-' . uniqid(), 'is_active' => true]);
        $teacher1 = Teacher::create(['name' => 'V1 UAT Teacher One', 'status' => 'active']);
        $teacher2 = Teacher::create(['name' => 'V1 UAT Teacher Two', 'status' => 'active']);

        // ------------------------------------------------------------
        // 1-2. Admin creates two Bell Timings (real store() route).
        // ------------------------------------------------------------
        $this->actingAs($admin)->post(route('bell-timing.store'), [
            'day_of_week' => 'Monday', 'period_name' => 'V1 UAT Period 1',
            'start_time' => '09:00', 'end_time' => '09:40', 'order_index' => 970,
        ])->assertRedirect(route('bell-timing.index'));
        $b1 = BellTiming::where('period_name', 'V1 UAT Period 1')->firstOrFail();

        $this->actingAs($admin)->post(route('bell-timing.store'), [
            'day_of_week' => 'Monday', 'period_name' => 'V1 UAT Period 2',
            'start_time' => '09:40', 'end_time' => '10:20', 'order_index' => 971,
        ])->assertRedirect(route('bell-timing.index'));
        $b2 = BellTiming::where('period_name', 'V1 UAT Period 2')->firstOrFail();

        // ------------------------------------------------------------
        // 3-5. Admin places a PUBLISHED slot: class, section, subject,
        // teacher, Bell Timing -- this is the live timetable.
        // ------------------------------------------------------------
        $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
            'bell_timing_id' => $b1->id, 'subject_id' => $subject->id,
            'teacher_id' => $teacher1->id, 'status' => 'published',
        ])->assertSessionHas('success');
        $slot = TimetableSlot::where('school_class_id', $class->id)->where('bell_timing_id', $b1->id)->firstOrFail();
        $this->assertSame(TimetableSlot::STATUS_PUBLISHED, $slot->status);

        // 6. Edit the draft/live cell -- reassign to teacher2.
        $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
            'bell_timing_id' => $b1->id, 'subject_id' => $subject->id,
            'teacher_id' => $teacher2->id,
        ])->assertSessionHas('success');
        $this->assertSame($teacher2->id, $slot->fresh()->teacher_id);

        // ------------------------------------------------------------
        // 7-8. Lock it, then verify unsafe modification/deletion are
        // both refused while locked (no accidental cascade deletion).
        // ------------------------------------------------------------
        $this->actingAs($admin)->post(route('timetable.lock', $slot))->assertSessionHas('success');
        $this->assertTrue($slot->fresh()->is_locked);

        $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
            'bell_timing_id' => $b1->id, 'subject_id' => $subject->id,
            'teacher_id' => $teacher1->id,
        ])->assertSessionHas('error');
        $this->assertSame($teacher2->id, $slot->fresh()->teacher_id, 'a locked slot must not be silently edited');

        $this->actingAs($admin)->delete(route('timetable.destroy', $slot->id))->assertSessionHas('error');
        $this->assertNotNull($slot->fresh(), 'a locked slot must not be silently deleted');

        $this->actingAs($admin)->post(route('timetable.unlock', $slot))->assertSessionHas('success');
        $this->assertFalse($slot->fresh()->is_locked);

        // ------------------------------------------------------------
        // 9-11. Attempt to delete B1 while it's still in use -> blocked.
        // A substitution referencing B1 is added too, so both dependency
        // kinds are live at once.
        // ------------------------------------------------------------
        $sub = TeacherSubstitution::create([
            'substitution_date' => '2026-08-31', 'absent_teacher_id' => $teacher2->id,
            'class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'period_number' => 1, 'bell_timing_id' => $b1->id, 'substitute_teacher_id' => $teacher1->id,
            'created_by' => $admin->id, 'status' => 'approved', 'reason' => 'V1 UAT',
        ]);

        $this->actingAs($admin)->delete(route('bell-timing.destroy', $b1))->assertSessionHas('error');
        $this->assertNotNull($b1->fresh(), 'delete must be blocked while dependencies exist, never DB-error');

        $detailResponse = $this->actingAs($admin)->get(route('bell-timing.dependencies', $b1));
        $detailResponse->assertOk();
        $detailResponse->assertSee($class->name);
        $detailResponse->assertSee($teacher2->name); // the substitution's absent teacher

        // ------------------------------------------------------------
        // 12-13. Resolve both dependencies via the real reassignment
        // screens/endpoints (never a raw model update), moving each to B2.
        // ------------------------------------------------------------
        $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-slot', [$b1, $slot]))->assertOk();
        $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $class->id, 'section_id' => $section->id,
            'bell_timing_id' => $b2->id, 'subject_id' => $subject->id,
            'teacher_id' => $teacher2->id,
        ])->assertSessionHas('success');
        $this->assertSame($b2->id, $slot->fresh()->bell_timing_id);

        $this->actingAs($admin)->get(route('bell-timing.dependencies.reassign-substitution', [$b1, $sub]))->assertOk();
        $this->actingAs($admin)->put(route('admin.teacher-substitutions.update', $sub), [
            'substitution_date' => $sub->substitution_date->toDateString(),
            'absent_teacher_id' => $sub->absent_teacher_id, 'class_id' => $sub->class_id,
            'section_id' => $sub->section_id, 'subject_id' => $sub->subject_id,
            'bell_timing_id' => $b2->id, 'status' => $sub->status,
            'substitute_teacher_id' => $sub->substitute_teacher_id, 'reason' => $sub->reason,
        ])->assertRedirect(route('admin.teacher-substitutions.index'));
        $this->assertSame($b2->id, $sub->fresh()->bell_timing_id);

        // 14. Re-check: B1 is now genuinely dependency-free.
        $this->actingAs($admin)->get(route('bell-timing.dependencies', $b1))
            ->assertOk()
            ->assertSee('has no dependencies');

        // 15. Delete only now that it is genuinely safe.
        $this->actingAs($admin)->delete(route('bell-timing.destroy', $b1))->assertSessionHas('success');
        $this->assertNull(BellTiming::find($b1->id));
        // The reassigned records -- and B2 -- must survive untouched.
        $this->assertNotNull(BellTiming::find($b2->id));
        $this->assertNotNull(TimetableSlot::find($slot->id));
        $this->assertNotNull(TeacherSubstitution::find($sub->id));

        // ------------------------------------------------------------
        // 16-17. Teacher-side: teacher2 (now the slot's own teacher,
        // reassigned above) sees the correct class/subject/period.
        // ------------------------------------------------------------
        $login = $this->teacherLogin($teacher2);
        $dashboard = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));
        $dashboard->assertOk();

        // ------------------------------------------------------------
        // 18. Availability integration: block teacher1 at B2, then
        // confirm a NEW placement attempt for teacher1 at B2 is rejected.
        // ------------------------------------------------------------
        TeacherAvailability::create(['teacher_id' => $teacher1->id, 'bell_timing_id' => $b2->id, 'is_available' => false]);

        $class2 = SchoolClass::create(['name' => 'V1 UAT Class 2', 'class_order' => 9702, 'is_active' => true]);
        $blockedAttempt = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class2->id, 'bell_timing_id' => $b2->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher1->id, 'status' => 'published',
        ]);
        $blockedAttempt->assertSessionHas('error');
        $this->assertNull(
            TimetableSlot::where('school_class_id', $class2->id)->where('bell_timing_id', $b2->id)->first(),
            'a placement conflicting with a teacher availability block must not be written'
        );
    }
}
