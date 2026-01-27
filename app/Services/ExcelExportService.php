<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExcelExportService
{
    /**
     * Generate an Excel report with customizable formatting
     */
    public function generateReport($filename, $data, $options = [])
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator("School Management System")
                ->setTitle($options['title'] ?? 'Biometric Report')
                ->setDescription($options['description'] ?? 'Biometric Attendance Report')
                ->setSubject($options['subject'] ?? 'Attendance Data')
                ->setKeywords($options['keywords'] ?? 'attendance biometric school')
                ->setCategory($options['category'] ?? 'Attendance Reports');

            // Add header with logo if provided
            $this->addHeader($sheet, $options);

            // Add title
            $this->addTitle($sheet, $options['title'] ?? 'Biometric Report', $options);

            // Add subtitle if provided
            if (isset($options['subtitle'])) {
                $this->addSubtitle($sheet, $options['subtitle'], $options);
            }

            // Add summary data if provided
            if (isset($data['summary'])) {
                $this->addSummary($sheet, $data['summary'], $options);
            }

            // Add table data
            $row = $this->addTableData($sheet, $data['table_data'] ?? [], $options);

            // Add chart data if provided
            if (isset($data['chart_data'])) {
                $this->addChartData($sheet, $data['chart_data'], $row + 2, $options);
            }

            // Apply formatting
            $this->applyFormatting($sheet, $options);

            // Save the file
            $writer = new Xlsx($spreadsheet);
            
            $path = storage_path('app/temp/' . $filename . '.xlsx');
            $writer->save($path);

            return $path;
        } catch (\Exception $e) {
            \Log::error('Excel Generation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add header section with logo
     */
    private function addHeader($sheet, $options)
    {
        $startRow = 1;
        
        if (isset($options['logo_path']) && file_exists($options['logo_path'])) {
            $drawing = new Drawing();
            $drawing->setName('Logo');
            $drawing->setDescription('Company Logo');
            $drawing->setPath($options['logo_path']);
            $drawing->setHeight(60);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setWorksheet($sheet);
            
            $sheet->setCellValue('B' . $startRow, $options['company_name'] ?? config('app.name', 'School Management System'));
            $sheet->getStyle('B' . $startRow)->getFont()->setSize(14)->setBold(true);
        } else {
            $sheet->setCellValue('A' . $startRow, $options['company_name'] ?? config('app.name', 'School Management System'));
            $sheet->getStyle('A' . $startRow)->getFont()->setSize(14)->setBold(true);
        }
        
        $sheet->getRowDimension($startRow)->setRowHeight(30);
    }

    /**
     * Add report title
     */
    private function addTitle($sheet, $title, $options)
    {
        $row = 2;
        $col = 'A';
        
        $sheet->setCellValue($col . $row, $title);
        $sheet->getStyle($col . $row)->getFont()->setSize(16)->setBold(true);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells($col . $row . ':' . $this->getColumnByIndex(count($options['columns'] ?? []) + 1));
    }

    /**
     * Add subtitle
     */
    private function addSubtitle($sheet, $subtitle, $options)
    {
        $row = 3;
        $col = 'A';
        
        $sheet->setCellValue($col . $row, $subtitle);
        $sheet->getStyle($col . $row)->getFont()->setSize(12);
        $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells($col . $row . ':' . $this->getColumnByIndex(count($options['columns'] ?? []) + 1));
    }

    /**
     * Add summary section
     */
    private function addSummary($sheet, $summary, $options)
    {
        $row = 4;
        $col = 'A';
        
        $sheet->setCellValue($col . $row, 'Summary:');
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $row++;
        
        foreach ($summary as $key => $value) {
            $sheet->setCellValue($col . $row, ucfirst(str_replace('_', ' ', $key)));
            $sheet->setCellValue($col . $row + 1, $value);
            $row++;
        }
        
        $row++; // Add extra space after summary
    }

    /**
     * Add table data to sheet
     */
    private function addTableData($sheet, $tableData, $options)
    {
        if (empty($tableData)) {
            return $sheet->getHighestRow();
        }

        $startRow = $sheet->getHighestRow() + 1;
        $currentRow = $startRow;

        // Add headers
        $headers = array_keys($tableData[0]);
        $colIndex = 0;
        
        foreach ($headers as $header) {
            $column = $this->getColumnByIndex($colIndex + 1);
            $sheet->setCellValue($column . $currentRow, ucfirst(str_replace('_', ' ', $header)));
            $sheet->getStyle($column . $currentRow)->getFont()->setBold(true);
            $sheet->getStyle($column . $currentRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E6E6E6');
            $sheet->getStyle($column . $currentRow)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);
            $colIndex++;
        }
        
        $currentRow++;

        // Add data rows
        foreach ($tableData as $rowData) {
            $colIndex = 0;
            foreach ($rowData as $value) {
                $column = $this->getColumnByIndex($colIndex + 1);
                $sheet->setCellValue($column . $currentRow, $value);
                $sheet->getStyle($column . $currentRow)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $colIndex++;
            }
            $currentRow++;
        }

        // Auto-size columns
        $colIndex = 0;
        foreach ($headers as $header) {
            $column = $this->getColumnByIndex($colIndex + 1);
            $sheet->getColumnDimension($column)->setAutoSize(true);
            $colIndex++;
        }

        return $currentRow;
    }

    /**
     * Add chart data (as pivot tables or summary data)
     */
    private function addChartData($sheet, $chartData, $startRow, $options)
    {
        $currentRow = $startRow;
        
        foreach ($chartData as $chart) {
            $sheet->setCellValue('A' . $currentRow, $chart['title'] ?? 'Chart Data');
            $sheet->getStyle('A' . $currentRow)->getFont()->setBold(true);
            $currentRow++;
            
            $colIndex = 0;
            foreach ($chart['data'] as $label => $value) {
                $column = $this->getColumnByIndex($colIndex + 1);
                $sheet->setCellValue($column . $currentRow, $label);
                $sheet->setCellValue($column . ($currentRow + 1), $value);
                
                // Apply borders to the cells
                $sheet->getStyle($column . $currentRow)->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle($column . ($currentRow + 1))->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);
                
                $colIndex++;
            }
            
            $currentRow += 3; // Skip rows for next chart
        }
    }

    /**
     * Apply general formatting to the sheet
     */
    private function applyFormatting($sheet, $options)
    {
        // Set alignment for the entire sheet
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        
        $range = 'A1:' . $highestColumn . $highestRow;
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        
        // Set default font
        $sheet->getStyle($range)->getFont()->setName('Arial')->setSize(10);
    }

    /**
     * Convert column index to letter (1 -> A, 2 -> B, etc.)
     */
    private function getColumnByIndex($index)
    {
        $result = '';
        while ($index > 0) {
            $index--;
            $result = chr(65 + ($index % 26)) . $result;
            $index = intval($index / 26);
        }
        return $result;
    }

    /**
     * Export teacher biometric attendance report to Excel
     */
    public function exportTeacherAttendanceReport($teacherId, $startDate, $endDate, $options = [])
    {
        // This would normally fetch data from the database
        $data = [
            'summary' => [
                'teacher_name' => 'John Doe', // Would be fetched from DB
                'department' => 'Mathematics',
                'total_working_days' => 22,
                'days_present' => 20,
                'days_absent' => 2,
                'late_arrivals' => 3,
                'early_departures' => 1,
                'attendance_percentage' => '90.91%'
            ],
            'table_data' => [
                ['date' => '2024-01-01', 'first_punch' => '08:45', 'last_punch' => '17:15', 'status' => 'On Time'],
                ['date' => '2024-01-02', 'first_punch' => '09:15', 'last_punch' => '16:45', 'status' => 'Late Arrival'],
                ['date' => '2024-01-03', 'first_punch' => '08:30', 'last_punch' => '17:30', 'status' => 'Early Departure'],
                // More data would be fetched from DB
            ],
            'chart_data' => [
                [
                    'type' => 'bar',
                    'title' => 'Monthly Attendance Trend',
                    'data' => ['Week 1' => 5, 'Week 2' => 4, 'Week 3' => 5, 'Week 4' => 5]
                ]
            ]
        ];

        $options = array_merge([
            'title' => 'Teacher Biometric Attendance Report',
            'subtitle' => 'Attendance Summary for Period: ' . $startDate . ' to ' . $endDate,
            'filename' => 'teacher_attendance_' . $teacherId . '_' . date('Y-m-d')
        ], $options);

        return $this->generateReport($options['filename'], $data, $options);
    }

    /**
     * Export department-wise performance report to Excel
     */
    public function exportDepartmentPerformanceReport($department, $startDate, $endDate, $options = [])
    {
        $data = [
            'summary' => [
                'department' => $department,
                'total_teachers' => 15,
                'average_attendance' => '89.5%',
                'average_punctuality' => '85.2%',
                'average_discipline' => '87.8%'
            ],
            'table_data' => [
                ['teacher_name' => 'John Doe', 'attendance' => '92%', 'punctuality' => '88%', 'discipline' => '90%'],
                ['teacher_name' => 'Jane Smith', 'attendance' => '87%', 'punctuality' => '82%', 'discipline' => '85%'],
                ['teacher_name' => 'Bob Johnson', 'attendance' => '95%', 'punctuality' => '91%', 'discipline' => '93%'],
                // More data would be fetched from DB
            ]
        ];

        $options = array_merge([
            'title' => 'Department Performance Report',
            'subtitle' => 'Performance Summary for ' . $department . ': ' . $startDate . ' to ' . $endDate,
            'filename' => 'dept_performance_' . str_replace(' ', '_', $department) . '_' . date('Y-m-d')
        ], $options);

        return $this->generateReport($options['filename'], $data, $options);
    }

    /**
     * Export overall biometric analytics report to Excel
     */
    public function exportBiometricAnalyticsReport($startDate, $endDate, $options = [])
    {
        $data = [
            'summary' => [
                'total_teachers' => 50,
                'total_punches' => 1250,
                'average_attendance_rate' => '88.5%',
                'late_arrival_percentage' => '12.3%',
                'early_departure_percentage' => '8.7%'
            ],
            'table_data' => [
                ['metric' => 'Total Teachers', 'value' => 50],
                ['metric' => 'Total Punches', 'value' => 1250],
                ['metric' => 'Avg Attendance Rate', 'value' => '88.5%'],
                ['metric' => 'Late Arrival %', 'value' => '12.3%'],
                ['metric' => 'Early Departure %', 'value' => '8.7%'],
            ],
            'chart_data' => [
                [
                    'type' => 'pie',
                    'title' => 'Attendance Distribution',
                    'data' => ['Present' => 88, 'Absent' => 12]
                ],
                [
                    'type' => 'line',
                    'title' => 'Daily Punch Trends',
                    'data' => ['Mon' => 180, 'Tue' => 195, 'Wed' => 175, 'Thu' => 200, 'Fri' => 185]
                ]
            ]
        ];

        $options = array_merge([
            'title' => 'Biometric Analytics Report',
            'subtitle' => 'Comprehensive Analytics for Period: ' . $startDate . ' to ' . $endDate,
            'filename' => 'biometric_analytics_' . date('Y-m-d')
        ], $options);

        return $this->generateReport($options['filename'], $data, $options);
    }

    /**
     * Export raw biometric data with advanced formatting options
     */
    public function exportRawBiometricData($filters, $formatOptions = [])
    {
        // This would fetch raw data from the database based on filters
        $data = [
            'table_data' => [
                ['teacher_id' => 1, 'teacher_name' => 'John Doe', 'department' => 'Mathematics', 'date' => '2024-01-01', 'punch_time' => '08:45:15', 'device' => 'Main Gate', 'status' => 'In'],
                ['teacher_id' => 1, 'teacher_name' => 'John Doe', 'department' => 'Mathematics', 'date' => '2024-01-01', 'punch_time' => '17:15:30', 'device' => 'Main Gate', 'status' => 'Out'],
                ['teacher_id' => 2, 'teacher_name' => 'Jane Smith', 'department' => 'Science', 'date' => '2024-01-01', 'punch_time' => '08:30:20', 'device' => 'Main Gate', 'status' => 'In'],
                // More data would be fetched from DB
            ]
        ];

        $options = array_merge([
            'title' => 'Raw Biometric Data Export',
            'subtitle' => 'Filtered Data Export',
            'filename' => 'raw_biometric_data_' . date('Y-m-d_H-i-s')
        ], $formatOptions);

        return $this->generateReport($options['filename'], $data, $options);
    }
}