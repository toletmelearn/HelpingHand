<?php

namespace Tests\Feature\Admin;

use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\DefaulterExamOverride;
use App\Models\DefaulterStage;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFeeLedger;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\User;
use App\Services\DefaulterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admit Card workflow: Class Teacher override for a fee-defaulted student.
 * The core architecture (AdmitCard state machine, DefaulterExamOverride,
 * ExamRestrictionService's sync-on-stage-change) already existed and was
 * confirmed working for Admin/Principal/Accountant this session's own
 * earlier audit -- this fixes/extends: (1) DefaulterExamOverride gained a
 * nullable exam_id so an override can be scoped to one specific exam
 * (unchanged blanket behavior when omitted); (2) Class Teacher
 * authorization, using the CANONICAL teacher_class_subject_assignments.
 * is_class_teacher data (not the dead class_teacher pivot
 * Admin\DefaulterController::scopedClassIds() still relies on -- that
 * method is untouched, a separate concern outside this task); (3) a real
 * bug where Admin\AdmitCardController::blockDefaulters() ignored any
 * active override and would silently re-revoke a card the moment anyone
 * ran that bulk action.
 */
class AdmitCardClassTeacherOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        // The permission-seeding migration only grants override-exam-restriction
        // to principal/accountant, not admin -- see DefaulterWorkflowTest's
        // admin_can_grant_and_revoke_the_exam_override_through_the_real_http_route.
        $permission = \App\Models\Permission::firstOrCreate(['name' => 'override-exam-restriction']);
        $role->grantPermission($permission->name);

        return $user;
    }

    /**
     * A real class-teacher-role User with a linked Teacher record holding
     * a genuine is_class_teacher assignment for the given class/section --
     * the canonical mechanism, distinct from (and not dependent on) the
     * legacy class_teacher pivot scopedClassIds() uses elsewhere.
     */
    private function classTeacherUser(SchoolClass $class, ?Section $section, Subject $subject): array
    {
        $role = Role::firstOrCreate(['name' => 'class-teacher'], ['display_name' => 'Class Teacher']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $teacher = Teacher::create(['name' => 'ACT Class Teacher', 'status' => 'active', 'user_id' => $user->id]);
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'section_id' => $section?->id,
            'subject_id' => $subject->id, 'academic_year' => '2026-2027', 'is_class_teacher' => true,
        ]);

        return [$user, $teacher];
    }

    private function fixtures(): array
    {
        $class = SchoolClass::create(['name' => 'ACT Class A', 'class_order' => 992001, 'is_active' => true]);
        $section = Section::create(['name' => 'ACT-A']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'ACT Subject', 'code' => 'ACT-' . uniqid(), 'is_active' => true]);

        $student = Student::create([
            'name' => 'ACT Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);

        $exam = Exam::create([
            'name' => 'ACT Exam', 'exam_type' => 'term', 'class_id' => $class->id, 'class_name' => $class->name,
            'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => '2026-11-01', 'start_time' => '09:00', 'end_time' => '11:00',
            'total_marks' => 100, 'passing_marks' => 33, 'academic_year' => '2026-2027', 'status' => 'scheduled',
        ]);

        $format = AdmitCardFormat::create(['name' => 'ACT Format', 'is_active' => true]);
        $admin = $this->admin();
        $admitCard = AdmitCard::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-2027', 'status' => 'published', 'published_at' => now(), 'published_by' => $admin->id,
        ]);

        return compact('class', 'section', 'subject', 'student', 'exam', 'admitCard', 'admin');
    }

    private function makeDefaulted(Student $student): void
    {
        StudentFeeLedger::create([
            'student_id' => $student->id, 'date' => '2026-07-01', 'description' => 'Tuition Fee charge',
            'reference_type' => 'fee_structure_item', 'reference_id' => 1, 'debit' => 1200.00, 'credit' => 0.00,
            'running_balance' => 1200.00, 'unpaid_amount' => 1200.00,
        ]);
        $service = new DefaulterService($this->createMock(\App\Services\NotificationService::class));
        $service->syncDefaulters();
        $service->overrideStage($student->id, 'Exam Restriction', null, User::factory()->create()->id);
    }

    // --- 1/2. Datesheet-created Exam works with Admit Card generation ------

    public function test_admit_card_generation_works_for_an_exam_created_from_a_published_datesheet(): void
    {
        $class = SchoolClass::create(['name' => 'ACT Class DS', 'class_order' => 992002, 'is_active' => true]);
        $section = Section::create(['name' => 'ACT-DS']);
        $this->bridgeSectionToClass($class, $section);
        $subject = Subject::create(['name' => 'ACT DS Subject', 'code' => 'ACT-' . uniqid(), 'is_active' => true]);
        $admin = $this->admin();
        $session = \App\Models\AcademicSession::create(['name' => '2026-2027', 'code' => 'ACT-' . uniqid(), 'is_current' => true, 'is_active' => true, 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);

        $datesheet = \App\Models\Datesheet::create([
            'name' => 'ACT Datesheet', 'exam_type' => 'Term 1', 'academic_session_id' => $session->id,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-15', 'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $datesheet->classes()->create(['school_class_id' => $class->id, 'section_id' => $section->id]);
        $datesheet->entries()->create([
            'school_class_id' => $class->id, 'section_id' => $section->id, 'subject_id' => $subject->id,
            'exam_date' => '2026-10-05', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->actingAs($admin)->post(route('admin.datesheets.submit', $datesheet));
        $this->actingAs($admin)->post(route('admin.datesheets.approve', $datesheet));
        $this->actingAs($admin)->post(route('admin.datesheets.publish', $datesheet));

        $exam = Exam::where('name', 'like', 'ACT Datesheet%')->firstOrFail();

        $student = Student::create([
            'name' => 'ACT DS Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'section_id' => $section->id,
        ]);

        $format = AdmitCardFormat::create(['name' => 'ACT DS Format', 'is_active' => true]);
        $admitCard = AdmitCard::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-2027', 'status' => 'draft',
        ]);

        $this->assertEmpty($admitCard->validateForGeneration());
    }

    // --- 5/6. Normally-eligible and fee-defaulted students -------------------

    public function test_normally_eligible_student_receives_an_admit_card(): void
    {
        $f = $this->fixtures();
        $this->assertEmpty($f['admitCard']->validateForGeneration());
        $this->assertSame('published', $f['admitCard']->status);
    }

    public function test_fee_defaulted_student_is_normally_blocked(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);

        $this->assertSame('revoked', $f['admitCard']->fresh()->status);
        $this->assertNotEmpty($f['admitCard']->fresh()->validateForGeneration());
    }

    // --- 7/8/9/10. Class Teacher override ------------------------------------

    public function test_class_teacher_can_override_a_student_in_their_own_class_section(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);
        $this->assertSame('revoked', $f['admitCard']->fresh()->status);

        [$ctUser, $ctTeacher] = $this->classTeacherUser($f['class'], $f['section'], $f['subject']);

        $response = $this->actingAs($ctUser)->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['reason' => 'Parent met in person, cleared to sit this exam', 'exam_id' => $f['exam']->id]
        );

        $response->assertSessionHas('success');
        // 10. Admit Card becomes available again.
        $this->assertSame('published', $f['admitCard']->fresh()->status);

        // 9. Recorded with student, exam, class teacher (granted_by), timestamp, reason.
        $this->assertDatabaseHas('defaulter_exam_overrides', [
            'student_id' => $f['student']->id, 'exam_id' => $f['exam']->id, 'granted_by' => $ctUser->id,
            'reason' => 'Parent met in person, cleared to sit this exam',
        ]);
        $override = DefaulterExamOverride::where('student_id', $f['student']->id)->firstOrFail();
        $this->assertNotNull($override->granted_at);

        // 8. Fee status itself is untouched.
        $this->assertSame('Exam Restriction', DefaulterStage::where('student_id', $f['student']->id)->value('stage'));
    }

    // 11. Cannot override a student outside their own class/section.
    public function test_class_teacher_cannot_override_a_student_outside_their_class(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);

        $otherClass = SchoolClass::create(['name' => 'ACT Class B', 'class_order' => 992003, 'is_active' => true]);
        $otherSection = Section::create(['name' => 'ACT-B']);
        $this->bridgeSectionToClass($otherClass, $otherSection);
        $otherSubject = Subject::create(['name' => 'ACT Subject B', 'code' => 'ACT-' . uniqid(), 'is_active' => true]);
        [$ctUser, $ctTeacher] = $this->classTeacherUser($otherClass, $otherSection, $otherSubject);

        $response = $this->actingAs($ctUser)->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['reason' => 'Trying to override outside my class', 'exam_id' => $f['exam']->id]
        );

        $response->assertForbidden();
        $this->assertSame('revoked', $f['admitCard']->fresh()->status);
        $this->assertDatabaseMissing('defaulter_exam_overrides', ['student_id' => $f['student']->id]);
    }

    // Class teacher cannot grant a blanket (all-exam) override -- must specify one.
    public function test_class_teacher_must_specify_an_exam_id(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);
        [$ctUser, $ctTeacher] = $this->classTeacherUser($f['class'], $f['section'], $f['subject']);

        $response = $this->actingAs($ctUser)->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['reason' => 'No exam specified']
        );

        $response->assertStatus(422);
        $this->assertSame('revoked', $f['admitCard']->fresh()->status);
    }

    // A class-teacher-scoped override only unblocks THAT exam, not others.
    public function test_class_teacher_override_only_unblocks_the_specified_exam(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);

        $otherExam = Exam::create([
            'name' => 'ACT Other Exam', 'exam_type' => 'term', 'class_id' => $f['class']->id, 'class_name' => $f['class']->name,
            'subject_id' => $f['subject']->id, 'subject' => $f['subject']->name, 'exam_date' => '2026-11-05', 'start_time' => '09:00', 'end_time' => '11:00',
            'total_marks' => 100, 'passing_marks' => 33, 'academic_year' => '2026-2027', 'status' => 'scheduled',
        ]);
        $format = AdmitCardFormat::where('id', $f['admitCard']->admit_card_format_id)->first();
        $otherAdmitCard = AdmitCard::create([
            'student_id' => $f['student']->id, 'exam_id' => $otherExam->id, 'admit_card_format_id' => $format->id,
            'academic_session' => '2026-2027', 'status' => 'published', 'published_at' => now(), 'published_by' => $f['admin']->id,
        ]);
        \App\Services\ExamRestrictionService::syncAdmitCardsForStudent($f['student']->id);
        $this->assertSame('revoked', $otherAdmitCard->fresh()->status);

        [$ctUser, $ctTeacher] = $this->classTeacherUser($f['class'], $f['section'], $f['subject']);
        $this->actingAs($ctUser)->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['reason' => 'Only for the first exam', 'exam_id' => $f['exam']->id]
        )->assertSessionHas('success');

        $this->assertSame('published', $f['admitCard']->fresh()->status);
        $this->assertSame('revoked', $otherAdmitCard->fresh()->status); // untouched
    }

    // --- Unauthorized users ---------------------------------------------------

    public function test_unrelated_teacher_cannot_override_anyone(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);

        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        Teacher::create(['name' => 'ACT Plain Teacher', 'status' => 'active', 'user_id' => $user->id]);

        $response = $this->actingAs($user)->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['exam_id' => $f['exam']->id]
        );

        // No communicate-defaulters/override-exam-restriction permission at all -> blocked by middleware.
        $response->assertForbidden();
    }

    public function test_guest_cannot_override(): void
    {
        $f = $this->fixtures();

        $this->post(route('admin.fees.defaulters.exam-override.grant', $f['student']->id), ['exam_id' => $f['exam']->id])
            ->assertRedirect(route('login'));
    }

    // --- blockDefaulters() respects an active override ------------------------

    public function test_block_defaulters_bulk_action_does_not_undo_an_active_override(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);
        \App\Services\ExamRestrictionService::grantOverride($f['student']->id, $f['admin']->id, 'Bulk-action regression check', $f['exam']->id);
        $this->assertSame('published', $f['admitCard']->fresh()->status);

        $this->actingAs($f['admin'])->post(route('admin.admit-cards.block-defaulters'))->assertRedirect();

        $this->assertSame('published', $f['admitCard']->fresh()->status, 'blockDefaulters() must not re-revoke a card an active override already covers.');
    }

    // --- Admin blanket override still works exactly as before -----------------

    public function test_admin_blanket_override_still_works_without_an_exam_id(): void
    {
        $f = $this->fixtures();
        $this->makeDefaulted($f['student']);

        $this->actingAs($f['admin'])->post(
            route('admin.fees.defaulters.exam-override.grant', $f['student']->id),
            ['reason' => 'Blanket admin override']
        )->assertSessionHas('success');

        $this->assertSame('published', $f['admitCard']->fresh()->status);
        $this->assertDatabaseHas('defaulter_exam_overrides', ['student_id' => $f['student']->id, 'exam_id' => null]);
    }

    // --- Parent visibility (12) ------------------------------------------------

    public function test_parent_sees_only_their_own_child_admit_card(): void
    {
        $f = $this->fixtures();
        $parent = \App\Models\ParentModel::create([
            'name' => 'ACT Parent', 'email' => 'actparent' . uniqid() . '@example.com',
            'phone' => (string) random_int(6000000000, 9999999999), 'password' => bcrypt('secret123'),
            'student_id' => $f['student']->id,
        ]);

        $this->actingAs($parent, 'parent')->get(route('parent.admit-cards.index'))
            ->assertOk()->assertSee($f['exam']->name);

        $this->actingAs($parent, 'parent')->get(route('parent.admit-cards.show', $f['admitCard']))->assertOk();
    }

    public function test_parent_cannot_view_a_different_students_admit_card(): void
    {
        $f = $this->fixtures();
        $otherStudent = Student::create([
            'name' => 'ACT Other Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $f['class']->id, 'school_class_id' => $f['class']->id,
        ]);
        $parent = \App\Models\ParentModel::create([
            'name' => 'ACT Parent 2', 'email' => 'actparent2' . uniqid() . '@example.com',
            'phone' => (string) random_int(6000000000, 9999999999), 'password' => bcrypt('secret123'),
            'student_id' => $otherStudent->id,
        ]);

        $this->actingAs($parent, 'parent')->get(route('parent.admit-cards.show', $f['admitCard']))->assertForbidden();
    }
}
