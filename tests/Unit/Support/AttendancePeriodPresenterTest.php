<?php

namespace Tests\Unit\Support;

use App\Support\Attendance\AttendancePeriodPresenter;
use PHPUnit\Framework\TestCase;

class AttendancePeriodPresenterTest extends TestCase
{
    public function test_classify_null_as_canonical_full_day(): void
    {
        $this->assertSame(
            AttendancePeriodPresenter::FULL_DAY_CANONICAL_NULL,
            AttendancePeriodPresenter::classify(null)
        );
    }

    public function test_classify_empty_string_as_full_day_empty_string(): void
    {
        $this->assertSame(
            AttendancePeriodPresenter::FULL_DAY_EMPTY_STRING,
            AttendancePeriodPresenter::classify('')
        );
    }

    public function test_classify_whitespace_as_full_day_empty_string(): void
    {
        $this->assertSame(
            AttendancePeriodPresenter::FULL_DAY_EMPTY_STRING,
            AttendancePeriodPresenter::classify(" \t\n ")
        );
    }

    public function test_classify_full_day_labels_as_full_day_label(): void
    {
        foreach (['full day', 'Full Day', 'full_day', 'full-day', 'fullday', 'all day', 'all_day', 'all-day'] as $period) {
            $this->assertSame(
                AttendancePeriodPresenter::FULL_DAY_LABEL,
                AttendancePeriodPresenter::classify($period),
                "Failed asserting {$period} is a full-day label."
            );
        }
    }

    public function test_classify_named_period_as_period_specific(): void
    {
        foreach (['1', 'Period 1', 'Morning'] as $period) {
            $this->assertSame(
                AttendancePeriodPresenter::PERIOD_SPECIFIC,
                AttendancePeriodPresenter::classify($period)
            );
        }
    }

    public function test_display_full_day_like_values_as_full_day(): void
    {
        foreach ([null, '', '   ', 'Full Day', 'full_day', 'all-day'] as $period) {
            $this->assertSame('Full Day', AttendancePeriodPresenter::display($period));
        }
    }

    public function test_display_named_period_trimmed(): void
    {
        $this->assertSame('Period 1', AttendancePeriodPresenter::display(' Period 1 '));
        $this->assertSame('Morning', AttendancePeriodPresenter::display('Morning'));
    }

    public function test_is_canonical_full_day_only_for_null(): void
    {
        $this->assertTrue(AttendancePeriodPresenter::isCanonicalFullDay(null));
        $this->assertFalse(AttendancePeriodPresenter::isCanonicalFullDay(''));
        $this->assertFalse(AttendancePeriodPresenter::isCanonicalFullDay('Full Day'));
        $this->assertFalse(AttendancePeriodPresenter::isCanonicalFullDay('Period 1'));
    }

    public function test_detects_non_canonical_full_day_values(): void
    {
        foreach (['', ' ', 'Full Day', 'full_day', 'all-day'] as $period) {
            $this->assertTrue(AttendancePeriodPresenter::isNonCanonicalFullDay($period));
        }

        $this->assertFalse(AttendancePeriodPresenter::isNonCanonicalFullDay(null));
        $this->assertFalse(AttendancePeriodPresenter::isNonCanonicalFullDay('Period 1'));
    }

    public function test_helper_is_pure_and_does_not_touch_database(): void
    {
        $this->assertSame('Full Day', AttendancePeriodPresenter::display(null));
        $this->assertSame('Morning', AttendancePeriodPresenter::display(' Morning '));
        $this->assertSame(
            AttendancePeriodPresenter::PERIOD_SPECIFIC,
            AttendancePeriodPresenter::classify('Morning')
        );
    }
}
