<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TimetableGeneration;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T6 item 5, revised for per-section class teachers: the guided "Set Up
 * Timetable" wizard is a UI orchestration layer only -- every assertion
 * here checks that a wizard step wrote (or reads) the SAME data the
 * individual admin pages already use (teacher_class_subject_assignments
 * scoped by class_id+section_id, FeasibilityService, the real
 * generate/publish endpoints), not a parallel data model.
 *
 * The standalone "pick class teacher first" screen is gone -- the class
 * teacher is just the "is_class_teacher" checkbox on a subject row, made
 * once the subject is known. Rows are walked one CLASS-SECTION at a time,
 * not one class at a time, because real schools (e.g. III-A/Prashant
 * Patra vs III-B/Lalita Devi) assign a different class teacher per
 * section, not per class.
 */
class TimetableWizardTest extends TestCase
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

    /** @return array{classA: SchoolClass, classB: SchoolClass, subject: Subject, teacherA: Teacher, teacherB: Teacher} */
    private function seedSchool(): array
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

        $classA = SchoolClass::create(['name' => 'Wizard Class A', 'class_order' => 1, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Wizard Class B', 'class_order' => 2, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Wizard Maths', 'code' => 'WZM' . uniqid()]);
        $teacherA = Teacher::create(['name' => 'Wizard Teacher A', 'status' => 'active']);
        $teacherB = Teacher::create(['name' => 'Wizard Teacher B', 'status' => 'active']);

        return compact('classA', 'classB', 'subject', 'teacherA', 'teacherB');
    }

    public function test_non_admin_cannot_access_the_wizard(): void
    {
        $teacherUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $teacherUser->roles()->attach($role->id);

        $this->actingAs($teacherUser)->get(route('timetable.wizard.index'))->assertForbidden();
    }

    public function test_index_redirects_to_the_first_class_sections_grid(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.index'));

        $response->assertRedirect(route('timetable.wizard.step1', [$data['classA']]));
    }

    public function test_step1_shows_the_subject_grid_for_a_sectionless_class(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step1', [$data['classA']]));

        $response->assertOk();
        $response->assertSee('Wizard Class A');
        $response->assertSee('Wizard Teacher A');
        $response->assertSee('Class-section 1 of 2');
    }

    /**
     * The exact scenario found live in this project's own dev database:
     * a retired/other-year assignment row (e.g. leftover walkthrough test
     * data) with a section_id set must not make a class appear split into
     * sections it doesn't actually have for the CURRENT year -- it should
     * still show as a single, sectionless class-section entry.
     */
    public function test_an_other_year_assignment_does_not_falsely_split_a_sectionless_class(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $section = Section::create(['name' => 'A']);

        TeacherClassSubjectAssignment::create([
            'teacher_id' => $data['teacherA']->id,
            'class_id' => $data['classA']->id,
            'section_id' => $section->id,
            'subject_id' => $data['subject']->id,
            'academic_year' => self::YEAR . '-WALKTHROUGH-ARCHIVED',
        ]);

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step1', [$data['classA']]));

        $response->assertOk();
        // Still "1 of 2" (Class A + Class B, both sectionless) -- the
        // other-year section-A row must not add a phantom extra entry.
        $response->assertSee('Class-section 1 of 2');
    }

    public function test_step2_shows_style_options_with_the_period_1_note(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step2'));

        $response->assertOk();
        $response->assertSee('Different Each Day');
        $response->assertSee('Same Every Day');
        $response->assertSee('Period 1 every day');
    }

    public function test_step1_store_writes_the_same_is_class_teacher_row_the_standalone_form_would(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA']]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $response->assertRedirect(route('timetable.wizard.step1', [$data['classB']]));

        $assignment = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->first();
        $this->assertNotNull($assignment);
        $this->assertNull($assignment->section_id);
        $this->assertSame($data['teacherA']->id, $assignment->teacher_id);
        $this->assertSame($data['subject']->id, $assignment->subject_id);
        $this->assertTrue($assignment->is_class_teacher);
        $this->assertSame(4, $assignment->periods_per_week);
        $this->assertSame(self::YEAR, $assignment->academic_year);
    }

    public function test_step1_store_redirects_to_step2_after_the_last_class_section(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classB']]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4],
            ],
        ]);

        $response->assertRedirect(route('timetable.wizard.step2'));
    }

    /**
     * The core gap this revision fixes: III-A and III-B (two sections of
     * ONE class) must be able to have two different class teachers, saved
     * independently, with neither overwriting the other.
     */
    public function test_two_sections_of_the_same_class_persist_different_class_teachers_without_overwriting(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $sectionA = Section::create(['name' => 'A']);
        $sectionB = Section::create(['name' => 'B']);

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionA->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionB->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $rowA = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->where('section_id', $sectionA->id)->first();
        $rowB = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->where('section_id', $sectionB->id)->first();

        $this->assertNotNull($rowA, 'Section A must have its own assignment row.');
        $this->assertNotNull($rowB, 'Section B must have its own assignment row.');
        $this->assertSame($data['teacherA']->id, $rowA->teacher_id);
        $this->assertSame($data['teacherB']->id, $rowB->teacher_id);
        $this->assertTrue($rowA->is_class_teacher, 'Section A class-teacher flag must survive Section B being saved afterwards.');
        $this->assertTrue($rowB->is_class_teacher);
    }

    /**
     * The swap-out rule ("only one is_class_teacher row per class") must
     * be scoped by section, not just class -- otherwise saving Section
     * B's class teacher would silently unset Section A's.
     */
    public function test_saving_one_sections_class_teacher_does_not_unset_a_different_sections(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $sectionA = Section::create(['name' => 'A']);
        $sectionB = Section::create(['name' => 'B']);
        $otherSubject = Subject::create(['name' => 'Wizard Science', 'code' => 'WZS' . uniqid()]);

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionA->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionB->id]), [
            'rows' => [
                ['subject_id' => $otherSubject->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $rowA = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->where('section_id', $sectionA->id)->first();
        $this->assertTrue($rowA->fresh()->is_class_teacher, 'Section A must be unaffected by Section B\'s save.');
    }

    public function test_step1_persists_subject_rows_independently_per_section(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $sectionA = Section::create(['name' => 'A']);
        $sectionB = Section::create(['name' => 'B']);

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionA->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 5],
            ],
        ]);
        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionB->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 3],
            ],
        ]);

        $rowA = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->where('section_id', $sectionA->id)->first();
        $rowB = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->where('section_id', $sectionB->id)->first();

        $this->assertSame(5, $rowA->periods_per_week);
        $this->assertSame(3, $rowB->periods_per_week);
    }

    public function test_step3_shows_the_same_readiness_notes_feasibility_service_produces(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool(); // neither class has a class-teacher yet

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step3', ['style' => 'rotating']));

        $response->assertOk();
        $response->assertSee('Wizard Class A has no class teacher assigned.');
        $response->assertSee('Wizard Class B has no class teacher assigned.');
    }

    public function test_step3_link_to_generate_carries_the_chosen_style(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step3', ['style' => 'fixed_daily']));

        $response->assertOk();
        $response->assertSee('style=fixed_daily', false);
        $response->assertSee('select_all=1', false);
    }

    /**
     * The plan's own required test, revised for the real structure this
     * fix targets: a full wizard run where ONE class has TWO sections
     * with TWO different class teachers ends in a published timetable
     * where EACH section's own class-teacher holds period 1 -- proving
     * the is_class_teacher flag drives the generator's period-1 rule per
     * section, not per class.
     */
    public function test_full_wizard_run_gives_each_section_its_own_class_teacher_at_period_1(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $sectionA = Section::create(['name' => 'A']);
        $sectionB = Section::create(['name' => 'B']);

        // Step 1, Class A / Section A -- its own class-teacher's subject.
        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionA->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect();

        // Step 1, Class A / Section B -- a DIFFERENT class-teacher, same class.
        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA'], $sectionB->id]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect();

        // Step 2 -> Step 3 (plain navigation, no persistence).
        $this->actingAs($admin)->get(route('timetable.wizard.step2'))->assertOk();
        $this->actingAs($admin)->get(route('timetable.wizard.step3', ['style' => 'rotating']))->assertOk();

        // Step 4 -- the existing, already-tested generate/publish flow.
        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['classA']->id],
            'style' => 'rotating',
        ]);
        $response->assertOk();
        $generation = TimetableGeneration::findOrFail($response->json('generation_id'));
        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertSame(0, $generation->unplaced_count);

        // Publishing a single-class generation redirects to the class's own
        // timetable view, not the generation review page (pre-existing
        // TimetableController::redirectAfterGenerationAction behaviour,
        // unrelated to this revision).
        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))
            ->assertRedirect(route('timetable.index', ['school_class_id' => $data['classA']->id]));

        // Each section's own class-teacher holds period 1, every running day.
        $period1Ids = BellTiming::where('order_index', 1)->pluck('id')->all();
        $sectionATeacherPeriod1Count = TimetableSlot::published()
            ->where('school_class_id', $data['classA']->id)
            ->where('section_id', $sectionA->id)
            ->where('teacher_id', $data['teacherA']->id)
            ->whereIn('bell_timing_id', $period1Ids)
            ->count();
        $sectionBTeacherPeriod1Count = TimetableSlot::published()
            ->where('school_class_id', $data['classA']->id)
            ->where('section_id', $sectionB->id)
            ->where('teacher_id', $data['teacherB']->id)
            ->whereIn('bell_timing_id', $period1Ids)
            ->count();

        $this->assertSame(4, $sectionATeacherPeriod1Count, 'Section A class-teacher must hold period 1 on all 4 running days.');
        $this->assertSame(4, $sectionBTeacherPeriod1Count, 'Section B class-teacher must hold period 1 on all 4 running days, independently of Section A.');
    }

    public function test_sectionless_class_still_completes_a_full_wizard_run(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classA']]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect(route('timetable.wizard.step1', [$data['classB']]));

        $this->actingAs($admin)->post(route('timetable.wizard.step1.store', [$data['classB']]), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect(route('timetable.wizard.step2'));

        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['classA']->id, $data['classB']->id],
            'style' => 'rotating',
        ]);
        $response->assertOk();
        $generation = TimetableGeneration::findOrFail($response->json('generation_id'));
        $this->assertSame(0, $generation->unplaced_count);

        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))
            ->assertRedirect(route('timetable.generation.review', $generation));

        $period1Ids = BellTiming::where('order_index', 1)->pluck('id')->all();
        $classATeacherPeriod1Count = TimetableSlot::published()
            ->where('school_class_id', $data['classA']->id)
            ->whereNull('section_id')
            ->where('teacher_id', $data['teacherA']->id)
            ->whereIn('bell_timing_id', $period1Ids)
            ->count();

        $this->assertSame(4, $classATeacherPeriod1Count);
    }
}
