<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Regression test for the Exam <-> SchoolClass/Student relation.
 *
 * ExamController::create()/edit() used to source their class dropdown from
 * ClassManagement, a legacy table that has diverged from SchoolClass (e.g.
 * three indistinguishable rows all named "Class 11" instead of the
 * stream-split "Class 11 Science/Commerce/Arts" that SchoolClass has).
 * Exam::schoolClass() resolves by matching the string class_name column
 * against SchoolClass.name, so any exam created against a ClassManagement
 * name that doesn't exist verbatim in SchoolClass silently orphans the exam
 * from real student data. This test locks in the fix: the class dropdown
 * must be sourced from SchoolClass, and a created exam's class_name must
 * resolve back to a real SchoolClass with real students in it.
 */
class ExamSchoolClassRelationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
    }

    public function test_exam_create_form_offers_stream_specific_school_classes(): void
    {
        SchoolClass::create(['name' => 'Class 11 Science', 'class_order' => 11]);
        SchoolClass::create(['name' => 'Class 11 Commerce', 'class_order' => 12]);

        $response = $this->actingAs($this->admin)->get(route('admin.exams.create'));

        $response->assertStatus(200);
        $response->assertSee('Class 11 Science', false);
        $response->assertSee('Class 11 Commerce', false);
    }

    public function test_created_exam_resolves_to_the_correct_school_class_and_its_students(): void
    {
        $scienceClass = SchoolClass::create(['name' => 'Class 11 Science', 'class_order' => 11]);
        SchoolClass::create(['name' => 'Class 11 Commerce', 'class_order' => 12]);
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHY11']);

        $student = Student::create([
            'name' => 'Stream Student',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2009-01-01',
            'gender' => 'male',
            'category' => 'General',
            'aadhar_number' => '999988887777',
            'phone' => '9111122223',
            'address' => 'Somewhere',
            'class_id' => $scienceClass->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'name' => 'Mid Term Physics',
            'exam_type' => 'midterm',
            'class_name' => 'Class 11 Science',
            'subject' => $subject->name,
            'exam_date' => now()->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026-2027',
            'term' => 'Term 1',
            'status' => 'scheduled',
        ]);

        $response->assertRedirect(route('admin.exams.index'));

        $exam = Exam::where('name', 'Mid Term Physics')->firstOrFail();

        // The relation must resolve to the exact stream, not just any
        // class sharing a bare "Class 11" name.
        $this->assertNotNull($exam->schoolClass);
        $this->assertEquals($scienceClass->id, $exam->schoolClass->id);

        // And that resolved class must actually contain the student --
        // proving the Exam -> SchoolClass -> Student chain is intact.
        $this->assertTrue(
            Student::where('class_id', $exam->schoolClass->id)->where('id', $student->id)->exists()
        );
    }

    public function test_exam_class_name_must_match_a_real_school_class(): void
    {
        Subject::create(['name' => 'Physics', 'code' => 'PHY11']);

        $response = $this->actingAs($this->admin)->post(route('admin.exams.store'), [
            'name' => 'Bad Class Exam',
            'exam_type' => 'midterm',
            'class_name' => 'Class 11', // bare name -- not a real SchoolClass row
            'subject' => 'Physics',
            'exam_date' => now()->addDays(7)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '11:00',
            'total_marks' => 100,
            'passing_marks' => 33,
            'academic_year' => '2026-2027',
            'term' => 'Term 1',
            'status' => 'scheduled',
        ]);

        $response->assertSessionHasErrors('class_name');
        $this->assertNull(Exam::where('name', 'Bad Class Exam')->first());
    }
}
