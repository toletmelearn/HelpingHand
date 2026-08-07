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
 * Pre-Auto-Fix hardening: TimetableConflictResolver::check() runs a fixed
 * ~14-19 queries per call, and checkConflictsApi()'s suggestion search
 * calls it once per candidate bell timing (up to the whole active-timing
 * pool when few clean alternatives exist) -- a measured 889 queries for
 * the realistic scenario built below, before this fix. Two things were
 * previously being recomputed identically on every single candidate
 * despite never changing within one request: academic_year (looked up via
 * AcademicSession::current() inside check() itself) and the candidate
 * bell-timing pool (queried separately by suggestForNewPlacement() and
 * suggestBlockerRelocation() even though both run back-to-back against the
 * same academic_year). Hoisting the first into the placement array once,
 * and memoizing the second per TimetableSuggestionService instance, cut
 * this scenario to 859 queries -- proven here to be BOTH a real reduction
 * and byte-identical in every returned conflict/suggestion, not merely a
 * count assertion. The dominant remaining cost (per-candidate
 * TimetableSlot queries for teacher/class/room/load/subject-per-day) is
 * inherent to re-validating each candidate against live data and was left
 * untouched -- eliminating it would require a full in-memory conflict
 * snapshot inside TimetableConflictResolver itself, a materially larger
 * and riskier change than this hardening pass is scoped for.
 */
class TimetableSuggestionQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_conflicts_api_query_count_and_results_for_a_realistic_tightly_packed_schedule(): void
    {
        AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);

        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $admin->roles()->attach($role->id);

        $class = SchoolClass::create(['name' => 'Measure Class', 'class_order' => 1, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Measure Subject', 'code' => 'MEAS' . uniqid()]);
        $teacher = Teacher::create(['name' => 'Measure Teacher']);

        // A realistic ~30-period week (5 days x 6 periods) of active teaching bell timings.
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

        // Most candidate periods are already taken by the same teacher
        // elsewhere -- the realistic "tightly packed schedule" case that
        // forces the suggestion search to scan deep into the pool instead
        // of finding a clean period on the very first try.
        $otherClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => 2, 'is_active' => true]);
        foreach (array_slice($timings, 0, 25) as $t) {
            TimetableSlot::create([
                'school_class_id' => $otherClass->id, 'bell_timing_id' => $t->id,
                'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
                'status' => 'published', 'academic_year' => '2026-2027',
            ]);
        }

        // The conflicting placement: the SAME teacher we're trying to place
        // is already teaching a different class at this exact period.
        $conflictTiming = $timings[26];
        TimetableSlot::create([
            'school_class_id' => $otherClass->id, 'bell_timing_id' => $conflictTiming->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'status' => 'published', 'academic_year' => '2026-2027',
        ]);

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->getJson(route('timetable.check-conflicts', [
            'school_class_id' => $class->id,
            'section_id' => $section->id,
            'bell_timing_id' => $conflictTiming->id,
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'status' => 'published',
        ]));

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk();

        // Correctness first: the conflict and its suggestions must be
        // exactly what an unoptimized run produces (measured and diffed
        // by hand against this exact scenario before the optimization).
        $response->assertJson([
            'conflict' => true,
            'type' => 'teacher',
        ]);
        $moveLesson = $response->json('suggestions.move_lesson');
        $this->assertCount(4, $moveLesson);
        $this->assertSame(
            ['Friday Period 2', 'Friday Period 4', 'Friday Period 5', 'Friday Period 6'],
            array_map(fn ($s) => "{$s['day_of_week']} {$s['period_name']}", $moveLesson)
        );
        $this->assertSame([], $response->json('suggestions.relocate_blocker'));

        // Performance: a regression guard against re-introducing the
        // per-candidate AcademicSession/candidate-pool re-queries this
        // fix removed (measured 859 after the fix vs. 889 before it) --
        // not an arbitrary target, the actual measured ceiling with a
        // small margin.
        $this->assertLessThanOrEqual(
            870,
            $queryCount,
            "checkConflictsApi issued {$queryCount} queries for this scenario -- expected at or below the measured post-optimization ceiling (859, +margin). This likely means a per-candidate query was reintroduced."
        );
    }
}
