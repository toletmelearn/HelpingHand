<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AccountantDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $accountantUser;
    protected $student;
    protected $schoolClass;
    protected $section;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant'], ['display_name' => 'Accountant']);

        $this->adminUser = User::factory()->create(['role' => 'admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->accountantUser = User::factory()->create(['role' => 'accountant']);
        $this->accountantUser->roles()->attach($accountantRole->id);

        $this->schoolClass = SchoolClass::create([
            'name' => 'Class 10',
            'class_order' => 10
        ]);

        $this->section = Section::create([
            'name' => 'A',
            'class_id' => $this->schoolClass->id
        ]);

        $this->student = Student::create([
            'name' => 'Jane Doe',
            'admission_no' => 'ADM-2026-9999',
            'father_name' => 'Father',
            'mother_name' => 'Mother',
            'date_of_birth' => '2010-01-01',
            'aadhar_number' => '123456789012',
            'address' => 'Test Address',
            'phone' => '9876543210',
            'class_id' => $this->schoolClass->id,
            'section_id' => $this->section->id
        ]);
    }

    /** @test */
    public function guests_and_students_cannot_access_accountant_dashboard()
    {
        $response = $this->get(route('admin.fees.dashboard'));
        $response->assertRedirect('/login');

        $studentUser = User::factory()->create(['role' => 'student']);
        $response2 = $this->actingAs($studentUser)->get(route('admin.fees.dashboard'));
        $response2->assertStatus(403);
    }

    /** @test */
    public function accountant_can_access_accountant_dashboard_with_all_metrics()
    {
        $response = $this->actingAs($this->accountantUser)->get(route('admin.fees.dashboard'));
        $response->assertStatus(200);
        $response->assertSee('Accountant Dashboard');
        $response->assertSee("Today");
        $response->assertSee("Collection");
        $response->assertSee("Outstanding");
        $response->assertSee('paymentModeChart');
        $response->assertSee('feeHeadChart');
    }
}
