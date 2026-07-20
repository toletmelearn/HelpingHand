<?php

namespace App\Support\Attendance;

class AttendancePeriodPresenter
{
    public const FULL_DAY_CANONICAL_NULL = 'full_day_canonical_null';
    public const FULL_DAY_EMPTY_STRING = 'full_day_empty_string';
    public const FULL_DAY_LABEL = 'full_day_label';
    public const PERIOD_SPECIFIC = 'period_specific';

    private const FULL_DAY_LABELS = [
        'full day',
        'full_day',
        'full-day',
        'fullday',
        'all day',
        'all_day',
        'all-day',
    ];

    public static function classify(?string $period): string
    {
        if ($period === null) {
            return self::FULL_DAY_CANONICAL_NULL;
        }

        $trimmed = trim($period);

        if ($trimmed === '') {
            return self::FULL_DAY_EMPTY_STRING;
        }

        if (in_array(strtolower($trimmed), self::FULL_DAY_LABELS, true)) {
            return self::FULL_DAY_LABEL;
        }

        return self::PERIOD_SPECIFIC;
    }

    public static function isFullDayLike(?string $period): bool
    {
        return self::classify($period) !== self::PERIOD_SPECIFIC;
    }

    public static function display(?string $period): string
    {
        if (self::isFullDayLike($period)) {
            return 'Full Day';
        }

        return trim((string) $period);
    }

    public static function isCanonicalFullDay(?string $period): bool
    {
        return self::classify($period) === self::FULL_DAY_CANONICAL_NULL;
    }

    public static function isNonCanonicalFullDay(?string $period): bool
    {
        return in_array(self::classify($period), [
            self::FULL_DAY_EMPTY_STRING,
            self::FULL_DAY_LABEL,
        ], true);
    }
}
