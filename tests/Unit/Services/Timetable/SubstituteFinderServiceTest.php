<?php

namespace Tests\Unit\Services\Timetable;

use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Services\Timetable\SubstituteFinderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T3 item 2: SubstituteFinderService's real scoring, replacing the old
 * stub methods. Scenarios seeded and hand-counted per assertion.
 */
class SubstituteFinderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubstitution(array $overrides = []): array
    {
        $timing = $overrides['bell_timing'] ?? BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $class = SchoolClass::create(['name' => 'Class 7B', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Maths', 'code' => 'MATH' . uniqid()]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher', 'status' => 'active']);

        $substitution = TeacherSubstitution::create([
            'substitution_date' => $overrides['date'] ?? now()->format('Y-m-d'),
            'absent_teacher_id' => $absentTeacher->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'subject_id' => $subject->id,
            'bell_timing_id' => $timing->id,
            'status' => 'pending',
            'created_by' => 1,
        ])->fresh(['bellTiming', 'class', 'subject']);

        return compact('substitution', 'timing', 'class', 'section', 'subject', 'absentTeacher');
    }

    public function test_absent_teacher_is_never_a_candidate(): void
    {
        $data = $this->makeSubstitution();

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertFalse(collect($candidates)->pluck('teacher.id')->contains($data['absentTeacher']->id));
    }

    public function test_busy_teacher_from_timetable_slots_is_excluded(): void
    {
        $data = $this->makeSubstitution();
        $busyTeacher = Teacher::create(['name' => 'Busy Teacher', 'status' => 'active']);
        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);

        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $data['timing']->id,
            'subject_id' => $otherSubject->id,
            'teacher_id' => $busyTeacher->id,
        ]);

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertFalse(collect($candidates)->pluck('teacher.id')->contains($busyTeacher->id));
    }

    public function test_teacher_blocked_by_availability_is_excluded(): void
    {
        $data = $this->makeSubstitution();
        $blockedTeacher = Teacher::create(['name' => 'Blocked Teacher', 'status' => 'active']);

        TeacherAvailability::create([
            'teacher_id' => $blockedTeacher->id,
            'bell_timing_id' => $data['timing']->id,
            'is_available' => false,
        ]);

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertFalse(collect($candidates)->pluck('teacher.id')->contains($blockedTeacher->id));
    }

    public function test_teacher_already_substituting_that_exact_period_elsewhere_is_excluded(): void
    {
        $data = $this->makeSubstitution();
        $busyElsewhere = Teacher::create(['name' => 'Already Substituting', 'status' => 'active']);

        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherSection = Section::create(['name' => 'B', 'class_id' => $otherClass->id]);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $otherAbsentTeacher = Teacher::create(['name' => 'Other Absent', 'status' => 'active']);

        TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $otherAbsentTeacher->id,
            'substitute_teacher_id' => $busyElsewhere->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'subject_id' => $otherSubject->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'created_by' => 1,
        ]);

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertFalse(collect($candidates)->pluck('teacher.id')->contains($busyElsewhere->id));
    }

    public function test_cancelled_substitution_elsewhere_does_not_exclude_the_teacher(): void
    {
        $data = $this->makeSubstitution();
        $freeTeacher = Teacher::create(['name' => 'Free After Cancel', 'status' => 'active']);

        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherSection = Section::create(['name' => 'B', 'class_id' => $otherClass->id]);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        $otherAbsentTeacher = Teacher::create(['name' => 'Other Absent', 'status' => 'active']);

        TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $otherAbsentTeacher->id,
            'substitute_teacher_id' => $freeTeacher->id,
            'class_id' => $otherClass->id,
            'section_id' => $otherSection->id,
            'subject_id' => $otherSubject->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'cancelled',
            'created_by' => 1,
        ]);

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertTrue(collect($candidates)->pluck('teacher.id')->contains($freeTeacher->id));
    }

    public function test_teacher_matching_class_and_subject_outranks_a_free_only_teacher(): void
    {
        $data = $this->makeSubstitution();
        $matchingTeacher = Teacher::create(['name' => 'Matching Teacher', 'status' => 'active']);
        $plainTeacher = Teacher::create(['name' => 'Plain Free Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $matchingTeacher->id,
            'class_id' => $data['class']->id,
            'subject_id' => $data['subject']->id,
        ]);

        $candidates = collect((new SubstituteFinderService())->findCandidates($data['substitution']))->keyBy(fn ($c) => $c['teacher']->id);

        $this->assertGreaterThan($candidates[$plainTeacher->id]['score'], $candidates[$matchingTeacher->id]['score']);
        $this->assertStringContainsString('Teaches Class 7B Maths', $candidates[$matchingTeacher->id]['reason_text']);
    }

    public function test_fewer_periods_today_scores_higher_between_otherwise_equal_candidates(): void
    {
        $data = $this->makeSubstitution();
        $busyToday = Teacher::create(['name' => 'Busy Today', 'status' => 'active']);
        $freeToday = Teacher::create(['name' => 'Free Today', 'status' => 'active']);

        // Give busyToday 3 other periods on the SAME day (different bell timings).
        $otherClass = SchoolClass::create(['name' => 'Other', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $otherSubject = Subject::create(['name' => 'Science', 'code' => 'SCI' . uniqid()]);
        for ($i = 2; $i <= 4; $i++) {
            $t = BellTiming::create(['day_of_week' => 'Monday', 'period_name' => "P{$i}", 'start_time' => "0{$i}:00", 'end_time' => "0{$i}:45", 'is_active' => true, 'is_break' => false, 'order_index' => $i]);
            TimetableSlot::create(['school_class_id' => $otherClass->id, 'bell_timing_id' => $t->id, 'subject_id' => $otherSubject->id, 'teacher_id' => $busyToday->id]);
        }

        $candidates = collect((new SubstituteFinderService())->findCandidates($data['substitution']))->keyBy(fn ($c) => $c['teacher']->id);

        $this->assertGreaterThan($candidates[$busyToday->id]['score'], $candidates[$freeToday->id]['score']);
        $this->assertStringContainsString('0 periods today', $candidates[$freeToday->id]['reason_text']);
        $this->assertStringContainsString('3 periods today', $candidates[$busyToday->id]['reason_text']);
    }

    public function test_candidates_are_ranked_highest_score_first(): void
    {
        $data = $this->makeSubstitution();
        $best = Teacher::create(['name' => 'Best Match', 'status' => 'active']);
        $worst = Teacher::create(['name' => 'Worst Match', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $best->id,
            'class_id' => $data['class']->id,
            'subject_id' => $data['subject']->id,
        ]);

        $candidates = (new SubstituteFinderService())->findCandidates($data['substitution']);

        $this->assertSame($best->id, $candidates[0]['teacher']->id);
        $scores = collect($candidates)->pluck('score')->all();
        $this->assertSame($scores, collect($scores)->sortDesc()->values()->all());
    }
}
