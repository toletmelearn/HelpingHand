<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TeachersAttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    /**
     * @return Collection
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Teacher ID',
            'Name',
            'Email',
            'Department',
            'Subject',
            'Status',
            'Check-in Time',
            'Remarks',
            'Date',
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            $row['Teacher ID'],
            $row['Name'],
            $row['Email'],
            $row['Department'],
            $row['Subject'],
            $row['Status'],
            $row['Check-in Time'],
            $row['Remarks'],
            $row['Date'],
        ];
    }
}