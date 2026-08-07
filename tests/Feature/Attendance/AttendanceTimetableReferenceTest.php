<?php

namespace Tests\Feature\Attendance;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T5 item 3: the period-wise attendance marking screen shows a read-only
 * reference panel of today's PUBLISHED timetable for the class -- no
 * attendance-marking behaviour changes, purely additive.
 */
class AttendanceTimetableReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_todays_timetable_reference_panel_shows_for_the_class(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $admin = $this->makeAdmin();

        $class = SchoolClass::create(['name' => 'Ref Class', 'class_order' => 1, 'is_active' => true]);
        $subject = Subject::create(['name' => 'Reference Subject', 'code' => 'REF1']);
        $teacher = Teacher::create(['name' => 'Reference Teacher', 'status' => 'active']);
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $timing = BellTiming::where('period_name', 'P1')->first();
        TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        Student::create([
            'name' => 'Ref Kid', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-REF-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'class' => 'Ref Class', 'school_class_id' => $class->id, 'roll_number' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/attendance/create?class=' . urlencode('Ref Class'));

        $response->assertOk();
        $response->assertSee('Timetable (reference only)');
        $response->assertSee('Reference Subject');
        $response->assertSee('Reference Teacher');
    }

    public function test_panel_is_simply_absent_when_no_timetable_exists_for_the_class(): void
    {
        $admin = $this->makeAdmin();

        $class = SchoolClass::create(['name' => 'No Timetable Class', 'class_order' => 2, 'is_active' => true]);
        Student::create([
            'name' => 'No TT Kid', 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-NOTT-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'class' => 'No Timetable Class', 'school_class_id' => $class->id, 'roll_number' => 1,
        ]);

        $response = $this->actingAs($admin)->get('/attendance/create?class=' . urlencode('No Timetable Class'));

        $response->assertOk();
        $response->assertSee('Mark Daily Attendance');
        $response->assertDontSee('Timetable (reference only)');
    }
}
