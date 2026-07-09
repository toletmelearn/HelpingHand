<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherFormFieldsTest extends TestCase
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

    private function baseFormPayload(): array
    {
        return [
            'name' => 'Anita Sharma',
            'email' => 'anita.sharma@school.test',
            'phone' => '9876543210',
            'aadhar_number' => '123456789012',
            'qualification' => 'B.Ed',
            'subject_specialization' => 'Mathematics',
            'designation' => 'Teacher',
            'date_of_joining' => '2024-06-01',
            'salary' => 45000,
            'status' => 'on_leave',
            'address' => 'School Campus',
            'experience_details' => '5 years teaching experience',
            'wing' => 'junior',
            'teacher_type' => 'TGT',
            'employment_type' => 'contract',
            'gender' => 'female',
        ];
    }

    /** @test */
    public function admin_can_create_a_teacher_with_the_new_hr_master_data_fields()
    {
        $payload = $this->baseFormPayload() + [
            'relative_name' => "Ramesh Sharma (Husband)",
            'permanent_address' => '45 Village Road, District',
            'emergency_contact' => '9998887770',
            'educational_qualification' => 'B.Ed in Mathematics',
            'classes_taught' => 'Class 6-A Maths, Class 7-B Maths',
            'no_of_periods' => 24,
            'class_section' => '7-B',
            'responsibilities' => 'Exam Coordinator',
            'pan_number' => 'ABCDE1234F',
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.teachers.store'), $payload)
            ->assertRedirect(route('admin.teachers.index'));

        $this->assertDatabaseHas('teachers', [
            'name' => 'Anita Sharma',
            'status' => 'on_leave',
            'relative_name' => 'Ramesh Sharma (Husband)',
            'permanent_address' => '45 Village Road, District',
            'emergency_contact' => '9998887770',
            'educational_qualification' => 'B.Ed in Mathematics',
            'classes_taught' => 'Class 6-A Maths, Class 7-B Maths',
            'no_of_periods' => 24,
            'class_section' => '7-B',
            'responsibilities' => 'Exam Coordinator',
            'pan_number' => 'ABCDE1234F',
        ]);
    }

    /** @test */
    public function admin_can_update_a_teacher_with_the_new_hr_master_data_fields()
    {
        $teacher = Teacher::create([
            'name' => 'Original Name',
            'email' => 'original@school.test',
            'phone' => '9998887771',
            'designation' => 'PGT',
        ]);

        $payload = $this->baseFormPayload();
        $payload['name'] = 'Updated Name';
        $payload['classes_taught'] = 'Class 9-A Science';
        $payload['no_of_periods'] = 20;
        $payload['responsibilities'] = 'Lab Coordinator';

        $this->actingAs($this->admin)
            ->put(route('admin.teachers.update', $teacher->id), $payload)
            ->assertRedirect(route('admin.teachers.index'));

        $teacher->refresh();
        $this->assertEquals('Updated Name', $teacher->name);
        $this->assertEquals('Class 9-A Science', $teacher->classes_taught);
        $this->assertEquals(20, $teacher->no_of_periods);
        $this->assertEquals('Lab Coordinator', $teacher->responsibilities);
    }

    /** @test */
    public function subjects_multi_select_is_saved_as_an_array()
    {
        $payload = $this->baseFormPayload() + [
            'subjects' => ['Mathematics', 'Physics'],
        ];

        $this->actingAs($this->admin)->post(route('admin.teachers.store'), $payload);

        $teacher = Teacher::where('name', 'Anita Sharma')->firstOrFail();
        $this->assertEquals(['Mathematics', 'Physics'], $teacher->subjects);
    }
}
