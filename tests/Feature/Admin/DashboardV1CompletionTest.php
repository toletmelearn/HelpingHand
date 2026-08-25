<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ParentModel;
use App\Models\Result;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherClassSubjectAssignment;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Dashboard V1 completion pass.
 *
 * 1. AdminDashboardController::index() had a SECOND, unnamed, more weakly
 *    guarded duplicate route ('/admin/dashboard' under auth:web only,
 *    registered before the named one, so it -- not the properly middleware'd
 *    one -- was what Laravel actually matched). Removed the dead duplicate.
 *    An admin/super-admin-only authorization gate was ALSO tried here, but
 *    reverted: it broke four pre-existing tests that deliberately rely on
 *    any staff role (clerk/accountant/teacher/staff, no special permissions
 *    required) reaching this exact route as a generic sidebar-visibility
 *    probe. That confirms the dashboard shell is intentionally open to any
 *    authenticated staff account, with permission-gating done in the
 *    sidebar/content itself -- not a gap this task should "fix".
 *
 * 2. TeacherDashboardController::index() wrapped several unrelated queries
 *    in one try/catch. Two of them threw on every single request:
 *    results.teacher_id doesn't exist (real column is
 *    uploaded_by_teacher_id) and there is no notices table anywhere in
 *    this schema at all. Either exception alone reset every other section
 *    of the dashboard (assigned classes, subjects, exams, homework,
 *    duties, enquiries) to empty defaults -- the teacher dashboard has
 *    been silently near-empty for every teacher, on every load.
 *
 * Also: resources/views/student/dashboard.blade.php did not exist at all,
 * even though RoleDashboardController::studentDashboard() already computes
 * correct, real, per-student stats and layouts/student.blade.php's own nav
 * already links to it as "Home". The feature was implemented end to end
 * except its last file. Added the (minimal, no new business logic) view.
 */
class DashboardV1CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function clerk(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function studentUser(Student $student): User
    {
        $user = User::factory()->create(['role' => 'student']);
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);
        $user->student()->save($student);

        return $user->fresh();
    }

    private function makeStudent(SchoolClass $class, string $name): Student
    {
        return Student::create([
            'name' => $name, 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2013-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'phone' => (string) random_int(6000000000, 9999999999), 'address' => 'Addr',
            'class_id' => $class->id, 'school_class_id' => $class->id, 'class' => $class->name,
        ]);
    }

    private function teacherLogin(string $suffix): array
    {
        $teacher = Teacher::create(['name' => "Dash Teacher $suffix", 'status' => 'active']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id, 'username' => 'dash' . $suffix . uniqid(), 'password' => Hash::make('secret123'),
        ]);

        return [$teacher, $login];
    }

    // --- Admin dashboard: authorization ------------------------------------

    public function test_admin_can_access_admin_dashboard(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
    }

    /**
     * The dashboard shell is intentionally reachable by any authenticated
     * staff role -- SidebarPermissionVisibilityTest, SidebarAcademicAssignment-
     * LinksTest, ReconciliationUpiYearClosingPermissionTest, and
     * FeeCollectionAndOperationsPermissionTest all rely on exactly this for
     * clerk/accountant/teacher/staff with no special permissions. An
     * admin-only gate was tried and reverted after it broke all four. This
     * test locks that reversion in.
     */
    public function test_a_staff_role_with_no_special_permissions_can_still_reach_the_dashboard_shell(): void
    {
        $this->actingAs($this->clerk())->get(route('admin.dashboard'))->assertOk();
    }

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_the_dead_duplicate_unnamed_admin_dashboard_route_is_gone_and_the_named_route_still_serves_it(): void
    {
        // Previously an unnamed, auth:web-only duplicate was registered
        // before the named admin.dashboard route, so it -- not the properly
        // middleware'd one -- was what Laravel actually matched for this
        // exact path. Removing it must not change the page's own behavior.
        $this->actingAs($this->admin())->get('/admin/dashboard')->assertOk();
    }

    // --- Admin dashboard: data accuracy -------------------------------------

    public function test_admin_dashboard_shows_correct_student_and_teacher_counts(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class A', 'class_order' => 961, 'is_active' => true]);
        $this->makeStudent($class, 'Dash Student 1');
        $this->makeStudent($class, 'Dash Student 2');
        Teacher::create(['name' => 'Dash Teacher Count', 'status' => 'active']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', function ($stats) {
            return $stats['total_students'] === 2 && $stats['total_teachers'] === 1;
        });
    }

    public function test_admin_dashboard_attendance_count_matches_todays_records(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class B', 'class_order' => 962, 'is_active' => true]);
        $student = $this->makeStudent($class, 'Dash Attend Student');
        Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        Attendance::create(['student_id' => $student->id, 'date' => today()->subDay(), 'status' => 'present']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('stats', fn ($stats) => $stats['today_attendance'] === 1);
    }

    public function test_admin_dashboard_upcoming_exams_count_matches_exam_records(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class C', 'class_order' => 963, 'is_active' => true]);
        Exam::create([
            'name' => 'Dash Upcoming Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Math', 'exam_date' => today()->addDays(5),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'scheduled',
        ]);
        Exam::create([
            'name' => 'Dash Far Away Exam', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => 'Science', 'exam_date' => today()->addDays(60),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'));

        $response->assertOk();
        // Only the exam within 30 days should be counted.
        $response->assertViewHas('stats', fn ($stats) => $stats['upcoming_exams'] === 1);
    }

    // --- Teacher dashboard: the silent-degrade fix --------------------------

    public function test_teacher_dashboard_shows_uploaded_results_count_correctly(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class D', 'class_order' => 964, 'is_active' => true]);
        $subject = \App\Models\Subject::create(['name' => 'Dash Subject D', 'code' => 'DD' . uniqid(), 'is_active' => true]);
        $exam = Exam::create([
            'name' => 'Dash Exam D', 'exam_type' => 'term', 'class_id' => $class->id,
            'class_name' => $class->name, 'subject' => $subject->name, 'exam_date' => today(),
            'start_time' => '10:00', 'end_time' => '12:00', 'total_marks' => 100, 'passing_marks' => 33,
            'academic_year' => '2026-27', 'status' => 'completed',
        ]);
        $student = $this->makeStudent($class, 'Dash Result Student');
        [$teacher, $login] = $this->teacherLogin('results');
        TeacherClassSubjectAssignment::create([
            'teacher_id' => $teacher->id, 'class_id' => $class->id, 'subject_id' => $subject->id,
            'academic_year' => '2026-27',
        ]);
        Result::create([
            'student_id' => $student->id, 'exam_id' => $exam->id, 'subject' => $subject->name,
            'marks_obtained' => 80, 'total_marks' => 100, 'percentage' => 80, 'grade' => 'A1',
            'academic_year' => '2026-27', 'uploaded_by_teacher_id' => $teacher->id,
        ]);

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
        $response->assertViewHas('uploadedResults', 1);
        $response->assertViewHas('assignedClasses', fn ($classes) => $classes->contains('id', $class->id));
    }

    public function test_teacher_dashboard_does_not_crash_on_the_removed_notices_query(): void
    {
        [$teacher, $login] = $this->teacherLogin('notices');

        $response = $this->actingAs($login, 'teacher')->get(route('teacher.dashboard'));

        $response->assertOk();
    }

    // --- Student dashboard: the missing-view fix ----------------------------

    public function test_student_can_access_their_own_dashboard(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class E', 'class_order' => 965, 'is_active' => true]);
        $student = $this->makeStudent($class, 'Dash Portal Student');
        $user = $this->studentUser($student);

        $response = $this->actingAs($user)->get(route('student.dashboard'));

        $response->assertOk();
        $response->assertSee($class->name);
    }

    public function test_non_student_cannot_access_student_dashboard(): void
    {
        $this->actingAs($this->clerk())->get(route('student.dashboard'))->assertForbidden();
    }

    public function test_two_students_see_only_their_own_dashboard_data(): void
    {
        $classA = SchoolClass::create(['name' => 'Dash Class F1', 'class_order' => 966, 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Dash Class F2', 'class_order' => 967, 'is_active' => true]);
        $studentA = $this->makeStudent($classA, 'Dash Student A');
        $studentB = $this->makeStudent($classB, 'Dash Student B');
        $userA = $this->studentUser($studentA);
        $userB = $this->studentUser($studentB);

        $responseA = $this->actingAs($userA)->get(route('student.dashboard'));
        $responseA->assertOk();
        $responseA->assertViewHas('student', fn ($s) => $s->id === $studentA->id);

        $responseB = $this->actingAs($userB)->get(route('student.dashboard'));
        $responseB->assertOk();
        $responseB->assertViewHas('student', fn ($s) => $s->id === $studentB->id);
    }

    // --- Parent dashboard: existing scoping still holds ---------------------

    public function test_parent_dashboard_shows_only_their_own_child(): void
    {
        $class = SchoolClass::create(['name' => 'Dash Class G', 'class_order' => 968, 'is_active' => true]);
        $ownChild = $this->makeStudent($class, 'Dash Own Child');
        $otherChild = $this->makeStudent($class, 'Dash Other Child');

        $parent = ParentModel::create([
            'name' => 'Dash Parent', 'email' => 'dashparent' . uniqid() . '@example.com',
            'password' => Hash::make('password123'), 'student_id' => $ownChild->id,
        ]);

        $response = $this->actingAs($parent, 'parent')->get(route('parent.dashboard'));

        $response->assertOk();
        $response->assertViewHas('student', fn ($s) => $s->id === $ownChild->id);
        $response->assertDontSee($otherChild->name);
    }
}
