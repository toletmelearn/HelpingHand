<?php

namespace Tests\Feature\Admin;

use App\Models\FieldPermission;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Mirrors PhotoFieldPermissionSeeder exactly, so this test exercises
        // the real deployed permission set rather than the helper's
        // default-allow fallback for roles with no explicit row.
        foreach (['student' => 'photo', 'teacher' => 'profile_image'] as $modelType => $fieldName) {
            foreach (self::allowedRoleProvider() as [$role]) {
                FieldPermission::create([
                    'model_type' => $modelType, 'field_name' => $fieldName,
                    'role' => $role, 'permission_level' => 'editable', 'is_active' => true,
                ]);
            }
            foreach (['teacher', 'student', 'parent', 'guard'] as $role) {
                FieldPermission::create([
                    'model_type' => $modelType, 'field_name' => $fieldName,
                    'role' => $role, 'permission_level' => 'hidden', 'is_active' => true,
                ]);
            }
        }
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Test Student', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => '111122223333', 'phone' => '9998887770', 'address' => 'Somewhere',
        ]);
    }

    private function makeTeacher(): Teacher
    {
        return Teacher::create(['name' => 'Test Teacher', 'email' => 'test.teacher@school.test', 'phone' => '9998887771', 'designation' => 'PGT']);
    }

    /** @dataProvider allowedRoleProvider */
    public function test_allowed_role_can_upload_student_photo(string $role)
    {
        $user = $this->userWithRole($role);
        $student = $this->makeStudent();
        $file = UploadedFile::fake()->image('student.jpg');

        $response = $this->actingAs($user)->post(route('students.photo.update', $student->id), ['photo' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $student->refresh();
        $this->assertNotNull($student->photo);
        Storage::disk('public')->assertExists($student->photo);
    }

    /** @dataProvider allowedRoleProvider */
    public function test_allowed_role_can_upload_teacher_photo(string $role)
    {
        $user = $this->userWithRole($role);
        $teacher = $this->makeTeacher();
        $file = UploadedFile::fake()->image('teacher.jpg');

        $response = $this->actingAs($user)->post(route('teachers.photo.update', $teacher->id), ['photo' => $file]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $teacher->refresh();
        $this->assertNotNull($teacher->profile_image);
        Storage::disk('public')->assertExists($teacher->profile_image);
    }

    public static function allowedRoleProvider(): array
    {
        return [
            'admin' => ['admin'],
            'super-admin' => ['super-admin'],
            'clerk' => ['clerk'],
            'accountant' => ['accountant'],
            'receptionist' => ['receptionist'],
            'class-teacher' => ['class-teacher'],
        ];
    }

    public function test_disallowed_role_is_forbidden_from_uploading_student_photo()
    {
        $user = $this->userWithRole('parent');
        $student = $this->makeStudent();
        $file = UploadedFile::fake()->image('student.jpg');

        $response = $this->actingAs($user)->post(route('students.photo.update', $student->id), ['photo' => $file]);

        $response->assertForbidden();
        $this->assertNull($student->fresh()->photo);
    }

    public function test_disallowed_role_is_forbidden_from_uploading_teacher_photo()
    {
        $user = $this->userWithRole('parent');
        $teacher = $this->makeTeacher();
        $file = UploadedFile::fake()->image('teacher.jpg');

        $response = $this->actingAs($user)->post(route('teachers.photo.update', $teacher->id), ['photo' => $file]);

        $response->assertForbidden();
        $this->assertNull($teacher->fresh()->profile_image);
    }

    public function test_uploading_a_new_photo_deletes_the_previous_one()
    {
        $user = $this->userWithRole('admin');
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('students.photo.update', $student->id), [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ]);
        $firstPath = $student->fresh()->photo;
        Storage::disk('public')->assertExists($firstPath);

        $this->actingAs($user)->post(route('students.photo.update', $student->id), [
            'photo' => UploadedFile::fake()->image('second.jpg'),
        ]);
        $secondPath = $student->fresh()->photo;

        $this->assertNotEquals($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_oversized_file_is_rejected_by_validation()
    {
        $user = $this->userWithRole('admin');
        $student = $this->makeStudent();
        $file = UploadedFile::fake()->image('too-big.jpg')->size(9000); // 9MB > 8MB cap

        $response = $this->actingAs($user)->post(route('students.photo.update', $student->id), ['photo' => $file]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($student->fresh()->photo);
    }

    public function test_non_image_file_is_rejected_by_validation()
    {
        $user = $this->userWithRole('admin');
        $student = $this->makeStudent();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)->post(route('students.photo.update', $student->id), ['photo' => $file]);

        $response->assertSessionHasErrors('photo');
        $this->assertNull($student->fresh()->photo);
    }

    public function test_photo_url_accessor_falls_back_to_default_avatar()
    {
        $student = $this->makeStudent();
        $this->assertStringContainsString('default-avatar.png', $student->photo_url);

        $teacher = $this->makeTeacher();
        $this->assertStringContainsString('default-avatar.png', $teacher->photo_url);
    }

    public function test_upload_creates_an_activity_log_entry()
    {
        $user = $this->userWithRole('admin');
        $student = $this->makeStudent();

        $this->actingAs($user)->post(route('students.photo.update', $student->id), [
            'photo' => UploadedFile::fake()->image('student.jpg'),
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Student::class,
            'subject_id' => $student->id,
            'description' => 'Uploaded student photo',
        ]);
    }
}
