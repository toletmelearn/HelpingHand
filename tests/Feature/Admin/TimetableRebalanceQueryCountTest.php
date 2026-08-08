<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rebalancing Engine: "measure query behavior rather than assuming it is
 * efficient." TimetableRebalanceService::analyze() re-runs
 * TimetableSuggestionService::checkSwap() / TimetableConflictResolver::
 * check() per candidate (the same ~6-19-query cost every other
 * interactive validation path already pays -- see
 * TimetableSuggestionQueryCountTest / TimetableAutoFixQueryCountTest),
 * fanned out by both slot pairs and candidate periods, but bounded by
 * config('timetable.rebalance.max_candidate_evaluations'). This is that
 * missing baseline for the Rebalancing Engine specifically. Do not raise
 * the ceiling below to make a regression pass -- investigate first.
 */
class TimetableRebalanceQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_query_count_for_a_realistic_three_day_class_with_two_gaps(): void
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);

        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Query Measure Class', 'class_order' => 1, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $teacherA = Teacher::create(['name' => 'Query Teacher A', 'status' => 'active']);
        $teacherB = Teacher::create(['name' => 'Query Teacher B', 'status' => 'active']);

        // A realistic 3-day x 5-period slice (15 candidate periods), not
        // the whole ~30-period week -- Rebalancing is scoped to one class-
        // section, a materially smaller search space than the whole-school
        // suggestion/chain-repair paths those other baselines measure.
        $timings = [];
        $order = 1;
        $subjects = [];
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) {
            for ($p = 1; $p <= 5; $p++) {
                $timings["{$day}{$p}"] = BellTiming::create([
                    'day_of_week' => $day, 'period_name' => "Period {$p}",
                    'start_time' => sprintf('%02d:00:00', 7 + $p), 'end_time' => sprintf('%02d:00:00', 8 + $p),
                    'is_active' => true, 'is_break' => false, 'order_index' => $order++,
                    'period_type' => 'teaching', 'academic_year' => '2026-2027',
                ]);
                $subjects["{$day}{$p}"] = Subject::create(['name' => "QM Subject {$day}{$p}", 'code' => 'QM' . uniqid()]);
            }
        }

        // Two genuine gaps: teacherA at Monday P1+P3 (gap at P2), and
        // teacherA again at Tuesday P1+P4 (a bigger gap) -- realistic
        // "tightly packed but imperfect" week, distinct subjects
        // throughout so nothing trips subject-per-day and masks the search.
        TimetableSlot::create(['school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timings['Monday1']->id, 'subject_id' => $subjects['Monday1']->id, 'teacher_id' => $teacherA->id, 'status' => 'published', 'academic_year' => '2026-2027']);
        TimetableSlot::create(['school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timings['Monday2']->id, 'subject_id' => $subjects['Monday2']->id, 'teacher_id' => $teacherB->id, 'status' => 'published', 'academic_year' => '2026-2027']);
        TimetableSlot::create(['school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timings['Monday3']->id, 'subject_id' => $subjects['Monday3']->id, 'teacher_id' => $teacherA->id, 'status' => 'published', 'academic_year' => '2026-2027']);
        TimetableSlot::create(['school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timings['Tuesday1']->id, 'subject_id' => $subjects['Tuesday1']->id, 'teacher_id' => $teacherA->id, 'status' => 'published', 'academic_year' => '2026-2027']);
        TimetableSlot::create(['school_class_id' => $class->id, 'section_id' => $section->id, 'bell_timing_id' => $timings['Tuesday4']->id, 'subject_id' => $subjects['Tuesday4']->id, 'teacher_id' => $teacherA->id, 'status' => 'published', 'academic_year' => '2026-2027']);

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->getJson(route('timetable.rebalance.preview', [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'published',
        ]));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        // Performance baseline, not a target: analyze() is explicitly
        // bounded by max_candidate_evaluations (100 by default) and
        // max_iterations (6) -- these defaults were themselves lowered
        // from an initial 300/8 after this exact test first measured
        // 1,677 queries for this scenario (each "candidate evaluation" is
        // ~2 checkSwap() calls, not 1 query, so the nominal budget number
        // understated real DB cost by an order of magnitude). Re-measured
        // at 1,361 after the fix; the ceiling below is that measurement
        // plus a margin, exactly mirroring TimetableSuggestionQueryCountTest's
        // own approach. Further reduction would need a genuinely different
        // algorithm (e.g. an in-memory conflict snapshot) -- out of scope
        // for this pass; the budget itself is what stops this from growing
        // unbounded in the meantime.
        $this->assertLessThanOrEqual(
            1450,
            $queryCount,
            "rebalancePreview (analyze) issued {$queryCount} queries for this scenario -- expected at or below the measured baseline ceiling (1,361 + margin). Investigate before raising this ceiling."
        );
    }
}
