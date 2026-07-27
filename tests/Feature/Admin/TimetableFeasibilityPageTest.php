<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableFeasibilityPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_page_loads_with_no_data(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('timetable.feasibility'));

        $response->assertOk();
        $response->assertSee('Timetable Feasibility Report');
        $response->assertSee('No academic session selected');
    }

    public function test_page_loads_and_shows_hand_countable_numbers(): void
    {
        $admin = $this->makeAdmin();

        $session = AcademicSession::create([
            'name' => '2026-2027', 'code' => '2026-2027',
            'start_date' => '2026-04-01', 'end_date' => '2027-03-31', 'is_current' => true,
        ]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'academic_year' => '2026-2027']);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P2', 'start_time' => '08:45', 'end_time' => '09:30', 'is_active' => true, 'is_break' => false, 'order_index' => 2, 'academic_year' => '2026-2027']);

        $class = SchoolClass::create(['name' => 'Feasibility Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Science', 'code' => 'SCI1']);
        $teacher = Teacher::create(['name' => 'Feasibility Teacher', 'status' => 'active']);
        $timing = BellTiming::where('period_name', 'P1')->first();

        TimetableSlot::create([
            'school_class_id' => $class->id, 'section_id' => null, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'academic_year' => '2026-2027',
        ]);

        $response = $this->actingAs($admin)->get(route('timetable.feasibility', ['academic_session_id' => $session->id]));

        $response->assertOk();
        // Hand-count: this class has 1 of 2 periods placed, 1 empty.
        $response->assertSee('Feasibility Class');
        $response->assertSee('1 empty period out of 2');
        // Hand-count: this teacher has 1 period placed.
        $response->assertSee('Feasibility Teacher');
        $response->assertSee('No conflicts found');
    }

    public function test_non_admin_non_teacher_cannot_view_the_report(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'parent'], ['display_name' => 'Parent']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->get(route('timetable.feasibility'));

        $response->assertForbidden();
    }
}
