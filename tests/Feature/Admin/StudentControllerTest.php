<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Role;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->admin->roles()->attach($adminRole->id);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_can_display_students_index()
    {
        $response = $this->get(route('admin.students.index'));
        
        $response->assertStatus(200);
        $response->assertViewIs('admin.students.index');
    }

    /** @test */
    public function it_can_create_new_student()
    {
        $schoolClass = SchoolClass::create([
            'name' => 'Class 1',
            'class_order' => 1,
            'is_active' => true,
        ]);

        $studentData = [
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'father_name' => 'Father Name',
            'mother_name' => 'Mother Name',
            'date_of_birth' => '2010-01-01',
            'aadhaar_number' => '123456789012',
            'address' => 'Test Address',
            'mobile' => '1234567890',
            'gender' => 'male',
            'category' => 'General',
            'class_id' => $schoolClass->id,
            'roll_number' => 1,
            'admission_date' => now()->format('Y-m-d'),
        ];

        $response = $this->post(route('admin.students.store'), $studentData);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('students', ['name' => 'Test Student']);
    }

    /** @test */
    public function it_validates_required_fields_for_student()
    {
        $response = $this->post(route('admin.students.store'), []);
        
        $response->assertSessionHasErrors(['name', 'father_name']);
    }
}
