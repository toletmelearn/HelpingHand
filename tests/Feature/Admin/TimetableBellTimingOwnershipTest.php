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
 * UAT Test 15 defect fix: BellTiming.class_section has no FK to
 * SchoolClass, so TimetableController::store()/update() previously only
 * verified `bell_timing_id` EXISTS, never that it belonged to the class
 * being scheduled. With many classes sharing the same day/period/time
 * (e.g. after a Bell Template bulk-apply), the "Select Period Slot"
 * dropdown offered visually-identical cross-class options with nothing --
 * neither the UI nor server-side validation -- preventing the wrong one
 * from being silently persisted.
 */
class TimetableBellTimingOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fixtures(): array
    {
        $classA = SchoolClass::create(['name' => 'Ownership Class A', 'class_order' => 970201, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Ownership Class B', 'class_order' => 970202, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Ownership Subject', 'code' => 'OWN-' . uniqid(), 'is_active' => true]);
        $teacher = Teacher::create(['name' => 'Ownership Teacher', 'status' => 'active']);

        // Two BellTiming rows sharing the exact same day/period/time label,
        // one per class -- exactly the "18 visually identical options"
        // scenario found during manual UAT.
        $bellA = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '09:10', 'end_time' => '09:50',
            'class_section' => 'Ownership Class A', 'is_active' => true, 'order_index' => 3,
        ]);
        $bellB = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 3', 'start_time' => '09:10', 'end_time' => '09:50',
            'class_section' => 'Ownership Class B', 'is_active' => true, 'order_index' => 3,
        ]);
        $bellGeneral = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 4', 'start_time' => '09:50', 'end_time' => '10:30',
            'class_section' => null, 'is_active' => true, 'order_index' => 4,
        ]);

        return compact('classA', 'classB', 'subject', 'teacher', 'bellA', 'bellB', 'bellGeneral');
    }

    public function test_store_accepts_a_bell_timing_belonging_to_the_selected_class(): void
    {
        ['classA' => $classA, 'subject' => $subject, 'teacher' => $teacher, 'bellA' => $bellA] = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $classA->id, 'bell_timing_id' => $bellA->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'published',
        ]);

        $response->assertSessionHas('success');
        $this->assertNotNull(
            TimetableSlot::where('school_class_id', $classA->id)->where('bell_timing_id', $bellA->id)->first()
        );
    }

    public function test_store_rejects_a_bell_timing_belonging_to_a_different_class(): void
    {
        ['classA' => $classA, 'subject' => $subject, 'teacher' => $teacher, 'bellB' => $bellB] = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $classA->id, 'bell_timing_id' => $bellB->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'published',
        ]);

        $response->assertSessionHas('error');
        $this->assertNull(
            TimetableSlot::where('school_class_id', $classA->id)->where('bell_timing_id', $bellB->id)->first(),
            'a slot must never be written when the bell timing belongs to a different class'
        );
    }

    public function test_store_accepts_a_general_all_classes_bell_timing_for_any_class(): void
    {
        ['classA' => $classA, 'subject' => $subject, 'teacher' => $teacher, 'bellGeneral' => $bellGeneral] = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $classA->id, 'bell_timing_id' => $bellGeneral->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'published',
        ]);

        $response->assertSessionHas('success');
        $this->assertNotNull(
            TimetableSlot::where('school_class_id', $classA->id)->where('bell_timing_id', $bellGeneral->id)->first()
        );
    }

    public function test_update_rejects_moving_a_slot_to_a_bell_timing_belonging_to_a_different_class(): void
    {
        ['classA' => $classA, 'classB' => $classB, 'subject' => $subject, 'teacher' => $teacher, 'bellA' => $bellA, 'bellB' => $bellB] = $this->fixtures();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('timetable.store'), [
            'school_class_id' => $classA->id, 'bell_timing_id' => $bellA->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'status' => 'published',
        ])->assertSessionHas('success');
        $slot = TimetableSlot::where('school_class_id', $classA->id)->where('bell_timing_id', $bellA->id)->firstOrFail();

        $response = $this->actingAs($admin)->patch(route('timetable.update', $slot), [
            'school_class_id' => $classA->id, 'bell_timing_id' => $bellB->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertSame($bellA->id, $slot->fresh()->bell_timing_id, 'a locked-in class/period pairing must survive a rejected edit');
    }

    public function test_review_edit_grid_only_offers_bell_timings_for_the_selected_class(): void
    {
        ['classA' => $classA, 'bellA' => $bellA, 'bellB' => $bellB, 'bellGeneral' => $bellGeneral] = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('timetable.index', ['school_class_id' => $classA->id]));

        $response->assertOk();
        $response->assertViewHas('bellTimings', function ($bellTimings) use ($bellA, $bellB, $bellGeneral) {
            $ids = $bellTimings->pluck('id');

            return $ids->contains($bellA->id)
                && $ids->contains($bellGeneral->id)
                && ! $ids->contains($bellB->id);
        });
    }
}
