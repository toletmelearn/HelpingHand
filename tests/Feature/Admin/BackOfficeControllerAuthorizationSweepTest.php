<?php

namespace Tests\Feature\Admin;

use App\Models\Exam;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentStatus;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up to the priority-audit authorization sweep (see
 * BackOfficeControllerAuthorizationTest for the first 6 controllers fixed).
 * These ~19 more Admin\* controllers had the identical gap: their route
 * group requires only 'auth', and none of the controllers called
 * $this->authorize(), checked a role, or sat behind a 'role:'/'permission:'
 * route-group middleware -- unlike every sibling Admin\* controller. Any
 * authenticated account (teacher, parent, accountant, clerk) could reach
 * them. Fixed by adding the same 'role:admin' middleware used everywhere
 * else in this codebase for controllers with no per-record ownership
 * concept.
 *
 * Note: VisitorManagementController, CallRegisterController,
 * CourierController, LostFoundController, and GuardDutyController were
 * investigated as part of the same sweep and found to already be protected
 * by a 'role:admin,super-admin,receptionist' route-group middleware
 * (routes/web.php, the "Front Office (Strictly Receptionist/Admin)" group)
 * -- they are intentionally NOT covered here to avoid narrowing that access
 * to admin-only, which would have been a regression for the receptionist
 * role.
 */
class BackOfficeControllerAuthorizationSweepTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeClerk(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'clerk'], ['display_name' => 'Clerk']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public static function simpleIndexRoutes(): array
    {
        return [
            'PermissionController' => ['admin.permissions.index'],
            'GradingSystemController' => ['admin.grading-systems.index'],
            'StudentStatusController' => ['admin.student-statuses.index'],
            'ExaminationPatternController' => ['admin.examination-patterns.index'],
            'ResultFormatController' => ['admin.result-formats.index'],
            'DocumentFormatController' => ['admin.document-formats.index'],
            'LanguageSettingController' => ['admin.language-settings.index'],
            'AssetController' => ['admin.assets.index'],
            'CertificateTemplateController' => ['admin.certificate-templates.index'],
            'DailyTeachingWorkController' => ['admin.daily-teaching-work.index'],
            'IdCardController' => ['admin.id-cards.index'],
            'SyllabusController' => ['admin.syllabi.index'],
            'LanguageController' => ['admin.languages.index'],
            'NotificationSettingController' => ['admin.notification-settings.index'],
            'BookController' => ['books.index'],
            'BookIssueController' => ['book-issues.index'],
            'ProfessionalHomeworkController' => ['admin.admin.professional-homework.index'],
            'ProfessionalLessonPlanController' => ['admin.admin.professional-lesson-plans.index'],
            'AdvancedReportController' => ['admin.advanced-reports.index'],
        ];
    }

    /** @dataProvider simpleIndexRoutes */
    public function test_non_admin_cannot_view(string $routeName): void
    {
        $response = $this->actingAs($this->makeClerk())->get(route($routeName));
        $response->assertForbidden();
    }

    /** @dataProvider simpleIndexRoutes */
    public function test_guest_cannot_view(string $routeName): void
    {
        $response = $this->get(route($routeName));
        $response->assertRedirect(route('login'));
    }

    public static function simpleIndexRoutesWithWorkingView(): array
    {
        // Same set as simpleIndexRoutes(), minus the two doubly-named professional-* routes:
        // their views (resources/views/admin/lesson-plans/professional-index.blade.php and
        // resources/views/admin/homework/professional-index.blade.php) call
        // route('admin.professional-lesson-plans.index') / route('admin.professional-homework.index')
        // internally, which do not exist -- the actual registered names are doubly-prefixed
        // 'admin.admin.professional-*' (routes/web.php ->names() call already includes 'admin.'
        // inside an 'admin.'-prefixed group). Pre-existing bug, unrelated to authorization;
        // out of scope for this fix.
        $routes = self::simpleIndexRoutes();
        unset($routes['ProfessionalLessonPlanController']);
        unset($routes['ProfessionalHomeworkController']);

        return $routes;
    }

    /** @dataProvider simpleIndexRoutesWithWorkingView */
    public function test_admin_can_view(string $routeName): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route($routeName));
        $response->assertOk();
    }

    // --- ExamBlueprintController (needs a real {examId}) -----------------------

    private function makeExam(): Exam
    {
        $class = SchoolClass::firstOrCreate(['name' => '5'], ['class_order' => 5, 'is_active' => true]);
        $subject = Subject::firstOrCreate(['name' => 'Maths'], ['code' => 'Maths', 'is_active' => true]);

        return Exam::create([
            'name' => 'Half Yearly', 'exam_type' => 'half_yearly',
            'class_id' => $class->id, 'class_name' => $class->name,
            'subject_id' => $subject->id, 'subject' => $subject->name, 'exam_date' => now()->addDays(10),
            'start_time' => '09:00', 'end_time' => '11:00', 'academic_year' => '2026-2027',
            'total_marks' => 100, 'passing_marks' => 33, 'status' => 'scheduled',
        ]);
    }

    public function test_non_admin_cannot_view_exam_blueprints(): void
    {
        $exam = $this->makeExam();

        $response = $this->actingAs($this->makeClerk())
            ->get(route('admin.exams.blueprints.index', $exam->id));
        $response->assertForbidden();
    }

    public function test_guest_cannot_view_exam_blueprints(): void
    {
        $exam = $this->makeExam();

        $response = $this->get(route('admin.exams.blueprints.index', $exam->id));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_view_exam_blueprints(): void
    {
        $exam = $this->makeExam();

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('admin.exams.blueprints.index', $exam->id));
        $response->assertOk();
    }

    // --- Destructive-action spot checks -----------------------------------------

    public function test_non_admin_cannot_delete_a_permission(): void
    {
        $permission = Permission::create(['name' => 'test-permission', 'guard_name' => 'web']);

        $response = $this->actingAs($this->makeClerk())->delete(route('admin.permissions.destroy', $permission));
        $response->assertForbidden();
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_non_admin_cannot_delete_a_student_status(): void
    {
        $student = Student::create([
            'name' => 'Status Test Student', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2012-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => '999911112222', 'phone' => '9000011122', 'address' => 'Addr',
        ]);
        $status = StudentStatus::create(['student_id' => $student->id, 'status' => 'active', 'status_date' => now()]);

        $response = $this->actingAs($this->makeClerk())->delete(route('admin.student-statuses.destroy', $status));
        $response->assertForbidden();
        $this->assertDatabaseHas('student_statuses', ['id' => $status->id]);
    }
}
