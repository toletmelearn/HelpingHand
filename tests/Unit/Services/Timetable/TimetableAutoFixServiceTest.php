<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableAutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 (Auto-Fix): applying a relocate-blocker suggestion is one
 * atomic action -- move the blocker, place the new lesson -- and it must
 * re-validate both against LIVE data immediately before writing anything,
 * never trust a suggestion computed earlier as still true.
 */
class TimetableAutoFixServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeGrid(): array
    {
        $timings = [];
        foreach (['Monday', 'Tuesday'] as $day) {
            for ($p = 1; $p <= 2; $p++) {
                $timings["{$day}{$p}"] = BellTiming::create([
                    'day_of_week' => $day, 'period_name' => "P{$p}",
                    'start_time' => sprintf('%02d:00', 7 + $p), 'end_time' => sprintf('%02d:45', 7 + $p),
                    'is_active' => true, 'is_break' => false, 'order_index' => $p,
                    'period_type' => BellTiming::PERIOD_TYPE_TEACHING,
                ]);
            }
        }

        return $timings;
    }

    public function test_applies_the_fix_moving_the_blocker_and_placing_the_new_lesson(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertTrue($result['applied']);
        $this->assertSame($timings['Monday2']->id, $blocker->fresh()->bell_timing_id);
        $this->assertDatabaseHas('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
        ]);
    }

    public function test_rejects_a_stale_fix_when_the_destination_is_no_longer_free(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $blockerClass = SchoolClass::create(['name' => 'Blocker Class', 'class_order' => 2, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared Teacher', 'status' => 'active']);
        $blockerTeacher = Teacher::create(['name' => 'Blocker Teacher', 'status' => 'active']);

        $blocker = TimetableSlot::create([
            'school_class_id' => $blockerClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $blockerTeacher->id,
        ]);

        // Something else already took the suggested destination since the
        // suggestion was computed -- the blocker's own teacher is now busy there.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timings['Monday2']->id,
            'subject_id' => $subject->id, 'teacher_id' => $blockerTeacher->id,
        ]);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $sharedTeacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation(
            $newPlacement, $blocker->id, $timings['Monday2']->id
        );

        $this->assertFalse($result['applied']);
        $this->assertSame($timings['Monday1']->id, $blocker->fresh()->bell_timing_id, 'The blocker must not have moved.');
        $this->assertDatabaseMissing('timetable_slots', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
        ]);
    }

    public function test_rejects_when_the_blocking_slot_no_longer_exists(): void
    {
        $timings = $this->makeGrid();
        $newClass = SchoolClass::create(['name' => 'New Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'AF' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Teacher', 'status' => 'active']);

        $newPlacement = [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ];

        $result = (new TimetableAutoFixService())->applyBlockerRelocation($newPlacement, 999999, $timings['Monday2']->id);

        $this->assertFalse($result['applied']);
    }
}
