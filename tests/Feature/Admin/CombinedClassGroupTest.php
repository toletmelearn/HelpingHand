<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicSession;
use App\Models\CombinedClassGroup;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2b item 3: combined-class group CRUD.
 */
class CombinedClassGroupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function makeSession(): AcademicSession
    {
        return AcademicSession::create(['name' => '2026-2027', 'code' => '2026-2027', 'start_date' => '2026-04-01', 'end_date' => '2027-03-31']);
    }

    public function test_admin_can_create_a_group_with_two_members(): void
    {
        $admin = $this->admin();
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS' . uniqid()]);
        $session = $this->makeSession();
        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Sanskrit Combined',
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'members' => [
                ['school_class_id' => $classA->id, 'section_id' => null],
                ['school_class_id' => $classB->id, 'section_id' => null],
            ],
        ]);

        $response->assertRedirect(route('combined-class-groups.index'));
        $this->assertDatabaseHas('combined_class_groups', ['name' => 'Sanskrit Combined']);
        $group = CombinedClassGroup::where('name', 'Sanskrit Combined')->firstOrFail();
        $this->assertSame(2, $group->members()->count());
    }

    public function test_store_rejects_a_group_with_fewer_than_two_members(): void
    {
        $admin = $this->admin();
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS' . uniqid()]);
        $session = $this->makeSession();
        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('combined-class-groups.store'), [
            'name' => 'Too Small',
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'members' => [
                ['school_class_id' => $classA->id, 'section_id' => null],
            ],
        ]);

        $response->assertSessionHasErrors('members');
        $this->assertDatabaseMissing('combined_class_groups', ['name' => 'Too Small']);
    }

    public function test_unauthorized_role_cannot_create_a_group(): void
    {
        $teacherUser = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $teacherUser->roles()->attach($role->id);

        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS' . uniqid()]);
        $session = $this->makeSession();
        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $response = $this->actingAs($teacherUser)->post(route('combined-class-groups.store'), [
            'name' => 'Sanskrit Combined',
            'subject_id' => $subject->id,
            'academic_session_id' => $session->id,
            'members' => [
                ['school_class_id' => $classA->id, 'section_id' => null],
                ['school_class_id' => $classB->id, 'section_id' => null],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_delete_a_group_and_its_slots_are_detached_not_deleted(): void
    {
        $admin = $this->admin();
        $subject = Subject::create(['name' => 'Sanskrit', 'code' => 'SANS' . uniqid()]);
        $session = $this->makeSession();
        $classA = SchoolClass::create(['name' => 'Class A', 'class_order' => random_int(1, 100000), 'is_active' => true]);
        $classB = SchoolClass::create(['name' => 'Class B', 'class_order' => random_int(1, 100000), 'is_active' => true]);

        $group = CombinedClassGroup::create(['name' => 'To Delete', 'subject_id' => $subject->id, 'academic_session_id' => $session->id]);
        \App\Models\CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classA->id]);
        \App\Models\CombinedClassGroupMember::create(['combined_class_group_id' => $group->id, 'school_class_id' => $classB->id]);

        $teacher = \App\Models\Teacher::create(['name' => 'T']);
        $timing = \App\Models\BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        $slot = \App\Models\TimetableSlot::create([
            'school_class_id' => $classA->id,
            'bell_timing_id' => $timing->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'combined_class_group_id' => $group->id,
        ]);

        $response = $this->actingAs($admin)->delete(route('combined-class-groups.destroy', $group));

        $response->assertRedirect(route('combined-class-groups.index'));
        $this->assertDatabaseMissing('combined_class_groups', ['id' => $group->id]);
        // The slot itself survives, just detached from the deleted group.
        $this->assertDatabaseHas('timetable_slots', ['id' => $slot->id, 'combined_class_group_id' => null]);
    }
}
