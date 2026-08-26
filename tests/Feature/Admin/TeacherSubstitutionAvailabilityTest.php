<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use App\Models\TeacherSubstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT Test 21 defect fix: SubstituteFinderService (the AUTOMATIC
 * suggestion engine used after store()) already excludes teachers with a
 * TeacherAvailability(is_available=false) row for the relevant bell
 * timing. But every path that accepts a MANUALLY-chosen
 * substitute_teacher_id directly from the request --
 * TeacherSubstitutionController::update(), ::assignSubstitute(), and
 * ::assignFromSlot() (the "Absent Today" one-click create+assign flow,
 * functionally the real "manual store" path since the plain store()
 * route never accepts substitute_teacher_id itself) -- only validated
 * that the teacher exists, never that they were actually available.
 */
class TeacherSubstitutionAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function fixtures(): array
    {
        $bellTiming = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);
        $class = SchoolClass::create(['name' => 'Availability Class', 'class_order' => 970301, 'is_active' => true]);
        $section = Section::create(['name' => 'A', 'class_id' => $class->id]);
        $subject = Subject::create(['name' => 'Availability Subject', 'code' => 'AVL-' . uniqid(), 'is_active' => true]);
        $absentTeacher = Teacher::create(['name' => 'Absent Teacher', 'status' => 'active']);
        $availableTeacher = Teacher::create(['name' => 'Available Teacher', 'status' => 'active']);
        $unavailableTeacher = Teacher::create(['name' => 'Unavailable Teacher', 'status' => 'active']);

        TeacherAvailability::create([
            'teacher_id' => $unavailableTeacher->id, 'bell_timing_id' => $bellTiming->id, 'is_available' => false,
        ]);

        return compact('bellTiming', 'class', 'section', 'subject', 'absentTeacher', 'availableTeacher', 'unavailableTeacher');
    }

    private function pendingSubstitution(array $f): TeacherSubstitution
    {
        return TeacherSubstitution::create([
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
            'status' => 'pending',
            'created_by' => 1,
        ]);
    }

    // 1. Available substitute is accepted (assignSubstitute -- the primary manual "select substitute" action).
    public function test_assign_substitute_accepts_an_available_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $substitution = $this->pendingSubstitution($f);

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign', $substitution), [
            'substitute_teacher_id' => $f['availableTeacher']->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('teacher_substitutions', [
            'id' => $substitution->id, 'substitute_teacher_id' => $f['availableTeacher']->id, 'status' => 'assigned',
        ]);
    }

    public function test_assign_substitute_rejects_an_unavailable_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $substitution = $this->pendingSubstitution($f);

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign', $substitution), [
            'substitute_teacher_id' => $f['unavailableTeacher']->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('teacher_substitutions', [
            'id' => $substitution->id, 'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('teacher_substitutions', [
            'id' => $substitution->id, 'substitute_teacher_id' => $f['unavailableTeacher']->id,
        ]);
    }

    // 2. Unavailable substitute rejected during creation. The plain store()
    // route never accepts substitute_teacher_id directly (it's always
    // auto-assigned by suggestSubstitutes()) -- assignFromSlot() is the
    // real "create a substitution with a manually-chosen substitute in one
    // step" path, so it stands in for "store()" here.
    public function test_assign_from_slot_rejects_creating_a_substitution_with_an_unavailable_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign-from-slot'), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
            'substitute_teacher_id' => $f['unavailableTeacher']->id,
        ]);

        $response->assertSessionHas('error');
        // 4. No invalid substitution record was written after rejection.
        $this->assertDatabaseMissing('teacher_substitutions', [
            'absent_teacher_id' => $f['absentTeacher']->id, 'substitute_teacher_id' => $f['unavailableTeacher']->id,
        ]);
    }

    public function test_assign_from_slot_accepts_creating_a_substitution_with_an_available_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.assign-from-slot'), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
            'substitute_teacher_id' => $f['availableTeacher']->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('teacher_substitutions', [
            'absent_teacher_id' => $f['absentTeacher']->id, 'substitute_teacher_id' => $f['availableTeacher']->id, 'status' => 'assigned',
        ]);
    }

    // 3. Existing substitution update cannot assign an unavailable substitute.
    public function test_update_rejects_assigning_an_unavailable_substitute(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $substitution = $this->pendingSubstitution($f);

        $response = $this->actingAs($admin)->put(route('admin.teacher-substitutions.update', $substitution), [
            'substitution_date' => $substitution->substitution_date->toDateString(),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
            'status' => 'assigned',
            'substitute_teacher_id' => $f['unavailableTeacher']->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertNotSame($f['unavailableTeacher']->id, $substitution->fresh()->substitute_teacher_id);
    }

    public function test_update_still_accepts_assigning_an_available_substitute(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();
        $substitution = $this->pendingSubstitution($f);

        $response = $this->actingAs($admin)->put(route('admin.teacher-substitutions.update', $substitution), [
            'substitution_date' => $substitution->substitution_date->toDateString(),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
            'status' => 'assigned',
            'substitute_teacher_id' => $f['availableTeacher']->id,
        ]);

        $response->assertRedirect(route('admin.teacher-substitutions.index'));
        $this->assertSame($f['availableTeacher']->id, $substitution->fresh()->substitute_teacher_id);
    }

    // 5. Existing automatic substitute suggestion behavior still passes:
    // suggestSubstitutes()/store() must never auto-assign a teacher who is
    // marked unavailable for the substitution's bell timing.
    public function test_automatic_suggestion_never_assigns_an_unavailable_teacher(): void
    {
        $f = $this->fixtures();
        $admin = $this->admin();

        // Only the unavailable teacher and the absent teacher exist as
        // candidates besides availableTeacher -- keep this simple by
        // making unavailableTeacher the only other real candidate so a
        // regression (suggesting them anyway) would be obvious.
        $response = $this->actingAs($admin)->post(route('admin.teacher-substitutions.store'), [
            'substitution_date' => now()->format('Y-m-d'),
            'absent_teacher_id' => $f['absentTeacher']->id,
            'class_id' => $f['class']->id,
            'section_id' => $f['section']->id,
            'subject_id' => $f['subject']->id,
            'bell_timing_id' => $f['bellTiming']->id,
        ]);

        $response->assertSessionHas('success');
        $substitution = TeacherSubstitution::where('absent_teacher_id', $f['absentTeacher']->id)->firstOrFail();
        $this->assertNotSame($f['unavailableTeacher']->id, $substitution->substitute_teacher_id);
    }
}
