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
 * T4b item 6: the plan's required tests for the generate/review/publish/
 * discard workflow. QUEUE_CONNECTION=sync in phpunit.xml, so
 * GenerateTimetableJob runs synchronously inside the generate() POST --
 * no queue faking needed to observe a completed generation immediately.
 */
class TimetableGenerationWorkflowTest extends TestCase
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

    private function makeTeacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * One class, one teacher, one subject, a 4-day x 2-period grid.
     * periods_per_week=4, non-consecutive, caps at 1/day -- needs 4
     * distinct days to reach 100%, hence 4 days, not 2.
     */
    private function seedGeneratableClass(): array
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

        $class = SchoolClass::create(['name' => 'Class Gen', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Generatable Subject', 'code' => 'GEN1']);
        $teacher = Teacher::create(['name' => 'Generatable Teacher', 'status' => 'active']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'periods_per_week' => 4,
            'academic_year' => self::YEAR,
        ]);

        return compact('class', 'subject', 'teacher');
    }

    private function snapshotPublishedSlots(): array
    {
        return TimetableSlot::published()
            ->orderBy('id')
            ->get()
            ->map(fn (TimetableSlot $s) => $s->only([
                'id', 'school_class_id', 'section_id', 'bell_timing_id', 'subject_id',
                'teacher_id', 'combined_class_group_id', 'room_number', 'academic_year', 'status',
            ]))
            ->all();
    }

    public function test_generate_then_discard_leaves_published_slots_byte_identical(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedGeneratableClass();

        // A pre-existing published slot for an unrelated period, so there's
        // something real to prove untouched.
        $otherTiming = BellTiming::where('day_of_week', 'Monday')->where('period_name', 'P1')->first();
        $preExisting = TimetableSlot::create([
            'school_class_id' => $data['class']->id,
            'bell_timing_id' => $otherTiming->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'academic_year' => self::YEAR,
        ]);
        $this->assertSame('published', $preExisting->fresh()->status);

        $before = $this->snapshotPublishedSlots();

        $genResponse = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['class']->id],
        ]);
        $genResponse->assertOk();
        $generation = TimetableGeneration::findOrFail($genResponse->json('generation_id'));

        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertGreaterThan(0, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->count());

        $this->actingAs($admin)->post(route('timetable.generation.discard', $generation))->assertRedirect();

        $this->assertSame(0, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->count());
        $this->assertSame(TimetableGeneration::STATUS_DISCARDED, $generation->fresh()->status);

        $after = $this->snapshotPublishedSlots();
        $this->assertSame($before, $after, 'Published slots must be byte-identical after a generate+discard cycle.');
    }

    public function test_full_generate_review_publish_flow(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedGeneratableClass();

        $timing = BellTiming::where('day_of_week', 'Monday')->where('period_name', 'P1')->first();
        $preExisting = TimetableSlot::create([
            'school_class_id' => $data['class']->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'academic_year' => self::YEAR,
        ]);

        $genResponse = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['class']->id],
        ]);
        $genResponse->assertOk();
        $generation = TimetableGeneration::findOrFail($genResponse->json('generation_id'));
        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertSame(4, $generation->placed_count);
        $this->assertSame(0, $generation->unplaced_count);

        // Status/polling endpoint.
        $statusResponse = $this->actingAs($admin)->getJson(route('timetable.generation.status', $generation));
        $statusResponse->assertOk()->assertJson([
            'status' => 'completed',
            'placed_count' => 4,
            'unplaced_count' => 0,
            'placement_percent' => 100.0,
        ]);

        // Review page: draft toggle shows the draft grid for this class.
        $reviewResponse = $this->actingAs($admin)->get(route('timetable.index', [
            'school_class_id' => $data['class']->id, 'status' => 'draft',
        ]));
        $reviewResponse->assertOk();
        $reviewResponse->assertSee('Draft review');

        $draftCount = TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->count();
        $this->assertSame(4, $draftCount);

        // Publish.
        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))->assertRedirect();

        $this->assertSame('archived', $preExisting->fresh()->status);
        $this->assertSame(TimetableGeneration::STATUS_PUBLISHED, $generation->fresh()->status);
        $this->assertSame(0, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->count());
        $this->assertSame(4, TimetableSlot::published()->where('timetable_generation_id', $generation->id)->count());

        // Published readers (feasibility/PDF/substitution) now see exactly
        // the published set: the 4 newly-published + 0 of the archived one.
        $this->assertSame(4, TimetableSlot::published()->where('school_class_id', $data['class']->id)->count());
    }

    public function test_publish_is_atomic_a_failed_flip_leaves_nothing_changed(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedGeneratableClass();

        $genResponse = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['class']->id],
        ]);
        $generation = TimetableGeneration::findOrFail($genResponse->json('generation_id'));
        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);

        // Force a real collision: an unrelated class already has a
        // PUBLISHED slot for this same teacher at one of the draft's exact
        // bell timings. That other class is NOT part of this generation's
        // scope, so it will never be archived -- when the draft flips to
        // published, the teacher+bell_timing+status=published unique key
        // collides, and the whole publish transaction must roll back.
        $conflictingSlot = TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->first();
        $otherClass = SchoolClass::create(['name' => 'Other Class', 'class_order' => 99, 'is_active' => true]);
        TimetableSlot::create([
            'school_class_id' => $otherClass->id,
            'bell_timing_id' => $conflictingSlot->bell_timing_id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'academic_year' => self::YEAR,
        ]);

        $draftIdsBefore = TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->pluck('status', 'id')->all();
        $publishedCountBefore = TimetableSlot::published()->count();
        $archivedCountBefore = TimetableSlot::archived()->count();

        try {
            $this->actingAs($admin)->post(route('timetable.generation.publish', $generation));
        } catch (\Illuminate\Database\QueryException $e) {
            // Expected in this test environment where the exception isn't
            // swallowed into a 500 response -- either way, what matters is
            // the assertions below: nothing committed.
        }

        // Nothing changed: the draft rows are still draft, nothing new got
        // archived, nothing new got published, and the generation itself
        // was never marked 'published' -- the whole transaction rolled back.
        $this->assertSame($draftIdsBefore, TimetableSlot::draft()->where('timetable_generation_id', $generation->id)->pluck('status', 'id')->all());
        $this->assertSame($publishedCountBefore, TimetableSlot::published()->count());
        $this->assertSame($archivedCountBefore, TimetableSlot::archived()->count());
        $this->assertNotSame(TimetableGeneration::STATUS_PUBLISHED, $generation->fresh()->status);
    }

    public function test_non_admin_cannot_generate(): void
    {
        $teacherUser = $this->makeTeacherUser();
        $data = $this->seedGeneratableClass();

        $response = $this->actingAs($teacherUser)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['class']->id],
        ]);

        $response->assertForbidden();
        $this->assertSame(0, TimetableGeneration::count());
    }

    public function test_non_admin_cannot_publish_or_discard(): void
    {
        $admin = $this->makeAdmin();
        $teacherUser = $this->makeTeacherUser();
        $data = $this->seedGeneratableClass();

        $genResponse = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['class']->id],
        ]);
        $generation = TimetableGeneration::findOrFail($genResponse->json('generation_id'));

        $this->actingAs($teacherUser)->post(route('timetable.generation.publish', $generation))->assertForbidden();
        $this->actingAs($teacherUser)->post(route('timetable.generation.discard', $generation))->assertForbidden();

        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->fresh()->status);
    }
}
