<?php

namespace Tests\Feature\Admin;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production-readiness audit follow-up: BellTimingPolicy's create()/update()
 * are a plain `hasRole('admin') || hasRole('teacher')` check -- ANY
 * teacher-role account may write ANY bell timing, with no per-teacher
 * ownership/ scoping condition at all (unlike TimetableSlotPolicy's
 * class-section-scoped teacherAssignedToClassSection() check). This test
 * proves that boundary explicitly from the CURRENT policy, both directions:
 * a teacher genuinely can create/update (not merely assumed), a non-admin/
 * non-teacher genuinely cannot, and -- the asymmetry that makes "teacher
 * can write" NOT mean "teacher has unrestricted access" -- a teacher still
 * cannot delete.
 */
class BellTimingPolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function teacherUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'teacher'], ['display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function unprivilegedUser(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'student'], ['display_name' => 'Student']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'day_of_week' => 'Monday',
            'period_name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'is_active' => true,
            'is_break' => false,
            'order_index' => 1,
        ], $overrides);
    }

    // --- teacher-write boundary: allowed --------------------------------

    public function test_teacher_role_user_can_create_a_bell_timing(): void
    {
        $user = $this->teacherUser();

        $response = $this->actingAs($user)->post(route('bell-timing.store'), $this->validPayload());

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertDatabaseHas('bell_timings', ['period_name' => 'Period 1', 'day_of_week' => 'Monday']);
    }

    public function test_teacher_role_user_can_update_a_bell_timing(): void
    {
        $user = $this->teacherUser();
        $bellTiming = BellTiming::create($this->validPayload(['period_name' => 'Original Name']));

        $response = $this->actingAs($user)->put(
            route('bell-timing.update', $bellTiming),
            $this->validPayload(['period_name' => 'Updated By Teacher'])
        );

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id, 'period_name' => 'Updated By Teacher']);
    }

    /** Confirms this is genuinely unrestricted among teachers -- no ownership/creator scoping exists on BellTiming. */
    public function test_teacher_role_user_can_update_a_bell_timing_they_did_not_create(): void
    {
        $creator = $this->teacherUser();
        $otherTeacher = $this->teacherUser();
        $bellTiming = BellTiming::create(array_merge($this->validPayload(), ['created_by' => $creator->id]));

        $response = $this->actingAs($otherTeacher)->put(
            route('bell-timing.update', $bellTiming),
            $this->validPayload(['period_name' => 'Changed By A Different Teacher'])
        );

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id, 'period_name' => 'Changed By A Different Teacher']);
    }

    // --- teacher-write boundary: denied ----------------------------------

    public function test_non_teacher_non_admin_user_cannot_create_a_bell_timing(): void
    {
        $user = $this->unprivilegedUser();

        $response = $this->actingAs($user)->post(route('bell-timing.store'), $this->validPayload());

        $response->assertForbidden();
        $this->assertDatabaseMissing('bell_timings', ['period_name' => 'Period 1']);
    }

    public function test_non_teacher_non_admin_user_cannot_update_a_bell_timing(): void
    {
        $user = $this->unprivilegedUser();
        $bellTiming = BellTiming::create($this->validPayload(['period_name' => 'Untouched']));

        $response = $this->actingAs($user)->put(
            route('bell-timing.update', $bellTiming),
            $this->validPayload(['period_name' => 'Should Not Apply'])
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id, 'period_name' => 'Untouched']);
    }

    /** The asymmetry: a teacher can write, but delete/restore/forceDelete are admin-only -- "can write" is not "unrestricted access." */
    public function test_teacher_role_user_cannot_delete_a_bell_timing(): void
    {
        $user = $this->teacherUser();
        $bellTiming = BellTiming::create($this->validPayload());

        $response = $this->actingAs($user)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertForbidden();
        $this->assertDatabaseHas('bell_timings', ['id' => $bellTiming->id]);
    }

    public function test_admin_can_delete_a_bell_timing(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);
        $bellTiming = BellTiming::create($this->validPayload());

        $response = $this->actingAs($user)->delete(route('bell-timing.destroy', $bellTiming));

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertDatabaseMissing('bell_timings', ['id' => $bellTiming->id]);
    }
}
