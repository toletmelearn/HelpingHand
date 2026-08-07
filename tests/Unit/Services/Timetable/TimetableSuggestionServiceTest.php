<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Services\Timetable\TimetableSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 (Smart Suggestions): every suggestion this service returns must
 * be one TimetableConflictResolver itself already confirmed clean --
 * these tests prove that end to end (a suggested period genuinely has no
 * conflict when actually checked), not just that some period was picked.
 */
class TimetableSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeClass(string $name = 'Class S'): SchoolClass
    {
        return SchoolClass::create(['name' => $name, 'class_order' => random_int(1, 100000), 'is_active' => true]);
    }

    /** 3 days x 2 periods, all active teaching periods. */
    private function makeGrid(): array
    {
        $timings = [];
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) {
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

    public function test_suggests_a_clean_alternative_period_for_a_new_placement(): void
    {
        $timings = $this->makeGrid();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Science', 'code' => 'SS' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Busy Teacher', 'status' => 'active']);
        $otherClass = $this->makeClass('Class T');

        // Teacher is already busy at Monday P1 with a different class --
        // the exact conflict this class's Monday P1 request would hit.
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        $suggestions = (new TimetableSuggestionService())->suggestForNewPlacement([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertNotEmpty($suggestions);
        $suggestedIds = collect($suggestions)->pluck('bell_timing_id');
        $this->assertNotContains($timings['Monday1']->id, $suggestedIds, 'Must not re-suggest the exact period that conflicts.');

        // Every suggestion must independently check out clean.
        $resolver = new \App\Services\Timetable\TimetableConflictResolver();
        foreach ($suggestions as $s) {
            $check = $resolver->check([
                'school_class_id' => $class->id, 'bell_timing_id' => $s['bell_timing_id'],
                'teacher_id' => $teacher->id, 'subject_id' => $subject->id,
            ]);
            $this->assertFalse($check['conflict'], "Suggested period {$s['description']} must genuinely be clean.");
        }
    }

    public function test_suggests_relocating_the_blocking_lesson_instead(): void
    {
        $timings = $this->makeGrid();
        $class = $this->makeClass();
        $subject = Subject::create(['name' => 'Science', 'code' => 'SS' . uniqid()]);
        $newTeacher = Teacher::create(['name' => 'New Teacher', 'status' => 'active']);
        $blockerTeacher = Teacher::create(['name' => 'Blocker Teacher', 'status' => 'active']);
        $otherClass = $this->makeClass('Class T');

        $blockerSlot = TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $newTeacher->id,
        ]);

        $suggestions = (new TimetableSuggestionService())->suggestBlockerRelocation([
            'school_class_id' => $class->id,
            'bell_timing_id' => $timings['Monday1']->id,
            'teacher_id' => $newTeacher->id,
            'subject_id' => $subject->id,
        ]);

        $this->assertNotEmpty($suggestions);
        foreach ($suggestions as $s) {
            $this->assertSame($blockerSlot->id, $s['blocking_slot_id']);
        }
    }

    public function test_no_blocker_relocation_suggested_when_the_conflict_has_no_single_blocking_row(): void
    {
        // A period-type violation (break period) has no "blocking slot" to relocate.
        $break = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Break',
            'start_time' => '10:00', 'end_time' => '10:15',
            'is_active' => true, 'is_break' => true, 'order_index' => 3,
            'period_type' => BellTiming::PERIOD_TYPE_BREAK,
        ]);
        $class = $this->makeClass();
        $teacher = Teacher::create(['name' => 'T', 'status' => 'active']);

        $suggestions = (new TimetableSuggestionService())->suggestBlockerRelocation([
            'school_class_id' => $class->id,
            'bell_timing_id' => $break->id,
            'teacher_id' => $teacher->id,
        ]);

        $this->assertSame([], $suggestions);
    }

    public function test_swap_is_ok_when_both_sides_are_clean_in_their_new_period(): void
    {
        $timings = $this->makeGrid();
        $classA = $this->makeClass('Class A');
        $classB = $this->makeClass('Class B');
        $subject = Subject::create(['name' => 'Maths', 'code' => 'SM' . uniqid()]);
        $teacherA = Teacher::create(['name' => 'Teacher A', 'status' => 'active']);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);

        $slotA = TimetableSlot::create([
            'school_class_id' => $classA->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacherA->id,
        ]);
        $slotB = TimetableSlot::create([
            'school_class_id' => $classB->id, 'bell_timing_id' => $timings['Monday2']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacherB->id,
        ]);

        $result = (new TimetableSuggestionService())->checkSwap($slotA->id, $slotB->id);

        $this->assertTrue($result['ok']);
    }

    public function test_swap_is_rejected_when_it_would_create_a_teacher_conflict(): void
    {
        $timings = $this->makeGrid();
        $classA = $this->makeClass('Class A');
        $classB = $this->makeClass('Class B');
        $subject = Subject::create(['name' => 'Maths', 'code' => 'SM' . uniqid()]);
        $sharedTeacher = Teacher::create(['name' => 'Shared', 'status' => 'active']);
        $teacherB = Teacher::create(['name' => 'Teacher B', 'status' => 'active']);

        $slotA = TimetableSlot::create([
            'school_class_id' => $classA->id, 'bell_timing_id' => $timings['Monday1']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);
        $slotB = TimetableSlot::create([
            'school_class_id' => $classB->id, 'bell_timing_id' => $timings['Monday2']->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacherB->id,
        ]);
        // The shared teacher is ALSO already teaching a third class at
        // slotB's period -- swapping A into that period would double-book them.
        $classC = $this->makeClass('Class C');
        TimetableSlot::create([
            'school_class_id' => $classC->id, 'bell_timing_id' => $timings['Monday2']->id,
            'subject_id' => $subject->id, 'teacher_id' => $sharedTeacher->id,
        ]);

        $result = (new TimetableSuggestionService())->checkSwap($slotA->id, $slotB->id);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['a_result']['conflict']);
    }
}
