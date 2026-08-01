<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Whole-school Generate: the solver/job/model already supported an
 * arbitrary class collection (built that way in T4a/T4b); this covers the
 * new wiring above it -- generate() accepting multiple classes, the
 * scope-selection screen, and the batch review/publish/discard page.
 */
class TimetableWholeSchoolGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = '2026-2027';

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * $count classes, each with its own subject + teacher (no cross-class
     * teacher contention) so every class solves to 100% independently --
     * keeps the per-class breakdown assertions simple and deterministic.
     * A 4-day x 2-period grid, periods_per_week=4, matches
     * TimetableGenerationWorkflowTest's fixture shape.
     */
    private function seedClasses(int $count): array
    {
        AcademicSession::create([
            'name' => self::YEAR, 'code' => self::YEAR,
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);

        foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday'] as $day) {
            for ($p = 1; $p <= 2; $p++) {
                BellTiming::create([
                    'day_of_week' => $day, 'period_name' => "P{$p}",
                    'start_time' => sprintf('%02d:00:00', 7 + $p), 'end_time' => sprintf('%02d:45:00', 7 + $p),
                    'is_active' => true, 'is_break' => false, 'order_index' => $p, 'academic_year' => self::YEAR,
                ]);
            }
        }

        $classes = [];
        for ($i = 1; $i <= $count; $i++) {
            $class = SchoolClass::create(['name' => "WS Class {$i}", 'class_order' => $i, 'is_active' => true]);
            $subject = Subject::create(['name' => "WS Subject {$i}", 'code' => 'WS' . $i . uniqid()]);
            $teacher = Teacher::create(['name' => "WS Teacher {$i}", 'status' => 'active']);

            TeacherClassSubjectAssignment::create([
                'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
                'periods_per_week' => 4, 'academic_year' => self::YEAR,
            ]);

            $classes[] = compact('class', 'subject', 'teacher');
        }

        return $classes;
    }

    private function generateFor(User $admin, array $classIds): TimetableGeneration
    {
        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => $classIds,
        ]);
        $response->assertOk();

        return TimetableGeneration::findOrFail($response->json('generation_id'));
    }

    public function test_admin_can_open_the_generate_form(): void
    {
        $admin = $this->makeAdmin();
        $this->seedClasses(2);

        $response = $this->actingAs($admin)->get(route('timetable.generate.form'));

        $response->assertOk();
        $response->assertSee('WS Class 1');
        $response->assertSee('WS Class 2');
        $response->assertSee('Select all active classes');
    }

    public function test_generate_for_multiple_classes_creates_drafts_for_all_of_them(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(3);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        $generation = $this->generateFor($admin, $classIds);

        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertSame($classIds, $generation->school_class_ids);
        $this->assertSame(12, $generation->placed_count); // 3 classes x 4 periods
        $this->assertSame(0, $generation->unplaced_count);

        foreach ($classIds as $classId) {
            $this->assertSame(4, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->where('school_class_id', $classId)->count());
        }
    }

    public function test_review_page_shows_correct_per_class_breakdown(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(2);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        $generation = $this->generateFor($admin, $classIds);

        $response = $this->actingAs($admin)->get(route('timetable.generation.review', $generation));

        $response->assertOk();
        $response->assertSee('WS Class 1');
        $response->assertSee('WS Class 2');
        $response->assertSee('Publish All');
        $response->assertSee('Discard All');
    }

    public function test_publish_all_flips_every_selected_class_atomically(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(3);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        // Pre-existing published slot for one of the classes, to prove it archives.
        $timing = BellTiming::where('day_of_week', 'Monday')->where('period_name', 'P1')->first();
        $preExisting = TimetableSlot::create([
            'school_class_id' => $classIds[0], 'bell_timing_id' => $timing->id,
            'subject_id' => $classes[0]['subject']->id, 'teacher_id' => $classes[0]['teacher']->id,
            'academic_year' => self::YEAR,
        ]);

        $generation = $this->generateFor($admin, $classIds);

        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))
            ->assertRedirect(route('timetable.generation.review', $generation));

        $this->assertSame('archived', $preExisting->fresh()->status);
        $this->assertSame(TimetableGeneration::STATUS_PUBLISHED, $generation->fresh()->status);

        foreach ($classIds as $classId) {
            $this->assertSame(4, TimetableSlot::published()->where('timetable_generation_id', $generation->id)->where('school_class_id', $classId)->count());
            $this->assertSame(0, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->where('school_class_id', $classId)->count());
        }
    }

    public function test_discard_all_removes_drafts_for_every_class_leaving_published_untouched(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(3);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        $before = TimetableSlot::published()->orderBy('id')->get()->toArray();

        $generation = $this->generateFor($admin, $classIds);

        $this->actingAs($admin)->post(route('timetable.generation.discard', $generation))
            ->assertRedirect(route('timetable.generation.review', $generation));

        $this->assertSame(TimetableGeneration::STATUS_DISCARDED, $generation->fresh()->status);
        $this->assertSame(0, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->count());
        $this->assertSame($before, TimetableSlot::published()->orderBy('id')->get()->toArray());
    }

    public function test_single_class_generation_still_redirects_to_that_classs_grid(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(1);
        $classId = $classes[0]['class']->id;

        $generation = $this->generateFor($admin, [$classId]);

        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))
            ->assertRedirect(route('timetable.index', ['school_class_id' => $classId]));
    }

    /** Realistic-scale sanity check, mirroring GeneratorServiceTest's 12-section fixture pattern. */
    public function test_whole_school_generation_at_realistic_scale(): void
    {
        $admin = $this->makeAdmin();
        $classes = $this->seedClasses(12);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        $start = microtime(true);
        $generation = $this->generateFor($admin, $classIds);
        $elapsed = microtime(true) - $start;

        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertSame(48, $generation->placed_count); // 12 classes x 4 periods
        $this->assertSame(0, $generation->unplaced_count);
        $this->assertLessThan(30.0, $elapsed, 'A 12-class whole-school generation should complete well inside the job timeout');

        $response = $this->actingAs($admin)->get(route('timetable.generation.review', $generation));
        $response->assertOk();
        foreach ($classes as $c) {
            $response->assertSee($c['class']->name);
        }
    }

    public function test_non_admin_cannot_open_the_generate_form_or_generate_for_multiple_classes(): void
    {
        $teacherUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $teacherUser->roles()->attach($role->id);
        $classes = $this->seedClasses(2);
        $classIds = array_map(fn ($c) => $c['class']->id, $classes);

        $this->actingAs($teacherUser)->get(route('timetable.generate.form'))->assertForbidden();
        $this->actingAs($teacherUser)->postJson(route('timetable.generate'), ['school_class_ids' => $classIds])->assertForbidden();
        $this->assertSame(0, TimetableGeneration::count());
    }
}
