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
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable Hardening pass: TimetableController::store() upserts by
 * NATURAL KEY (class, section, bell_timing, status), not by row id --
 * unlike update(), which fetches a specific row and already rejects a
 * locked/combined-group target. TimetableConflictResolver deliberately
 * treats an existing row at that exact key as "self" (an in-place edit,
 * see resolveSelfId()/classSectionOverlapConflicts()'s documented
 * same-section exclusion), never as a reportable conflict -- so a locked
 * or combined-group row sitting there would previously be silently
 * overwritten by store()'s updateOrCreate() with zero warning. This was
 * the one write path the earlier Lock Integrity audit's sweep
 * (update()/destroy()/lockSlot()/swap/rebalance/Auto-Fix) never reached.
 */
class TimetableSlotNaturalKeyLockSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected SchoolClass $class;
    protected Section $section;
    protected Subject $subject;
    protected Teacher $originalTeacher;
    protected BellTiming $timing1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->class = SchoolClass::create(['name' => 'NK Class ' . uniqid(), 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $this->section = Section::create(['name' => 'A', 'class_id' => $this->class->id]);
        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'NK' . uniqid()]);
        $this->originalTeacher = Teacher::create(['name' => 'Original Teacher']);
        $this->timing1 = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSlot(array $overrides = []): TimetableSlot
    {
        return TimetableSlot::create(array_merge([
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->originalTeacher->id,
            'status' => TimetableSlot::STATUS_PUBLISHED,
        ], $overrides));
    }

    public function test_store_cannot_silently_overwrite_a_locked_slot_at_the_same_natural_key(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot(['is_locked' => true]);
        $newTeacher = Teacher::create(['name' => 'Attempted New Teacher']);

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $newTeacher->id,
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('locked', strtolower(session('error')));
        $this->assertSame($this->originalTeacher->id, $slot->fresh()->teacher_id);
        $this->assertTrue($slot->fresh()->is_locked);
        $this->assertSame(1, TimetableSlot::count());
    }

    public function test_store_cannot_silently_overwrite_a_combined_group_slot_at_the_same_natural_key(): void
    {
        $admin = $this->makeAdmin();
        $session = AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
        $group = CombinedClassGroup::create(['name' => 'Combined', 'subject_id' => $this->subject->id, 'academic_session_id' => $session->id]);
        CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $this->class->id]);
        $slot = $this->makeSlot(['combined_class_group_id' => $group->id]);
        $newTeacher = Teacher::create(['name' => 'Attempted New Teacher']);

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $newTeacher->id,
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame($this->originalTeacher->id, $slot->fresh()->teacher_id);
        $this->assertSame($group->id, $slot->fresh()->combined_class_group_id);
        $this->assertSame(1, TimetableSlot::count());
    }

    /**
     * Regression guard: an ordinary (unlocked, non-combined) slot at the
     * same natural key must still be editable in place via store() --
     * this is long-standing, intended behaviour (the manual grid's normal
     * "re-save this cell" path), unchanged by the new checks above.
     */
    public function test_store_can_still_update_an_ordinary_unlocked_slot_at_the_same_natural_key(): void
    {
        $admin = $this->makeAdmin();
        $slot = $this->makeSlot();
        $newTeacher = Teacher::create(['name' => 'New Teacher']);

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'bell_timing_id' => $this->timing1->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $newTeacher->id,
            'status' => 'published',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame($newTeacher->id, $slot->fresh()->teacher_id);
        $this->assertSame(1, TimetableSlot::count());
    }
}
