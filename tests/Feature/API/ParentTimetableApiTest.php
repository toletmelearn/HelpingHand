<?php

namespace Tests\Feature\API;

use App\Models\BellTiming;
use App\Models\ParentModel;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * T5 item 2: the mobile/app "today's periods" API, token-gated the same
 * way as every other route in the protected v1 group (auth:sanctum +
 * ApiAccessControl -- see the new parentSelfRoutes()/isParentSelf() there).
 */
class ParentTimetableApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeParentUserAndRecord(\App\Models\Student $student): array
    {
        $user = User::factory()->create(['email' => 'apiparent' . $student->id . '@example.com']);
        $role = Role::firstOrCreate(['name' => 'parent'], ['display_name' => 'Parent']);
        $user->roles()->attach($role->id);

        $parentRecord = ParentModel::create([
            'name' => 'API Parent',
            'email' => $user->email,
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);

        return [$user, $parentRecord];
    }

    private function seedStudentWithTodaysSlot(): array
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $dayOfWeek = now()->format('l');

        $class = SchoolClass::create(['name' => 'API Class ' . uniqid(), 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'API Subject', 'code' => 'API' . uniqid()]);
        $teacher = Teacher::create(['name' => 'API Teacher', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $student = \App\Models\Student::create([
            'name' => 'API Kid', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-API-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class->id,
        ]);

        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);

        return compact('class', 'subject', 'teacher', 'timing', 'student', 'slot');
    }

    public function test_parent_can_see_own_childs_periods_today(): void
    {
        $data = $this->seedStudentWithTodaysSlot();
        [$user] = $this->makeParentUserAndRecord($data['student']);

        Sanctum::actingAs($user, ['mobile:user', 'mobile:parent']);

        $response = $this->getJson(route('api.v1.students.timetable-today', ['studentId' => $data['student']->id]));

        $response->assertOk();
        $response->assertJsonPath('data.periods.0.subject', 'API Subject');
        $response->assertJsonPath('data.periods.0.teacher', 'API Teacher');
        $response->assertJsonPath('data.periods.0.is_arrangement', false);
    }

    public function test_substitution_is_reflected_as_an_arrangement_in_the_api(): void
    {
        $data = $this->seedStudentWithTodaysSlot();
        [$user] = $this->makeParentUserAndRecord($data['student']);
        $substitute = Teacher::create(['name' => 'API Substitute Teacher', 'status' => 'active']);
        $section = \App\Models\Section::create(['name' => 'A']);

        TeacherSubstitution::create([
            'substitution_date' => now()->toDateString(),
            'absent_teacher_id' => $data['teacher']->id,
            'substitute_teacher_id' => $substitute->id,
            'class_id' => $data['class']->id,
            'section_id' => $section->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'created_by' => User::factory()->create()->id,
        ]);

        Sanctum::actingAs($user, ['mobile:user', 'mobile:parent']);

        $response = $this->getJson(route('api.v1.students.timetable-today', ['studentId' => $data['student']->id]));

        $response->assertOk();
        $response->assertJsonPath('data.periods.0.teacher', 'API Substitute Teacher');
        $response->assertJsonPath('data.periods.0.is_arrangement', true);
    }

    public function test_parent_cannot_see_a_different_familys_child(): void
    {
        $ownData = $this->seedStudentWithTodaysSlot();
        [$user] = $this->makeParentUserAndRecord($ownData['student']);

        $otherStudent = \App\Models\Student::create([
            'name' => 'Other Family Kid', 'father_name' => 'F2', 'mother_name' => 'M2',
            'date_of_birth' => '2015-01-01', 'gender' => 'female', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-API-OTHER-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Elsewhere',
        ]);

        Sanctum::actingAs($user, ['mobile:user', 'mobile:parent']);

        $response = $this->getJson(route('api.v1.students.timetable-today', ['studentId' => $otherStudent->id]));

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $data = $this->seedStudentWithTodaysSlot();

        $response = $this->getJson(route('api.v1.students.timetable-today', ['studentId' => $data['student']->id]));

        $response->assertUnauthorized();
    }

    public function test_non_parent_role_token_is_rejected(): void
    {
        $data = $this->seedStudentWithTodaysSlot();
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);

        Sanctum::actingAs($user, ['mobile:user', 'mobile:student']);

        $response = $this->getJson(route('api.v1.students.timetable-today', ['studentId' => $data['student']->id]));

        $response->assertForbidden();
    }
}
