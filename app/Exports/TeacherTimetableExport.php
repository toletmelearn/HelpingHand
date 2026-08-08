<?php

namespace App\Exports;

use App\Models\AcademicSession;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Phase 5: Teacher timetable Excel export -- reads the same [period][day]
 * grid TimetableController::buildTimetableGrid() already builds for
 * teacherPdf() (primary teacher OR co-teacher, published only).
 */
class TeacherTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $teacherName,
        private readonly ?AcademicSession $session,
        private readonly array $periods,
        private readonly array $days,
        private readonly array $periodMeta,
        private readonly array $grid
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
                $slot = $this->grid[$period][$day] ?? null;

                if ($isNonTeaching) {
                    $row[] = $meta['label'];
                } elseif ($slot) {
                    $className = $slot->schoolClass->name ?? '';
                    $className .= $slot->section ? ' ' . $slot->section->name : '';
                    $subject = $slot->subject->name ?? '';
                    $cell = trim($className . ' - ' . $subject, ' -');
                    if ($slot->room_number) {
                        $cell .= " (Room {$slot->room_number})";
                    }
                    $row[] = $cell;
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
        return substr($this->teacherName, 0, 31) ?: 'Teacher Timetable';
    }
}
