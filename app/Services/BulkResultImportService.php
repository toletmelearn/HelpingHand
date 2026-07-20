<?php

namespace App\Services;

use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkResultImportService
{
    protected $professionalResultService;
    
    public function __construct(ProfessionalResultService $professionalResultService)
    {
        $this->professionalResultService = $professionalResultService;
    }
    
    /**
     * Import results from Excel file
     */
    public function importFromExcel(UploadedFile $file, int $examId): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            // Remove header row
            array_shift($rows);
            
            $importedCount = 0;
            $errors = [];
            $processedStudents = [];
            
            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1]) || empty($row[2])) {
                    $errors[] = "Row " . ($index + 2) . ": Missing required data";
                    continue;
                }
                
                $studentRoll = trim($row[0]);
                $subject = trim($row[1]);
                $marksObtained = trim($row[2]);
                
                // Validate data
                $validator = Validator::make([
                    'student_roll' => $studentRoll,
                    'subject' => $subject,
                    'marks_obtained' => $marksObtained,
                ], [
                    'student_roll' => 'required|string',
                    'subject' => 'required|string|max:100',
                    'marks_obtained' => 'required|numeric|min:0',
                ]);
                
                if ($validator->fails()) {
                    $errors[] = "Row " . ($index + 2) . ": " . implode(', ', $validator->errors()->all());
                    continue;
                }
                
                // Find student by roll number
                $student = Student::where('roll_number', $studentRoll)->first();
                if (!$student) {
                    $errors[] = "Row " . ($index + 2) . ": Student with roll number '$studentRoll' not found";
                    continue;
                }
                
                // Get exam details
                $exam = Exam::findOrFail($examId);
                
                // Validate marks don't exceed total
                if ($marksObtained > $exam->total_marks) {
                    $errors[] = "Row " . ($index + 2) . ": Marks obtained ($marksObtained) cannot exceed total marks ({$exam->total_marks})";
                    continue;
                }
                
                // Calculate percentage and grade
                $percentage = $exam->total_marks > 0 ? round(($marksObtained / $exam->total_marks) * 100, 2) : 0;
                $grade = $this->professionalResultService->calculateGrade($percentage);
                $resultStatus = $percentage >= 33 ? 'pass' : 'fail';
                
                // Create or update result
                $result = Result::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'exam_id' => $examId,
                        'subject' => $subject,
                    ],
                    [
                        'marks_obtained' => $marksObtained,
                        'total_marks' => $exam->total_marks,
                        'percentage' => $percentage,
                        'grade' => $grade,
                        'academic_year' => $exam->academic_year,
                        'term' => $exam->term,
                        'result_status' => $resultStatus,
                        'generated_by' => Auth::id(),
                        'generated_at' => now(),
                    ]
                );
                
                $importedCount++;
                $processedStudents[] = [
                    'student_name' => $student->name,
                    'roll_number' => $student->roll_number,
                    'subject' => $subject,
                    'marks_obtained' => $marksObtained,
                    'percentage' => $percentage,
                    'grade' => $grade,
                ];
            }
            
            // Generate rankings after import
            $this->professionalResultService->generateCompleteRankings($examId);
            
            return [
                'success' => true,
                'imported_count' => $importedCount,
                'errors' => $errors,
                'processed_students' => $processedStudents,
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Generate sample Excel template
     */
    public function generateSampleTemplate(): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Student Roll Number');
        $sheet->setCellValue('B1', 'Subject');
        $sheet->setCellValue('C1', 'Marks Obtained');
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        
        // Make headers bold
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']],
        ];
        $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
        
        // Add sample data
        $sheet->setCellValue('A2', 'STU001');
        $sheet->setCellValue('B2', 'Mathematics');
        $sheet->setCellValue('C2', '85');
        
        $sheet->setCellValue('A3', 'STU002');
        $sheet->setCellValue('B3', 'Science');
        $sheet->setCellValue('C3', '78');
        
        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'sample_template_') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);
        
        return $tempFile;
    }
    
    /**
     * Export results to Excel
     */
    public function exportResults(int $examId): string
    {
        $exam = Exam::findOrFail($examId);
        $results = Result::where('exam_id', $examId)
            ->with(['student'])
            ->get()
            ->groupBy('student_id');
            
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headers = ['Student Name', 'Roll Number', 'Class', 'Subject', 'Marks Obtained', 'Total Marks', 'Percentage', 'Grade', 'Status'];
        $column = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($column . '1', $header);
            $column++;
        }
        
        // Make headers bold
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCCCCC']],
        ];
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
        
        // Add data
        $row = 2;
        foreach ($results as $studentResults) {
            foreach ($studentResults as $result) {
                $sheet->setCellValue('A' . $row, $result->student->name ?? '');
                $sheet->setCellValue('B' . $row, $result->student->roll_number ?? '');
                $sheet->setCellValue('C' . $row, $result->student->class_name ?? '');
                $sheet->setCellValue('D' . $row, $result->subject);
                $sheet->setCellValue('E' . $row, $result->marks_obtained);
                $sheet->setCellValue('F' . $row, $result->total_marks);
                $sheet->setCellValue('G' . $row, $result->percentage . '%');
                $sheet->setCellValue('H' . $row, $result->grade);
                $sheet->setCellValue('I' . $row, strtoupper($result->result_status));
                $row++;
            }
        }
        
        // Auto-size columns
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'results_export_') . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);
        
        return $tempFile;
    }
}