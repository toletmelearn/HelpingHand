<?php

namespace Tests\Unit\Services\Imports;

use App\Services\Imports\ImportNormalizer;
use PHPUnit\Framework\TestCase;

class ImportNormalizerDateTest extends TestCase
{
    private ImportNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new ImportNormalizer();
    }

    /**
     * @dataProvider dateFormatProvider
     */
    public function test_it_normalizes_common_date_formats(string $raw, ?string $expected)
    {
        $this->assertEquals($expected, $this->normalizer->normalizeValue('date_of_birth', $raw));
    }

    public static function dateFormatProvider(): array
    {
        return [
            // Reported directly: space-separated D M Y.
            'space separated' => ['15 05 2019', '2019-05-15'],
            'dot separated' => ['15.05.2019', '2019-05-15'],
            'slash separated' => ['15/05/2019', '2019-05-15'],
            'dash separated' => ['15-05-2019', '2019-05-15'],
            'multiple spaces' => ['15   05   2019', '2019-05-15'],
            // Day > 12 removes any month/day ambiguity as a sanity check.
            'day over 12, slash' => ['25/03/2018', '2018-03-25'],
            // ISO format passes through unambiguously.
            'iso format' => ['2019-05-15', '2019-05-15'],
            'iso format with slashes' => ['2019/05/15', '2019-05-15'],
            // Month-name formats (unambiguous, handled by the generic parser).
            'day-month name-2digit year' => ['16-Apr-21', '2021-04-16'],
            'day-month name-4digit year' => ['27-Feb-2021', '2021-02-27'],
            'month name day, year' => ['May 15, 2019', '2019-05-15'],
            // Excel serial date (43601 is genuinely 2019-05-16 per Excel's
            // day-count epoch -- verified directly against PhpSpreadsheet's
            // own conversion, not assumed).
            'excel serial date' => ['43601', '2019-05-16'],
            // Genuinely invalid -- must not silently produce a wrong date.
            'invalid day/month' => ['32-13-2019', null],
            'garbage' => ['not-a-date', null],
            'blank' => ['', null],
        ];
    }

    public function test_ambiguous_numeric_date_assumes_day_month_year_not_us_convention()
    {
        // "05-06-2019" is ambiguous: could be 5 June (D-M-Y, the convention
        // this system assumes) or May 6th (M-D-Y, US convention). Must
        // resolve to the D-M-Y reading, not silently default to US-style.
        $this->assertEquals('2019-06-05', $this->normalizer->normalizeValue('date_of_birth', '05-06-2019'));
    }
}
