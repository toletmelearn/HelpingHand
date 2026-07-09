<?php

namespace Tests\Feature\Admin;

use App\Models\ParentModel;
use App\Models\Role;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherLogin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountManagementSecurityTest extends TestCase
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

    /** @test */
    public function sync_accounts_generates_unique_non_default_passwords_with_reset_required()
    {
        $teacher = Teacher::create(['name' => 'No Login Teacher', 'email' => 'no.login@school.test', 'phone' => '9998880001', 'designation' => 'PGT']);
        $student = Student::create([
            'name' => 'No Parent Kid', 'father_name' => 'Father', 'mother_name' => 'Mother',
            'date_of_birth' => '2016-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhar_number' => '999988887777', 'phone' => '9998880002', 'address' => 'Somewhere',
        ]);
        // Student::saved() auto-creates a ParentModel; delete it so this student
        // counts as "without a parent login" for syncAccounts() to pick up.
        ParentModel::where('student_id', $student->id)->delete();

        $this->actingAs($this->admin)->post(route('admin.accounts.sync'));

        $teacherLogin = TeacherLogin::where('teacher_id', $teacher->id)->first();
        $this->assertNotNull($teacherLogin);
        $this->assertFalse(Hash::check('123456', $teacherLogin->password));
        $this->assertTrue($teacherLogin->force_password_change);

        $parent = ParentModel::where('student_id', $student->id)->first();
        $this->assertNotNull($parent);
        $this->assertFalse(Hash::check('123456', $parent->password));
        $this->assertTrue($parent->must_reset_password);
    }

    /** @test */
    public function teacher_with_forced_password_change_is_blocked_from_other_routes_until_reset()
    {
        $teacher = Teacher::create(['name' => 'Gate Test Teacher', 'email' => 'gate.test@school.test', 'phone' => '9998880003', 'designation' => 'PGT']);
        $login = TeacherLogin::create([
            'teacher_id' => $teacher->id,
            'username' => '9998880003',
            'password' => Hash::make('TempPass123'),
            'status' => 'active',
            'force_password_change' => true,
        ]);

        $this->actingAs($login, 'teacher');

        // Direct navigation to a protected route (not through the login redirect)
        // must still be blocked while force_password_change is set.
        $response = $this->get(route('teacher.dashboard'));
        $response->assertRedirect(route('teacher.password.change'));

        // Clear the flag (simulating a completed reset) and confirm access opens up.
        $login->update(['force_password_change' => false]);
        $this->get(route('teacher.dashboard'))->assertStatus(200);
    }

    /** @test */
    public function admin_password_reset_clears_must_reset_password_flag_for_parent()
    {
        $parent = ParentModel::create([
            'name' => 'Reset Test Parent',
            'email' => 'reset.test.parent@example.test',
            'phone' => '9998880004',
            'password' => Hash::make('OldPass123'),
            'must_reset_password' => true,
        ]);

        $this->actingAs($this->admin)->post(route('admin.accounts.change-password'), [
            'role_type' => 'parent',
            'account_id' => $parent->id,
            'password' => 'NewAdminSetPass1',
            'password_confirmation' => 'NewAdminSetPass1',
        ]);

        $parent->refresh();
        $this->assertTrue(Hash::check('NewAdminSetPass1', $parent->password));
        $this->assertFalse($parent->must_reset_password);
    }
}
