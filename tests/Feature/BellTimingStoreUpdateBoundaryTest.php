<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Follow-up defect fix (post Step-5-UAT audit): BellTimingController::
 * store() and update() shared the same inclusive-whereBetween boundary
 * bug already fixed in bulkCreate() -- a period whose start_time exactly
 * matched another period's end_time (a genuine back-to-back schedule)
 * was flagged as a false-positive conflict. Fixed with the identical
 * strict-inequality overlap test used in bulkCreate() (existing.start <
 * new.end AND existing.end > new.start). BellTimingPolicy is untouched;
 * only the boundary-overlap query changed.
 */
class BellTimingStoreUpdateBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function payload(string $start, string $end, array $overrides = []): array
    {
        return array_merge([
            'day_of_week' => 'Monday',
            'period_name' => 'New Period',
            'start_time' => $start,
            'end_time' => $end,
            'class_section' => 'Store Boundary Class',
            'order_index' => 99,
        ], $overrides);
    }

    // ---- store() ----

    public function test_store_accepts_exact_back_to_back_timing(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Existing', 'start_time' => '09:00', 'end_time' => '10:00',
            'class_section' => 'Store Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.store'), $this->payload('10:00', '11:00'));

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(2, BellTiming::where('class_section', 'Store Boundary Class')->count());
    }

    public function test_store_rejects_genuine_overlap(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Existing', 'start_time' => '09:00', 'end_time' => '10:00',
            'class_section' => 'Store Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        $response = $this->actingAs($admin)->post(route('bell-timing.store'), $this->payload('09:30', '10:30'));

        $response->assertSessionHasErrors('time_conflict');
        $this->assertSame(1, BellTiming::where('class_section', 'Store Boundary Class')->count());
    }

    // ---- update() ----

    public function test_update_accepts_exact_back_to_back_timing(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Neighbour', 'start_time' => '09:00', 'end_time' => '10:00',
            'class_section' => 'Update Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);
        $target = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Movable', 'start_time' => '11:00', 'end_time' => '12:00',
            'class_section' => 'Update Boundary Class', 'is_active' => true, 'order_index' => 2,
        ]);

        // Move the movable period so it starts exactly when the neighbour ends.
        $response = $this->actingAs($admin)->put(route('bell-timing.update', $target), $this->payload('10:00', '10:45', ['class_section' => 'Update Boundary Class']));

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame('10:00:00', $target->fresh()->start_time->format('H:i:s'));
    }

    public function test_update_rejects_genuine_overlap(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Neighbour', 'start_time' => '09:00', 'end_time' => '10:00',
            'class_section' => 'Update Overlap Class', 'is_active' => true, 'order_index' => 1,
        ]);
        $target = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Movable', 'start_time' => '11:00', 'end_time' => '12:00',
            'class_section' => 'Update Overlap Class', 'is_active' => true, 'order_index' => 2,
        ]);

        $response = $this->actingAs($admin)->put(route('bell-timing.update', $target), $this->payload('09:30', '10:30', ['class_section' => 'Update Overlap Class']));

        $response->assertSessionHasErrors('time_conflict');
        $this->assertSame('11:00:00', $target->fresh()->start_time->format('H:i:s'));
    }

    public function test_update_does_not_conflict_with_itself(): void
    {
        $admin = $this->makeAdmin();
        $target = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Self', 'start_time' => '09:00', 'end_time' => '10:00',
            'class_section' => 'Self Conflict Class', 'is_active' => true, 'order_index' => 1,
        ]);

        // Re-save the exact same record with an unrelated field changed
        // (period_name) but identical timing -- must not conflict with itself.
        $response = $this->actingAs($admin)->put(
            route('bell-timing.update', $target),
            $this->payload('09:00', '10:00', ['class_section' => 'Self Conflict Class', 'period_name' => 'Self Renamed'])
        );

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionDoesntHaveErrors('time_conflict');
        $this->assertSame('Self Renamed', $target->fresh()->period_name);
    }

    // ---- authorization untouched ----

    public function test_teacher_role_user_can_still_store_a_bell_timing(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'teacher', 'display_name' => 'Teacher']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)->post(route('bell-timing.store'), $this->payload('08:00', '08:40', ['class_section' => 'Teacher Role Class']));

        $response->assertRedirect(route('bell-timing.index'));
        $this->assertSame(1, BellTiming::where('class_section', 'Teacher Role Class')->count());
    }

    public function test_non_admin_non_teacher_user_still_cannot_store_a_bell_timing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bell-timing.store'), $this->payload('08:00', '08:40'));

        $response->assertForbidden();
    }

    // ---- bulkCreate() unchanged ----

    public function test_bulk_create_still_accepts_back_to_back_periods(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => 'Bulk Boundary Unchanged Class',
            'academic_year' => '2026-2027',
            'periods' => [
                ['period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false, 'order_index' => 0],
                ['period_name' => 'P2', 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => false, 'order_index' => 1],
            ],
        ]);

        $response->assertSessionHas('success', 'Successfully created 2 bell timings.');
        $this->assertSame(2, BellTiming::where('class_section', 'Bulk Boundary Unchanged Class')->count());
    }
}
