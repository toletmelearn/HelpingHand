<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lock Integrity hardening pass: TimetableAutoFixService::discoverChain()
 * re-runs a full TimetableConflictResolver::check() (the same ~6-19-query
 * cost TimetableSuggestionQueryCountTest measures for suggestBlockerRelocation())
 * on every recursive entry -- fanned out by both the candidate-period pool
 * and the chain depth, bounded only by config('timetable.autofix.search_budget')
 * (default 150) and config('timetable.autofix.max_chain_depth') (default
 * 3). The read-only production audit that preceded this hardening pass
 * flagged this as an unmeasured N+1 risk (unlike the suggestion-search
 * path, which already has a query-count regression guard) -- this test is
 * that missing baseline, not an optimization.
 *
 * A realistic tightly-packed scenario (~30-period week, one teacher busy
 * across most of it) that resolves as a genuine two-hop chain measured 62
 * queries for the preview endpoint -- cheap relative to the suggestion-
 * search path's 859, because discoverChain()'s greedy strategy commits to
 * the FIRST candidate that works at each level rather than exhausting the
 * whole pool the way an unconstrained suggestion scan does; the search
 * only pays for candidates it actually tries, not the full ~30-period
 * pool. The ceiling below is that measurement plus a margin, exactly
 * mirroring TimetableSuggestionQueryCountTest's own approach. Do not
 * raise this ceiling to make a regression pass -- if a change pushes the
 * count up, that's the thing to investigate.
 */
class TimetableAutoFixQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_chain_query_count_for_a_realistic_two_hop_tightly_packed_scenario(): void
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);

        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $newClass = SchoolClass::create(['name' => 'Chain Measure New', 'class_order' => 1, 'is_active' => true]);
        $classA = SchoolClass::create(['name' => 'Chain Measure A', 'class_order' => 2, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Chain Measure B', 'class_order' => 3, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Chain Measure Subject', 'code' => 'CHM' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Chain Measure Teacher', 'status' => 'active']);

        // A realistic ~30-period week (5 days x 6 periods) of active teaching bell timings -- same shape as TimetableSuggestionQueryCountTest's own scenario.
        $timings = [];
        $order = 1;
        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as $day) {
            for ($p = 1; $p <= 6; $p++) {
                $start = sprintf('%02d:00:00', 7 + $p);
                $end = sprintf('%02d:00:00', 8 + $p);
                $timings[] = BellTiming::create([
                    'day_of_week' => $day, 'period_name' => "Period {$p}",
                    'start_time' => $start, 'end_time' => $end,
                    'is_active' => true, 'is_break' => false, 'order_index' => $order++,
                    'period_type' => 'teaching', 'academic_year' => '2026-2027',
                ]);
            }
        }

        // The shared teacher is busy across most of the week via classA,
        // forcing the search to scan deep into the candidate pool instead
        // of finding a clean period on the first try.
        foreach (array_slice($timings, 0, 25) as $t) {
            TimetableSlot::create([
                'school_class_id' => $classA->id, 'bell_timing_id' => $t->id,
                'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
                'status' => 'published', 'academic_year' => '2026-2027',
            ]);
        }

        // The blocker: the SAME teacher, a different class (B), sitting
        // exactly on the period newClass wants -- a genuine two-hop chain,
        // since relocating the blocker anywhere in the first 25 (tightly
        // packed) periods just re-collides with classA's own schedule.
        $conflictTiming = $timings[26];
        TimetableSlot::create([
            'school_class_id' => $classB->id, 'bell_timing_id' => $conflictTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => 'published', 'academic_year' => '2026-2027',
        ]);

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->getJson(route('timetable.auto-fix.preview-chain', [
            'school_class_id' => $newClass->id,
            'bell_timing_id' => $conflictTiming->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'status' => 'published',
        ]));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();
        $this->assertTrue($response->json('ok'), $response->json('message'));
        $this->assertCount(2, $response->json('steps'), 'This scenario is deliberately a genuine two-hop chain -- see the docblock.');

        // Performance baseline, not a target: a regression guard against
        // discoverChain()'s query cost growing unbounded. Not optimized as
        // part of this hardening pass -- the search's own $budget/$depth
        // config already caps it from running away entirely.
        $this->assertLessThanOrEqual(
            75,
            $queryCount,
            "autoFixPreviewChain (discoverChain) issued {$queryCount} queries for this scenario -- expected at or below the measured baseline ceiling (62, +margin). This likely means a per-candidate or per-recursion query was added."
        );
    }
}
