<?php

namespace App\Exports;

use App\Models\AcademicSession;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Phase 5: Class timetable Excel export -- reads the exact same
 * [period][day] grid TimetableController::buildTimetableGrid() already
 * builds for classPdf(), so the Excel and PDF exports can never drift
 * apart on what a "class timetable" actually contains.
 */
class ClassTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly ?AcademicSession $session,
        private readonly array $periods,
        private readonly array $days,
        private readonly array $periodMeta,
        private readonly array $grid,
        private readonly ?int $lastTeachingPeriod
    ) {
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->periods as $period) {
            $row = [$period];

            foreach ($this->days as $day) {
                $meta = $this->periodMeta[$period][$day] ?? null;
                $isNonTeaching = $meta && !$meta['is_teaching'];
                $beyondClassDay = $meta && $this->lastTeachingPeriod && $meta['order_index'] > $this->lastTeachingPeriod;
                $slot = $this->grid[$period][$day] ?? null;

                if ($beyondClassDay) {
                    $row[] = '';
                } elseif ($isNonTeaching) {
                    $row[] = $meta['label'];
                } elseif ($slot) {
                    $subject = $slot->subject->name ?? '';
                    $teacher = $slot->teacher->short_name ?? $slot->teacher->name ?? '';
                    if ($slot->coTeacher) {
                        $teacher .= ' / ' . ($slot->coTeacher->short_name ?? $slot->coTeacher->name);
                    }
                    $row[] = trim($subject . ' - ' . $teacher, ' -');
                } else {
                    $row[] = '';
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    public function headings(): array
    {
        return array_merge(['Period'], $this->days);
    }

    public function title(): string
    {
        return substr($this->title, 0, 31) ?: 'Class Timetable';
    }
}
