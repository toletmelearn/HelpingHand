<?php

namespace Tests\Feature;

use App\Models\CBSEResult;
use App\Models\Exam;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Category A2: results/index.blade.php:110, results/entry/index.blade.php:116,
 * results/verification/index.blade.php:132, and results/create.blade.php:29
 * all called ->class->name directly on a Student -- 'class' is a real
 * string column, not a relationship, so ->name was being called on a
 * plain string (or null for a student with no legacy class string at
 * all), which Eloquent/PHP resolves to null rather than a fatal error.
 * Confirmed live: /admin/results rendered no Class column content at all.
 * Fixed by migrating all four to Student::display_class_name (Category A1).
 */
class ResultsClassColumnTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    /** A student whose legacy 'class' string is null entirely -- ->class->name on this would previously render nothing, silently, with no error. */
    private function makeStudentWithNoLegacyString(): Student
    {
        $class = SchoolClass::create(['name' => 'Class 7', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        return Student::create([
            'name' => 'A2 Student', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'school_class_id' => $class->id, 'class_id' => $class->id, 'class' => null,
        ]);
    }

    public function test_results_index_shows_the_class_column(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudentWithNoLegacyString();
        $subject = Subject::create(['name' => 'A2 Subject', 'code' => 'A2' . uniqid()]);
        $exam = Exam::create([
            'name' => 'A2 Exam', 'exam_type' => 'term', 'class_id' => $student->school_class_id,
            'class_name' => 'Class 7', 'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'active',
        ]);
        CBSEResult::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject_id' => $subject->id,
            'pt_marks' => 5, 'notebook_marks' => 2, 'sea_marks' => 2, 'exam_marks' => 20,
            'academic_year' => '2026-27', 'term' => 'Term 1',
        ]);

        $response = $this->actingAs($admin)->get(route('results.index'));

        $response->assertOk();
        $response->assertSee('Class 7');
    }

    public function test_results_create_shows_the_class_for_each_student_option(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudentWithNoLegacyString();

        $response = $this->actingAs($admin)->get(route('results.create'));

        $response->assertOk();
        $response->assertSee('Class 7');
    }

    /**
     * NEW finding, separate from Category A2, discovered while writing this
     * test: Route::resource('results', ResultController::class) (routes/web.php)
     * registers GET results/{result} (bound to CBSEResult, no numeric
     * constraint) BEFORE the results.entry.* and results.verification.*
     * route groups later in the same file. Laravel's router matches
     * routes in registration order, so a request to /results/entry or
     * /results/verification is caught by results/{result} first --
     * "entry"/"verification" get passed to implicit route-model binding
     * as a CBSEResult id, which always throws ModelNotFoundException
     * (surfaced to the client as a 404). Both the entire "Multi-Subject
     * Result Entry" and "Result Verification" features are consequently
     * unreachable via their own named routes for any admin, always -- not
     * a symptom of anything in this change. Confirmed directly:
     * ImplicitRouteBinding throws "No query results for model
     * [App\Models\CBSEResult] verification".
     *
     * Fixing this means reordering/constraining routes in routes/web.php,
     * a structural change with a broader blast radius than this item's
     * "fix a blank column" scope -- flagged for the user rather than
     * silently fixed here. The Category A2 code fix in both files'
     * ->display_class_name (already applied) is still correct and will
     * take effect the moment the routing is fixed; StudentDisplayClassNameTest
     * already proves the accessor itself is correct independent of these
     * two pages being reachable.
     */
    public function test_results_entry_and_verification_routes_are_currently_unreachable_pre_existing_bug(): void
    {
        $admin = $this->makeAdmin();

        $entryResponse = $this->actingAs($admin)->get(route('results.entry.index'));
        $verificationResponse = $this->actingAs($admin)->get(route('results.verification.index'));

        // Documents the CURRENT (broken) behavior -- not a target for this
        // fix. If this test starts failing because these routes suddenly
        // return 200, that's good news: the shadowing bug got fixed
        // elsewhere and this test (and the two feature pages it
        // documents) should be revisited.
        $entryResponse->assertNotFound();
        $verificationResponse->assertNotFound();
    }
}
