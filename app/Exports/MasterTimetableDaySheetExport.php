<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One sheet per operating day for MasterTimetableExport, mirroring
 * masterPdf()'s "one page per day" layout -- built from the same
 * TimetableController::masterTimetableData()['byDay'][$day] shape.
 */
class MasterTimetableDaySheetExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $day,
        private readonly array $periods,
        private readonly array $periodMeta,
        private readonly Collection $classes,
        private readonly array $dayGrid
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];
            $meta = $this->periodMeta[$period][$this->day] ?? null;
            $isNonTeaching = $meta && !$meta['is_teaching'];

            foreach ($this->classes as $class) {
                if ($isNonTeaching) {
                    $row[] = $meta['label'];
                    continue;
                }

                $slot = $this->dayGrid[$class->id][$period] ?? null;
                if (!$slot) {
                    $row[] = '';
                    continue;
                }

                $subject = $slot->subject->code ?? $slot->subject->name ?? '';
                $teacher = $slot->teacher->short_name ?? $slot->teacher->name ?? '';
                $row[] = trim($subject . ' / ' . $teacher, ' /');
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->classes->pluck('name')->all());
    }

    public function title(): string
    {
        return substr($this->day, 0, 31) ?: 'Day';
    }
}
