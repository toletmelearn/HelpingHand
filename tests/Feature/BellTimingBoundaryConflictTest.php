<?php

namespace Tests\Feature;

use App\Models\BellTiming;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UAT Step 5, Scenario 1: bulk-creating a back-to-back schedule (18
 * requested periods across 6 days) only created 12 -- every period whose
 * start_time exactly matched the previous period's end_time was flagged
 * as a false-positive conflict. Root cause was
 * BellTimingController::bulkCreate()'s conflict query: whereBetween on
 * start_time/end_time is inclusive on both ends, so a touching (not
 * overlapping) boundary matched. Fixed to a single strict-inequality
 * overlap test (existing.start < new.end AND existing.end > new.start),
 * the standard interval-overlap condition that correctly excludes
 * touching boundaries while still catching every genuine overlap.
 */
class BellTimingBoundaryConflictTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function bulkCreate(User $admin, string $start, string $end, string $classSection = 'Boundary Class'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday'],
            'class_section' => $classSection,
            'academic_year' => '2026-2027',
            'periods' => [
                [
                    'period_name' => 'New Period',
                    'start_time' => $start,
                    'end_time' => $end,
                    'is_break' => false,
                    'order_index' => 99,
                ],
            ],
        ]);
    }

    public function test_exact_end_to_start_adjacency_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:40',
            'class_section' => 'Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        // New period starts exactly when the existing one ends -- back-to-back, not overlapping.
        $this->bulkCreate($admin, '09:40', '10:20');

        $this->assertSame(2, BellTiming::where('class_section', 'Boundary Class')->where('day_of_week', 'Monday')->count());
    }

    public function test_genuine_overlap_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:40',
            'class_section' => 'Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        // New period starts one minute before the existing one ends -- a real overlap.
        $this->bulkCreate($admin, '09:39', '10:20');

        $this->assertSame(1, BellTiming::where('class_section', 'Boundary Class')->where('day_of_week', 'Monday')->count());
    }

    public function test_identical_timing_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:40',
            'class_section' => 'Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        $this->bulkCreate($admin, '09:00', '09:40');

        $this->assertSame(1, BellTiming::where('class_section', 'Boundary Class')->where('day_of_week', 'Monday')->count());
    }

    public function test_contained_timing_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Long Period', 'start_time' => '09:00', 'end_time' => '10:30',
            'class_section' => 'Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        // New period sits entirely inside the existing one.
        $this->bulkCreate($admin, '09:15', '09:45');

        $this->assertSame(1, BellTiming::where('class_section', 'Boundary Class')->where('day_of_week', 'Monday')->count());
    }

    public function test_separate_non_overlapping_timing_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Period 1', 'start_time' => '09:00', 'end_time' => '09:40',
            'class_section' => 'Boundary Class', 'is_active' => true, 'order_index' => 1,
        ]);

        // Clean gap between the two periods.
        $this->bulkCreate($admin, '10:00', '10:40');

        $this->assertSame(2, BellTiming::where('class_section', 'Boundary Class')->where('day_of_week', 'Monday')->count());
    }

    public function test_bulk_create_no_longer_drops_back_to_back_periods(): void
    {
        $admin = $this->makeAdmin();

        // Exactly the UAT Step 5 Scenario 1 reproduction: 3 back-to-back
        // periods per day (Period 1 / Recess / Period 2, each starting
        // exactly when the previous one ends) across 6 days -- 18 periods
        // requested, all 18 must now be created.
        $response = $this->actingAs($admin)->post(route('bell-timing.bulk-create.process'), [
            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            'class_section' => 'Full Week Boundary Class',
            'academic_year' => '2026-2027',
            'periods' => [
                ['period_name' => 'Period 1', 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false, 'order_index' => 0],
                ['period_name' => 'Recess', 'start_time' => '08:40', 'end_time' => '08:55', 'is_break' => true, 'order_index' => 1],
                ['period_name' => 'Period 2', 'start_time' => '08:55', 'end_time' => '09:35', 'is_break' => false, 'order_index' => 2],
            ],
        ]);

        $response->assertRedirect(route('bell-timing.index'));
        $response->assertSessionHas('success', 'Successfully created 18 bell timings.');
        $this->assertSame(18, BellTiming::where('class_section', 'Full Week Boundary Class')->count());
    }
}
