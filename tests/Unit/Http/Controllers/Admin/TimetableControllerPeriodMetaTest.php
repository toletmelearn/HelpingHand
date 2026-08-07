<?php

namespace Tests\Unit\Http\Controllers\Admin;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\BellTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * T2b item 4: buildPeriodDayAxes()'s $periodMeta -- which (period, day)
 * cells are non-teaching, and what label to shade them with in the PDFs.
 * Private method, tested via reflection since there's no public seam
 * (the alternative, asserting on raw PDF bytes, is unreliable -- DomPDF's
 * output isn't a reliably text-searchable format, and every other PDF
 * test in this codebase already only checks magic bytes for that reason).
 */
class TimetableControllerPeriodMetaTest extends TestCase
{
    use RefreshDatabase;

    private function callBuildPeriodDayAxes(?string $academicYear = null): array
    {
        $controller = new TimetableController();
        $method = new ReflectionMethod(TimetableController::class, 'buildPeriodDayAxes');
        $method->setAccessible(true);

        return $method->invoke($controller, $academicYear);
    }

    public function test_teaching_period_is_marked_is_teaching_true(): void
    {
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);

        [, , $meta] = $this->callBuildPeriodDayAxes();

        $this->assertTrue($meta['P1']['Monday']['is_teaching']);
    }

    public function test_break_period_is_marked_non_teaching_with_a_label(): void
    {
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'Lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'is_active' => true, 'is_break' => true, 'order_index' => 5]);

        [, , $meta] = $this->callBuildPeriodDayAxes();

        $this->assertFalse($meta['Lunch']['Monday']['is_teaching']);
        $this->assertSame('Break', $meta['Lunch']['Monday']['label']);
    }

    public function test_assembly_period_is_marked_non_teaching_with_custom_label(): void
    {
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Assembly', 'start_time' => '07:45', 'end_time' => '08:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 0,
            'period_type' => BellTiming::PERIOD_TYPE_ASSEMBLY, 'custom_label' => 'Morning Assembly',
        ]);

        [, , $meta] = $this->callBuildPeriodDayAxes();

        $this->assertFalse($meta['Assembly']['Monday']['is_teaching']);
        $this->assertSame('Morning Assembly', $meta['Assembly']['Monday']['label']);
    }

    public function test_assembly_without_custom_label_falls_back_to_period_type(): void
    {
        BellTiming::create([
            'day_of_week' => 'Monday', 'period_name' => 'Assembly', 'start_time' => '07:45', 'end_time' => '08:00',
            'is_active' => true, 'is_break' => false, 'order_index' => 0,
            'period_type' => BellTiming::PERIOD_TYPE_ASSEMBLY,
        ]);

        [, , $meta] = $this->callBuildPeriodDayAxes();

        $this->assertSame('Assembly', $meta['Assembly']['Monday']['label']);
    }

    public function test_same_period_name_can_differ_by_day(): void
    {
        // P1 on Monday is teaching; P1 on Saturday (short day) is zero period -- same
        // period_name, different day, must not share metadata.
        BellTiming::create(['day_of_week' => 'Monday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:45', 'is_active' => true, 'is_break' => false, 'order_index' => 1]);
        BellTiming::create(['day_of_week' => 'Saturday', 'period_name' => 'P1', 'start_time' => '08:00', 'end_time' => '08:30', 'is_active' => true, 'is_break' => false, 'order_index' => 1, 'period_type' => BellTiming::PERIOD_TYPE_ZERO]);

        [, , $meta] = $this->callBuildPeriodDayAxes();

        $this->assertTrue($meta['P1']['Monday']['is_teaching']);
        $this->assertFalse($meta['P1']['Saturday']['is_teaching']);
    }
}
