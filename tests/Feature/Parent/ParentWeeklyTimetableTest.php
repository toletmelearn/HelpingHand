<?php

namespace Tests\Feature\Parent;

use App\Models\BellTiming;
use App\Models\ParentModel;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Timetable pilot-completion pass (Phase 3): the weekly companion to
 * ParentTimetableTodayTest, same security pattern -- the parent guard's
 * own linked student (ParentModel::getStudentAttribute(), which already
 * respects session('active_student_id') for multi-child switching), never
 * a client-supplied id.
 */
class ParentWeeklyTimetableTest extends TestCase
{
    use RefreshDatabase;

    private function seedFamily(string $label, string $dayOfWeek = 'Monday'): array
    {
        $class = SchoolClass::create(['name' => "{$label} Class", 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject = Subject::create(['name' => "{$label} Subject", 'code' => strtoupper($label) . uniqid()]);
        $teacher = Teacher::create(['name' => "{$label} Teacher", 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => $dayOfWeek, 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $student = Student::create([
            'name' => "{$label} Kid", 'father_name' => 'F', 'mother_name' => 'M',
            'date_of_birth' => '2015-01-01', 'gender' => 'male', 'category' => 'General',
            'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-' . strtoupper($label) . '-' . uniqid(),
            'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class->id,
        ]);
        $slot = TimetableSlot::create([
            'school_class_id' => $class->id, 'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'room_number' => "{$label}-Room",
        ]);
        $parent = ParentModel::create([
            'name' => "{$label} Parent",
            'email' => strtolower($label) . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'student_id' => $student->id,
        ]);

        return compact('class', 'subject', 'teacher', 'timing', 'student', 'slot', 'parent');
    }

    public function test_parent_sees_own_childs_periods_across_the_week(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00'); // a Monday
        $data = $this->seedFamily('WeekAlpha', 'Monday');

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.weekly'));

        $response->assertOk();
        $response->assertSee('Monday');
        $response->assertSee('WeekAlpha Subject');
        $response->assertSee('WeekAlpha Teacher');
        $response->assertSee('WeekAlpha-Room');
    }

    public function test_parent_does_not_see_a_different_familys_child_weekly_periods(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $ownFamily = $this->seedFamily('WeekBeta', 'Monday');
        $otherFamily = $this->seedFamily('WeekGamma', 'Monday');

        $response = $this->actingAs($ownFamily['parent'], 'parent')->get(route('parent.timetable.weekly'));

        $response->assertOk();
        $response->assertSee('WeekBeta Subject');
        $response->assertDontSee('WeekGamma Subject');
        $response->assertDontSee('WeekGamma Teacher');
    }

    public function test_draft_slots_are_never_shown_on_the_weekly_view(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $data = $this->seedFamily('WeekDelta', 'Monday');
        $data['slot']->update(['status' => 'draft']);

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.weekly'));

        $response->assertOk();
        $response->assertDontSee('WeekDelta Subject');
        $response->assertSee('No published timetable is available');
    }

    public function test_substitution_is_shown_as_an_arrangement_on_the_weekly_view(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $data = $this->seedFamily('WeekEpsilon', 'Monday');
        $substitute = Teacher::create(['name' => 'WeekEpsilon Substitute', 'status' => 'active']);
        $section = Section::create(['name' => 'A']);

        TeacherSubstitution::create([
            'substitution_date' => '2026-08-03',
            'absent_teacher_id' => $data['teacher']->id,
            'substitute_teacher_id' => $substitute->id,
            'class_id' => $data['class']->id,
            'section_id' => $section->id,
            'subject_id' => $data['subject']->id,
            'bell_timing_id' => $data['timing']->id,
            'status' => 'assigned',
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.weekly'));

        $response->assertOk();
        $response->assertSee('WeekEpsilon Substitute');
        $response->assertSee('Arrangement');
    }

    public function test_guest_is_redirected_to_parent_login_for_weekly_view(): void
    {
        $response = $this->get(route('parent.timetable.weekly'));

        $response->assertRedirect();
    }

    public function test_today_view_links_to_the_weekly_view_and_back(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $data = $this->seedFamily('WeekZeta', 'Monday');

        $today = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.today'));
        $today->assertOk();
        $today->assertSee(route('parent.timetable.weekly'), false);

        $weekly = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.weekly'));
        $weekly->assertOk();
        $weekly->assertSee(route('parent.timetable.today'), false);
    }

    /**
     * Multi-child security: switching the active child (the EXISTING
     * ParentAuthController::switchStudent mechanism, validated there
     * against $parent->students()) must correctly change which child's
     * weekly timetable is shown -- and a parent must never be able to see
     * a sibling's timetable without a legitimate switch through it.
     */
    public function test_switching_child_changes_which_weekly_timetable_is_shown(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');

        $class1 = SchoolClass::create(['name' => 'Sibling Class 1', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $class2 = SchoolClass::create(['name' => 'Sibling Class 2', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $subject1 = Subject::create(['name' => 'Sibling Subject One', 'code' => 'SIB1' . uniqid()]);
        $subject2 = Subject::create(['name' => 'Sibling Subject Two', 'code' => 'SIB2' . uniqid()]);
        $teacher1 = Teacher::create(['name' => 'Sibling Teacher One', 'status' => 'active']);
        $teacher2 = Teacher::create(['name' => 'Sibling Teacher Two', 'status' => 'active']);
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00:00', 'end_time' => '08:45:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        $parent = ParentModel::create([
            'name' => 'Sibling Parent', 'email' => 'sibling' . uniqid() . '@example.com', 'password' => bcrypt('password123'),
        ]);

        $child1 = Student::create([
            'name' => 'Child One', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2015-01-01',
            'gender' => 'male', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-C1-' . uniqid(), 'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class1->id,
        ]);
        $child2 = Student::create([
            'name' => 'Child Two', 'father_name' => 'F', 'mother_name' => 'M', 'date_of_birth' => '2016-01-01',
            'gender' => 'female', 'category' => 'General', 'aadhaar_number' => (string) random_int(100000000000, 999999999999),
            'admission_no' => 'ADM-C2-' . uniqid(), 'phone' => '9' . random_int(100000000, 999999999), 'address' => 'Somewhere',
            'school_class_id' => $class2->id,
        ]);
        // parent_id isn't mass-assignable on Student -- set it directly,
        // matching the normalized hasMany(Student, 'parent_id') structure
        // $parent->students() (and switchStudent()'s own authorization
        // check) relies on.
        $child1->forceFill(['parent_id' => $parent->id])->save();
        $child2->forceFill(['parent_id' => $parent->id])->save();

        TimetableSlot::create(['school_class_id' => $class1->id, 'bell_timing_id' => $timing->id, 'subject_id' => $subject1->id, 'teacher_id' => $teacher1->id]);
        TimetableSlot::create(['school_class_id' => $class2->id, 'bell_timing_id' => $timing->id, 'subject_id' => $subject2->id, 'teacher_id' => $teacher2->id]);

        // Default (no explicit switch yet) resolves to child1 via the legacy student_id fallback... but student_id
        // was never set here (multi-child, normalized structure) -- so nothing is active until an explicit switch.
        $this->actingAs($parent, 'parent')->post(route('parent.switch-student', $child1->id));
        $response1 = $this->actingAs($parent, 'parent')->get(route('parent.timetable.weekly'));
        $response1->assertOk();
        $response1->assertSee('Sibling Subject One');
        $response1->assertDontSee('Sibling Subject Two');

        $this->actingAs($parent, 'parent')->post(route('parent.switch-student', $child2->id));
        $response2 = $this->actingAs($parent, 'parent')->get(route('parent.timetable.weekly'));
        $response2->assertOk();
        $response2->assertSee('Sibling Subject Two');
        $response2->assertDontSee('Sibling Subject One');
    }

    /**
     * Hardening pass item 1's own weekly counterpart: the sidebar link
     * that already exists on every parent page ("Timetable" ->
     * parent.timetable.today) must be reachable, and the weekly view
     * itself must render inside the same persistent layout/nav.
     */
    public function test_parent_layout_navigation_reaches_the_weekly_view(): void
    {
        \Carbon\Carbon::setTestNow('2026-08-03 08:00:00');
        $data = $this->seedFamily('WeekEta', 'Monday');

        $response = $this->actingAs($data['parent'], 'parent')->get(route('parent.timetable.weekly'));

        $response->assertOk();
        $response->assertSee(route('parent.timetable.today'), false);
    }
}
