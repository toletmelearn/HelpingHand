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
 * T6 item 5: the guided "Set Up Timetable" wizard is a UI orchestration
 * layer only -- every assertion here checks that a wizard step wrote (or
 * reads) the SAME data the individual admin pages already use
 * (school_classes.teacher_id, teacher_class_subject_assignments,
 * FeasibilityService, the real generate/publish endpoints), not a
 * parallel data model.
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

        $this->actingAs($teacherUser)->get(route('timetable.wizard.step1'))->assertForbidden();
    }

    public function test_step1_shows_all_active_classes_with_a_teacher_dropdown(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step1'));

        $response->assertOk();
        $response->assertSee('Wizard Class A');
        $response->assertSee('Wizard Class B');
        $response->assertSee('Wizard Teacher A');
    }

    public function test_step3_shows_style_options_with_the_period_1_note(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step3'));

        $response->assertOk();
        $response->assertSee('Different Each Day');
        $response->assertSee('Same Every Day');
        $response->assertSee('Period 1 every day');
    }

    public function test_step1_saves_class_teachers_onto_school_classes(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->post(route('timetable.wizard.step1.store'), [
            'class_teachers' => [
                $data['classA']->id => $data['teacherA']->id,
                $data['classB']->id => $data['teacherB']->id,
            ],
        ]);

        $response->assertRedirect(route('timetable.wizard.step2', $data['classA']));
        $this->assertSame($data['teacherA']->id, $data['classA']->fresh()->teacher_id);
        $this->assertSame($data['teacherB']->id, $data['classB']->fresh()->teacher_id);
    }

    public function test_step2_prefills_the_class_teachers_row_from_step_1(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $data['classA']->update(['teacher_id' => $data['teacherA']->id]);

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step2', $data['classA']));

        $response->assertOk();
        $response->assertSee('Wizard Teacher A');
        $response->assertSee('already set to this class');
    }

    public function test_step2_store_writes_the_same_is_class_teacher_row_the_standalone_form_would(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();
        $data['classA']->update(['teacher_id' => $data['teacherA']->id]);

        $response = $this->actingAs($admin)->post(route('timetable.wizard.step2.store', $data['classA']), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ]);

        $response->assertRedirect(route('timetable.wizard.step2', $data['classB']));

        $assignment = TeacherClassSubjectAssignment::where('class_id', $data['classA']->id)->first();
        $this->assertNotNull($assignment);
        $this->assertSame($data['teacherA']->id, $assignment->teacher_id);
        $this->assertSame($data['subject']->id, $assignment->subject_id);
        $this->assertTrue($assignment->is_class_teacher);
        $this->assertSame(4, $assignment->periods_per_week);
        $this->assertSame(self::YEAR, $assignment->academic_year);
    }

    public function test_step2_store_redirects_to_step3_after_the_last_class(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        $response = $this->actingAs($admin)->post(route('timetable.wizard.step2.store', $data['classB']), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4],
            ],
        ]);

        $response->assertRedirect(route('timetable.wizard.step3'));
    }

    public function test_step4_shows_the_same_readiness_notes_feasibility_service_produces(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool(); // neither class has a class-teacher yet

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step4', ['style' => 'rotating']));

        $response->assertOk();
        $response->assertSee('Wizard Class A has no class teacher assigned.');
        $response->assertSee('Wizard Class B has no class teacher assigned.');
    }

    public function test_step4_link_to_generate_carries_the_chosen_style(): void
    {
        $admin = $this->makeAdmin();
        $this->seedSchool();

        $response = $this->actingAs($admin)->get(route('timetable.wizard.step4', ['style' => 'fixed_daily']));

        $response->assertOk();
        $response->assertSee('style=fixed_daily', false);
        $response->assertSee('select_all=1', false);
    }

    /**
     * The plan's own required test: a full wizard run on a seeded school
     * ends in a published timetable -- Steps 1-4 through the wizard's own
     * routes, Step 5 through the REAL (already-tested) generate/publish
     * endpoints, proving the wizard's writes are exactly what those
     * endpoints need to act on.
     */
    public function test_full_wizard_run_on_seeded_school_ends_in_a_published_timetable(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->seedSchool();

        // Step 1.
        $this->actingAs($admin)->post(route('timetable.wizard.step1.store'), [
            'class_teachers' => [
                $data['classA']->id => $data['teacherA']->id,
                $data['classB']->id => $data['teacherB']->id,
            ],
        ])->assertRedirect(route('timetable.wizard.step2', $data['classA']));

        // Step 2, class A -- their own class-teacher's subject.
        $this->actingAs($admin)->post(route('timetable.wizard.step2.store', $data['classA']), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherA']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect(route('timetable.wizard.step2', $data['classB']));

        // Step 2, class B -- last class, redirects to Step 3.
        $this->actingAs($admin)->post(route('timetable.wizard.step2.store', $data['classB']), [
            'rows' => [
                ['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacherB']->id, 'periods_per_week' => 4, 'is_class_teacher' => 1],
            ],
        ])->assertRedirect(route('timetable.wizard.step3'));

        // Step 3 -> Step 4 (plain navigation, no persistence).
        $this->actingAs($admin)->get(route('timetable.wizard.step3'))->assertOk();
        $this->actingAs($admin)->get(route('timetable.wizard.step4', ['style' => 'rotating']))->assertOk();

        // Step 5 -- the existing, already-tested generate/publish flow.
        $response = $this->actingAs($admin)->postJson(route('timetable.generate'), [
            'school_class_ids' => [$data['classA']->id, $data['classB']->id],
            'style' => 'rotating',
        ]);
        $response->assertOk();
        $generation = TimetableGeneration::findOrFail($response->json('generation_id'));
        $this->assertSame(TimetableGeneration::STATUS_COMPLETED, $generation->status);
        $this->assertSame(0, $generation->unplaced_count);

        $this->actingAs($admin)->post(route('timetable.generation.publish', $generation))
            ->assertRedirect(route('timetable.generation.review', $generation));

        // Both class-teachers hold period 1, every running day, in the LIVE (published) timetable.
        $period1Ids = BellTiming::where('order_index', 1)->pluck('id')->all();
        $classATeacherPeriod1Count = TimetableSlot::published()
            ->where('school_class_id', $data['classA']->id)
            ->where('teacher_id', $data['teacherA']->id)
            ->whereIn('bell_timing_id', $period1Ids)
            ->count();
        $classBTeacherPeriod1Count = TimetableSlot::published()
            ->where('school_class_id', $data['classB']->id)
            ->where('teacher_id', $data['teacherB']->id)
            ->whereIn('bell_timing_id', $period1Ids)
            ->count();

        $this->assertSame(4, $classATeacherPeriod1Count, 'Class A class-teacher must hold period 1 on all 4 running days.');
        $this->assertSame(4, $classBTeacherPeriod1Count, 'Class B class-teacher must hold period 1 on all 4 running days.');
    }
}
