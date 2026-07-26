<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentPromotionLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPromotionAuthorizationAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSchoolClass(string $name, int $order): SchoolClass
    {
        return SchoolClass::create(['name' => $name, 'class_order' => $order, 'is_active' => true]);
    }

    private function makeStudent(SchoolClass $class): Student
    {
        return Student::create([
            'name' => 'Test Student ' . uniqid(),
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2012-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
            'class' => $class->name,
            'class_id' => $class->id,
            'school_class_id' => $class->id,
        ]);
    }

    private function makeSession(): AcademicSession
    {
        return AcademicSession::create([
            'name' => '2026-2027',
            'code' => '2026-2027',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'is_current' => true,
        ]);
    }

    public function test_unauthorized_role_gets_403_when_promoting(): void
    {
        $teacher = $this->makeUserWithRole('teacher');
        $source = $this->makeSchoolClass('Class 5', 5);
        $destination = $this->makeSchoolClass('Class 6', 6);
        $student = $this->makeStudent($source);
        $session = $this->makeSession();

        $response = $this->actingAs($teacher)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $source->id,
            'to_class' => $destination->id,
            'students' => [$student->id],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('student_promotion_logs', ['student_id' => $student->id]);
    }

    public function test_double_submit_of_the_same_batch_promotes_zero_students_the_second_time(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $source = $this->makeSchoolClass('Class 5', 5);
        $destination = $this->makeSchoolClass('Class 6', 6);
        $student = $this->makeStudent($source);
        $session = $this->makeSession();

        $payload = [
            'academic_session_id' => $session->id,
            'from_class' => $source->id,
            'to_class' => $destination->id,
            'students' => [$student->id],
        ];

        $first = $this->actingAs($admin)->post(route('admin.student-promotions.store'), $payload);
        $first->assertSessionHas('success');
        $this->assertSame(1, StudentPromotionLog::where('student_id', $student->id)->count());

        $second = $this->actingAs($admin)->post(route('admin.student-promotions.store'), $payload);
        $second->assertSessionHas('success');
        $second->assertSessionHas('success', function ($message) {
            return str_contains($message, '0 students promoted') && str_contains($message, 'already promoted');
        });

        // Still exactly one log row -- the resubmission did not duplicate it.
        $this->assertSame(1, StudentPromotionLog::where('student_id', $student->id)->count());
    }

    public function test_partial_batch_with_some_already_promoted_handles_correctly(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $source = $this->makeSchoolClass('Class 5', 5);
        $destination = $this->makeSchoolClass('Class 6', 6);
        $alreadyPromoted = $this->makeStudent($source);
        $fresh = $this->makeStudent($source);
        $session = $this->makeSession();

        // Pre-promote one student to the destination class in this session.
        $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $source->id,
            'to_class' => $destination->id,
            'students' => [$alreadyPromoted->id],
        ]);

        // Now submit a batch containing BOTH the already-promoted student and a fresh one.
        $response = $this->actingAs($admin)->post(route('admin.student-promotions.store'), [
            'academic_session_id' => $session->id,
            'from_class' => $source->id,
            'to_class' => $destination->id,
            'students' => [$alreadyPromoted->id, $fresh->id],
        ]);

        $response->assertSessionHas('success', function ($message) {
            return str_contains($message, '1 students promoted') && str_contains($message, 'already promoted');
        });

        $this->assertSame(1, StudentPromotionLog::where('student_id', $alreadyPromoted->id)->count());
        $this->assertSame(1, StudentPromotionLog::where('student_id', $fresh->id)->count());
        $this->assertSame($destination->id, $fresh->fresh()->school_class_id);
    }

    public function test_db_unique_index_rejects_a_duplicate_promotion_log_row(): void
    {
        // Backstop only: the controller's own skip logic (proven above) means
        // this constraint should never actually fire during normal use --
        // this proves it's still there and armed if something ever bypasses
        // the app-level guard (e.g. a direct DB write).
        $source = $this->makeSchoolClass('Class 5', 5);
        $destination = $this->makeSchoolClass('Class 6', 6);
        $student = $this->makeStudent($source);
        $session = $this->makeSession();

        StudentPromotionLog::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'from_class' => $source->name,
            'to_class' => $destination->name,
            'promoted_by' => null,
            'promoted_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        StudentPromotionLog::create([
            'student_id' => $student->id,
            'academic_session_id' => $session->id,
            'from_class' => $source->name,
            'to_class' => $destination->name,
            'promoted_by' => null,
            'promoted_at' => now(),
        ]);
    }
}
