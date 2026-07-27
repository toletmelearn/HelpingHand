<?php

namespace Tests\Unit\Models;

use App\Models\BellTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T2b item 1: bell_timings.period_type. Also guards against the
 * getPeriodTypeAttribute() accessor this migration replaced ever coming
 * back and silently shadowing the real column again.
 */
class BellTimingPeriodTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_period_type_defaults_to_teaching(): void
    {
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'P1',
            'start_time' => '08:00', 'end_time' => '08:45',
            'is_active' => true, 'is_break' => false, 'order_index' => 1,
        ]);

        $this->assertSame('teaching', $timing->fresh()->period_type);
    }

    public function test_period_type_can_be_set_to_a_non_teaching_value(): void
    {
        $timing = BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Assembly',
            'start_time' => '07:45', 'end_time' => '08:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 0,
            'period_type' => BellTiming::PERIOD_TYPE_ASSEMBLY,
        ]);

        $this->assertSame('assembly', $timing->fresh()->period_type);
    }

    public function test_teaching_type_scope_excludes_non_teaching_periods(): void
    {
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Assembly', 'start_time' => '07:45', 'end_time' => '08:00', 'is_active' => true, 'is_break' => false, 'order_index' => 0, 'period_type' => BellTiming::PERIOD_TYPE_ASSEMBLY]);
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'is_active' => true, 'is_break' => true, 'order_index' => 5, 'period_type' => BellTiming::PERIOD_TYPE_BREAK]);

        $this->assertSame(1, BellTiming::teachingType()->count());
    }
}
