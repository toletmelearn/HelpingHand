<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Phase 5: Master timetable Excel export -- reads the same
 * TimetableController::masterTimetableData() data masterPdf() already
 * builds; one sheet per operating day (mirrors masterPdf()'s one-page-
 * per-day layout) since a single flat sheet with every class x period x
 * day combination would be unreadable.
 */
class MasterTimetableExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data)
    {
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->data['days'] as $day) {
            $sheets[] = new MasterTimetableDaySheetExport(
                $day,
                $this->data['periods'],
                $this->data['periodMeta'],
                $this->data['classes'],
                $this->data['byDay'][$day] ?? []
            );
        }

        return $sheets;
    }
}
