<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Hostel;
use App\Models\HostelRoom;
use App\Models\Student;
use App\Models\StudentHostelAllocation;
use Illuminate\Foundation\Testing\RefreshDatabase;

class HostelAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Hostel $hostel;
    protected HostelRoom $room;
    protected Student $studentMale;
    protected Student $studentFemale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $adminRole = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->hostel = Hostel::create([
            'name' => 'Newton Boys Dorm',
            'type' => 'boys',
            'capacity' => 10,
        ]);

        $this->room = HostelRoom::create([
            'hostel_id' => $this->hostel->id,
            'room_no' => 'Room 201',
            'capacity' => 2,
            'cost_per_bed' => 150.00,
        ]);

        $this->studentMale = Student::create([
            'name' => 'Robert Boyles',
            'father_name' => 'John Boyles',
            'mother_name' => 'Mary Boyles',
            'date_of_birth' => '2016-05-15',
            'aadhaar_number' => '111122223333',
            'address' => '123 Boyles Road',
            'phone' => '9876543211',
            'gender' => 'male',
            'admission_no' => 'ADM-5501',
        ]);

        $this->studentFemale = Student::create([
            'name' => 'Marie Curie',
            'father_name' => 'Pierre Curie',
            'mother_name' => 'Anne Curie',
            'date_of_birth' => '2016-05-16',
            'aadhaar_number' => '111122223334',
            'address' => '123 Curie Lane',
            'phone' => '9876543212',
            'gender' => 'female',
            'admission_no' => 'ADM-5502',
        ]);
    }

    public function test_hostel_dashboard_loads()
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('hostel.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Hostel');
        $response->assertSee('Newton Boys Dorm');
    }

    public function test_can_allocate_hostel_bed_to_matching_gender()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('hostel.allocate'), [
                'student_id' => $this->studentMale->id,
                'room_id' => $this->room->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_hostel_allocations', [
            'student_id' => $this->studentMale->id,
            'room_id' => $this->room->id,
            'status' => 'active',
        ]);
    }

    public function test_cannot_allocate_gender_mismatched_student()
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('hostel.allocate'), [
                'student_id' => $this->studentFemale->id, // Female into Boys Dorm
                'room_id' => $this->room->id,
            ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('student_hostel_allocations', [
            'student_id' => $this->studentFemale->id,
        ]);
    }

    public function test_cannot_allocate_exceeding_capacity()
    {
        // Occupying 2 beds (room capacity is 2)
        $studentOther = Student::create([
            'name' => 'Albert Einstein',
            'father_name' => 'Hermann Einstein',
            'mother_name' => 'Pauline Einstein',
            'date_of_birth' => '2016-05-17',
            'aadhaar_number' => '111122223335',
            'address' => '123 Relativity Way',
            'phone' => '9876543213',
            'gender' => 'male',
            'admission_no' => 'ADM-5503',
        ]);

        StudentHostelAllocation::create([
            'student_id' => $this->studentMale->id,
            'room_id' => $this->room->id,
            'status' => 'active',
        ]);

        StudentHostelAllocation::create([
            'student_id' => $studentOther->id,
            'room_id' => $this->room->id,
            'status' => 'active',
        ]);

        // Attempt third allocation
        $studentExtra = Student::create([
            'name' => 'Richard Feynman',
            'father_name' => 'Melville Feynman',
            'mother_name' => 'Lucille Feynman',
            'date_of_birth' => '2016-05-18',
            'aadhaar_number' => '111122223336',
            'address' => '123 Quantum Drive',
            'phone' => '9876543214',
            'gender' => 'male',
            'admission_no' => 'ADM-5504',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('hostel.allocate'), [
                'student_id' => $studentExtra->id,
                'room_id' => $this->room->id,
            ]);

        $response->assertSessionHas('error');
    }

    public function test_can_vacate_allocated_bed()
    {
        $alloc = StudentHostelAllocation::create([
            'student_id' => $this->studentMale->id,
            'room_id' => $this->room->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->post(route('hostel.vacate', $alloc->id));

        $response->assertRedirect();
        $this->assertEquals('vacated', $alloc->fresh()->status);
    }
}
