<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CustomReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $columns;
    protected $reportType;

    public function __construct($data, $columns, $reportType)
    {
        $this->data = collect($data);
        $this->columns = $columns;
        $this->reportType = $reportType;
    }

    public function collection()
    {
        return $this->data->map(function($row) {
            return array_values($row);
        });
    }

    public function headings(): array
    {
        // Convert column names to readable headers
        return array_map(function($column) {
            return ucwords(str_replace('_', ' ', $column));
        }, $this->columns);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                    ],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return ucfirst($this->reportType) . ' Report';
    }
}
