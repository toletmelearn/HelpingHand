<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable-module T1a: DB-level double-booking protection under the
 * existing app-level checks in Admin\TimetableController.
 *
 * Note on what's testable via HTTP: TimetableController::store() upserts
 * on (school_class_id, section_id, bell_timing_id) via updateOrCreate, so
 * resubmitting the exact same class+section+period through the real
 * endpoint always UPDATES the existing row rather than violating the
 * class-side constraint -- it can only be violated by a genuine race
 * between two concurrent requests, which a single-threaded HTTP test
 * can't simulate. The class-side and NULL-section-normalization tests
 * below exercise the constraint directly at the model/DB level instead,
 * which is what actually proves the migration did its job. The teacher-
 * side constraint IS reachable via HTTP, but checkSlotConflicts() already
 * catches every teacher double-booking before the DB write is ever
 * attempted (covered by the existing
 * TimetableSchedulerTest::test_timetable_blocks_overlapping_teacher_slots) --
 * this file doesn't duplicate that coverage, it proves the DB has its own
 * independent backstop.
 */
class TimetableSlotUniqueConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeClass(): SchoolClass
    {
        return SchoolClass::create(['name' => 'Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create(['name' => 'Mathematics', 'code' => 'MATH' . uniqid()]);
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::create(['name' => 'Teacher ' . uniqid()]);
    }

    private function makeTiming(): BellTiming
    {
        return BellTiming::create([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 1,
        ]);
    }

    public function test_db_constraint_blocks_a_direct_duplicate_class_section_slot(): void
    {
        $class = $this->makeClass();
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $timing = $this->makeTiming();
        $subject = $this->makeSubject();

        TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->makeTeacher()->id,
        ]);

        $this->expectException(QueryException::class);

        // A different teacher/subject -- proves it's the class+section+
        // period key being enforced, not an incidental duplicate row.
        TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $this->makeSubject()->id,
            'teacher_id' => $this->makeTeacher()->id,
        ]);
    }

    public function test_db_constraint_blocks_a_direct_duplicate_teacher_slot(): void
    {
        $teacher = $this->makeTeacher();
        $timing = $this->makeTiming();
        $subject = $this->makeSubject();

        TimetableSlot::create([
            'school_class_id' => $this->makeClass()->id,
            'section_id' => null,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->expectException(QueryException::class);

        // A different class entirely -- proves it's teacher+period being
        // enforced regardless of which class/section is involved.
        TimetableSlot::create([
            'school_class_id' => $this->makeClass()->id,
            'section_id' => null,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);
    }

    /**
     * The specific case a plain (unnormalized) unique index would miss:
     * MySQL/MariaDB treats multiple NULLs in a composite unique index as
     * distinct, not equal. section_id_norm (a STORED generated column,
     * COALESCE(section_id, 0)) exists specifically so two class-wide
     * (section_id NULL) slots for the same class+period DO collide.
     */
    public function test_db_constraint_blocks_two_class_wide_null_section_slots_for_the_same_period(): void
    {
        $class = $this->makeClass();
        $timing = $this->makeTiming();
        $subject = $this->makeSubject();

        TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => null,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->makeTeacher()->id,
        ]);

        try {
            TimetableSlot::create([
                'school_class_id' => $class->id,
                'section_id' => null,
                'bell_timing_id' => $timing->id,
                'subject_id' => $this->makeSubject()->id,
                'teacher_id' => $this->makeTeacher()->id,
            ]);
            $this->fail('Expected a QueryException: two NULL-section slots for the same class+period should collide via section_id_norm.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode(), 'Expected a genuine integrity-constraint violation (SQLSTATE 23000).');
        }
    }

    public function test_a_class_wide_slot_and_a_specific_section_slot_do_not_collide_documented_gap(): void
    {
        // Documents the known, intentional gap from the migration: a
        // class-wide slot (section_id_norm=0) and a specific-section slot
        // of the SAME class at the SAME period have different
        // section_id_norm values, so the DB constraint does not catch
        // this cross-case (flagged for T1b's conflict scan, not fixed
        // here). This test pins the current (gap) behavior so a future
        // change to close it is a deliberate, visible decision.
        $class = $this->makeClass();
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $timing = $this->makeTiming();
        $subject = $this->makeSubject();

        TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => null,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->makeTeacher()->id,
        ]);

        $secondSlot = TimetableSlot::create([
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $this->makeSubject()->id,
            'teacher_id' => $this->makeTeacher()->id,
        ]);

        $this->assertNotNull($secondSlot->id);
        $this->assertSame(2, TimetableSlot::where('school_class_id', $class->id)->count());
    }

    public function test_valid_slot_with_no_section_still_saves_via_the_controller(): void
    {
        $admin = $this->makeAdmin();
        $class = $this->makeClass();
        $timing = $this->makeTiming();
        $subject = $this->makeSubject();
        $teacher = $this->makeTeacher();

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $class->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $class->id,
            'section_id' => null,
            'bell_timing_id' => $timing->id,
        ]);
    }
}
