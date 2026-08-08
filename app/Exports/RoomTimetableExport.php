<?php

namespace App\Exports;

use App\Models\AcademicSession;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Phase 5: Room timetable Excel export -- reads the same [period][day]
 * grid the interactive room view (TimetableController::roomView()) and
 * buildTimetableGrid() already build for a single room_number.
 */
class RoomTimetableExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(
        private readonly string $room,
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
                    $teacher = $slot->teacher->name ?? '';
                    $row[] = trim("{$className} - {$subject} ({$teacher})", ' -()');
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
        return substr('Room ' . $this->room, 0, 31) ?: 'Room Timetable';
    }
}
