<?php

namespace Tests\Feature\Admin;

use App\Models\AdmitCardFormat;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Remediation Task 7: AdmitCardController::store(), ExamArrangementController
 * ::seatingIndex()/generateSeating() matched students via
 * Student::where('class', $exam->class_name) -- the legacy free-text
 * class string against the exam's free-text class name, two different
 * vocabularies that essentially never agreed (0/3 exams matched any
 * student, live-verified in the audit). Repointed to
 * Student::where('school_class_id', $exam->class_id), with exams.class_id
 * now populated via a proper school_classes dropdown instead of free text.
 */
class ExamClassIdRepointTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        // ExamArrangementController checks the legacy single-value
        // users.role column instead of the roles() relation -- both need
        // to say admin for full route coverage (same quirk documented in
        // PhotoEverywhereEndToEndTest).
        $user = User::factory()->create(['role' => 'admin']);
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(?int $schoolClassId, ?string $legacyClass): Student
    {
        return Student::create([
            'name' => 'Test Student ' . uniqid(),
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2012-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => (string) random_int(6000000000, 9999999999),
            'class' => $legacyClass,
            'school_class_id' => $schoolClassId,
        ]);
    }

    public function test_exam_matches_students_by_school_class_id_not_the_legacy_string(): void
    {
        $admin = $this->makeAdmin();
        $realClass = SchoolClass::create(['name' => 'Class 10', 'class_order' => 10, 'is_active' => true]);
        $otherClass = SchoolClass::create(['name' => 'Class 9', 'class_order' => 9, 'is_active' => true]);

        // Canonically enrolled -- school_class_id matches, legacy string
        // is stale/irrelevant ("X" instead of "Class 10").
        $canonicalStudent = $this->makeStudent($realClass->id, 'X');

        // Legacy string happens to equal the exam's class_name, but
        // school_class_id points elsewhere -- must NOT match.
        $wrongClassStudent = $this->makeStudent($otherClass->id, 'Class 10');

        $exam = Exam::create([
            'name' => 'Repoint Test Exam', 'exam_type' => 'term', 'class_id' => $realClass->id,
            'class_name' => $realClass->name, 'subject_id' => \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true])->id, 'subject' => 'Math',
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);
        $format = AdmitCardFormat::create(['name' => 'Standard', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.admit-cards.store'), [
            'exam_id' => $exam->id,
            'admit_card_format_id' => $format->id,
            'academic_session' => '2026-27',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('admit_cards', ['student_id' => $canonicalStudent->id, 'exam_id' => $exam->id]);
        $this->assertDatabaseMissing('admit_cards', ['student_id' => $wrongClassStudent->id, 'exam_id' => $exam->id]);
    }

    public function test_admit_card_store_shows_error_not_false_success_when_no_students_match(): void
    {
        $admin = $this->makeAdmin();
        $emptyClass = SchoolClass::create(['name' => 'Class 11', 'class_order' => 11, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'Empty Class Exam', 'exam_type' => 'term', 'class_id' => $emptyClass->id,
            'class_name' => $emptyClass->name, 'subject_id' => \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true])->id, 'subject' => 'Math',
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);
        $format = AdmitCardFormat::create(['name' => 'Standard', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.admit-cards.store'), [
            'exam_id' => $exam->id,
            'admit_card_format_id' => $format->id,
            'academic_session' => '2026-27',
        ]);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseMissing('admit_cards', ['exam_id' => $exam->id]);
    }

    public function test_seating_index_shows_error_when_no_students_match(): void
    {
        $admin = $this->makeAdmin();
        $emptyClass = SchoolClass::create(['name' => 'Class 12', 'class_order' => 12, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'Empty Seating Exam', 'exam_type' => 'term', 'class_id' => $emptyClass->id,
            'class_name' => $emptyClass->name, 'subject_id' => \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true])->id, 'subject' => 'Math',
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.exams.arrangements.seating', $exam->id));

        $response->assertRedirect(route('admin.exams.arrangements.index'));
        $response->assertSessionHasErrors('error');
    }

    public function test_generate_seating_shows_error_when_no_students_match(): void
    {
        $admin = $this->makeAdmin();
        $emptyClass = SchoolClass::create(['name' => 'Class 8', 'class_order' => 8, 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'Empty Generate Exam', 'exam_type' => 'term', 'class_id' => $emptyClass->id,
            'class_name' => $emptyClass->name, 'subject_id' => \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true])->id, 'subject' => 'Math',
            'exam_date' => today()->addDays(5), 'start_time' => '10:00', 'end_time' => '12:00',
            'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.exams.arrangements.seating.generate', $exam->id), [
            'room_number' => 'Room 1',
            'start_number' => 1,
        ]);

        $response->assertSessionHasErrors('error');
    }

    public function test_exam_create_requires_a_valid_class_pick(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'No Class Exam',
            'exam_type' => 'term',
            'subject' => 'Math',
            'exam_date' => today()->addDays(5)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026-27',
            'term' => 'Term 1',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('class_id');
        $this->assertDatabaseMissing('exams', ['name' => 'No Class Exam']);
    }

    public function test_exam_create_with_a_valid_class_id_derives_class_name_automatically(): void
    {
        $admin = $this->makeAdmin();
        $schoolClass = SchoolClass::create(['name' => 'Class 6', 'class_order' => 6, 'is_active' => true]);
        \App\Models\Subject::firstOrCreate(['name' => 'Math'], ['code' => 'Math', 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('admin.exams.store'), [
            'name' => 'Valid Class Exam',
            'exam_type' => 'term',
            'class_id' => $schoolClass->id,
            'subject' => 'Math',
            'exam_date' => today()->addDays(5)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026-27',
            'term' => 'Term 1',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.exams.index'));
        $this->assertDatabaseHas('exams', [
            'name' => 'Valid Class Exam',
            'class_id' => $schoolClass->id,
            'class_name' => 'Class 6',
        ]);
    }
}
