<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => ucfirst($roleName)]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Test Student',
            'father_name' => 'Test Father',
            'mother_name' => 'Test Mother',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'address' => 'Somewhere',
            'phone' => '9999999999',
            'class' => 'Class 8',
        ]);
    }

    private function makeCertificate(Student $student, string $status, User $creator): Certificate
    {
        return Certificate::create([
            'certificate_type' => 'bonafide',
            'serial_number' => 'CERT-2026-' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'recipient_id' => $student->id,
            'recipient_type' => Student::class,
            'content_data' => ['recipient.name' => $student->name],
            'status' => $status,
            'created_by' => $creator->id,
        ]);
    }

    public function test_unauthorized_role_gets_403_on_store(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $student = $this->makeStudent();

        $response = $this->actingAs($clerk)->post(route('admin.certificates.store'), [
            'certificate_type' => 'bonafide',
            'recipient_type' => 'App\\Models\\Student',
            'recipient_id' => $student->id,
            'content_data' => ['recipient.name' => $student->name],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('certificates', ['recipient_id' => $student->id]);
    }

    public function test_unauthorized_role_gets_403_on_update(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();
        $certificate = $this->makeCertificate($student, 'draft', $admin);

        $response = $this->actingAs($clerk)->put(route('admin.certificates.update', $certificate), [
            'content_data' => ['recipient.name' => 'Changed Name'],
        ]);

        $response->assertForbidden();
    }

    public function test_unauthorized_role_gets_403_on_destroy(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();
        $certificate = $this->makeCertificate($student, 'draft', $admin);

        $response = $this->actingAs($clerk)->delete(route('admin.certificates.destroy', $certificate));

        $response->assertForbidden();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
    }

    public function test_unauthorized_role_gets_403_on_approve(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();
        $certificate = $this->makeCertificate($student, 'draft', $admin);

        $response = $this->actingAs($clerk)->put(route('admin.certificates.approve', $certificate));

        $response->assertForbidden();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'draft']);
    }

    public function test_unauthorized_role_gets_403_on_publish(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();
        $certificate = $this->makeCertificate($student, 'generated', $admin);

        $response = $this->actingAs($clerk)->put(route('admin.certificates.publish', $certificate));

        $response->assertForbidden();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'generated']);
    }

    public function test_unauthorized_role_gets_403_on_revoke(): void
    {
        $clerk = $this->makeUserWithRole('clerk');
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();
        $certificate = $this->makeCertificate($student, 'published', $admin);

        $response = $this->actingAs($clerk)->put(route('admin.certificates.revoke', $certificate), [
            'reason' => 'Attempted by an unauthorized role',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'status' => 'published']);
    }

    public function test_admin_can_go_through_the_full_lifecycle(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $student = $this->makeStudent();

        $store = $this->actingAs($admin)->post(route('admin.certificates.store'), [
            'certificate_type' => 'bonafide',
            'recipient_type' => 'App\\Models\\Student',
            'recipient_id' => $student->id,
            'content_data' => ['recipient.name' => $student->name],
        ]);
        $certificate = Certificate::where('recipient_id', $student->id)->firstOrFail();
        $store->assertRedirect(route('admin.certificates.show', $certificate->id));

        $approve = $this->actingAs($admin)->put(route('admin.certificates.approve', $certificate));
        $approve->assertRedirect();
        $this->assertSame('generated', $certificate->fresh()->status);

        $publish = $this->actingAs($admin)->put(route('admin.certificates.publish', $certificate));
        $publish->assertRedirect();
        $this->assertSame('published', $certificate->fresh()->status);

        $revoke = $this->actingAs($admin)->put(route('admin.certificates.revoke', $certificate), [
            'reason' => 'Issued in error',
        ]);
        $revoke->assertRedirect();
        $this->assertSame('revoked', $certificate->fresh()->status);
    }

    public function test_user_with_manage_permission_can_create(): void
    {
        $user = $this->makeUserWithRole('clerk');
        $permission = Permission::firstOrCreate(['name' => 'manage-certificates']);
        $user->roles->first()->grantPermission($permission);
        $student = $this->makeStudent();

        $response = $this->actingAs($user)->post(route('admin.certificates.store'), [
            'certificate_type' => 'bonafide',
            'recipient_type' => 'App\\Models\\Student',
            'recipient_id' => $student->id,
            'content_data' => ['recipient.name' => $student->name],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('certificates', ['recipient_id' => $student->id]);
    }
}
