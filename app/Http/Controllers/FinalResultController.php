<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class FinalResultController extends Controller
{
    /**
     * Generate final result for a student in an exam
     */
    public function generate($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $student = Student::with(['schoolClass', 'schoolSection'])->findOrFail($studentId);
        $exam = Exam::findOrFail($examId);
        
        // Get all verified results for this student in this exam
        $results = Result::where([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'is_verified' => true
        ])->with('exam')->orderBy('subject')->get();
        
        if ($results->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No verified results found for this student in the selected exam.');
        }
        
        // Calculate overall statistics
        $totalObtained = $results->sum('marks_obtained');
        $totalMarks = $results->sum('total_marks');
        $overallPercentage = $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0;
        $overallGrade = $this->calculateGrade($overallPercentage);
        
        // Determine final result status
        $anyFail = $results->contains('result_status', 'fail');
        $finalResult = $anyFail ? 'FAIL' : 'PASS';
        
        // Prepare data for template
        $data = [
            'student_name' => $student->name,
            'father_name' => $student->father_name,
            'class' => $student->schoolClass->name ?? $student->class ?? 'N/A',
            'section' => $student->schoolSection->name ?? $student->section ?? '',
            'roll_no' => $student->roll_number,
            'admission_no' => $student->admission_number,
            'dob' => $student->date_of_birth->format('d/m/Y'),
            'student_photo' => $student->photo ? asset('storage/' . $student->photo) : asset('images/default-avatar.png'),
            'school_logo' => asset('images/school-logo.png'),
            'school_name' => 'HelpingHand Public School',
            'school_address' => 'Sector-12, Chandigarh',
            'affiliation_no' => '1630012',
            'exam_name' => $exam->name,
            'academic_year' => $results->first()->academic_year ?? '2025-26',
            'term' => $results->first()->term ?? 'Annual',
            'total_obtained' => $totalObtained,
            'total_marks' => $totalMarks,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
            'final_result' => $finalResult,
            'attendance' => '245', // This should come from attendance system
            'working_days' => '260', // This should come from attendance system
            'remarks' => 'Good performance. Keep it up!',
            'results_table' => $this->generateResultsTable($results),
            'generated_by' => Auth::user()->name,
            'generated_date' => now()->format('d/m/Y'),
        ];
        
        return view('results.final-result', $data);
    }

    /**
     * Download PDF version of final result
     */
    public function downloadPdf($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $student = Student::with(['schoolClass', 'schoolSection'])->findOrFail($studentId);
        $exam = Exam::findOrFail($examId);
        
        // Get all verified results
        $results = Result::where([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'is_verified' => true
        ])->with('exam')->orderBy('subject')->get();
        
        if ($results->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No verified results found for this student in the selected exam.');
        }
        
        // Calculate overall statistics
        $totalObtained = $results->sum('marks_obtained');
        $totalMarks = $results->sum('total_marks');
        $overallPercentage = $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0;
        $overallGrade = $this->calculateGrade($overallPercentage);
        $anyFail = $results->contains('result_status', 'fail');
        $finalResult = $anyFail ? 'FAIL' : 'PASS';
        
        // Prepare data for template
        $data = [
            'student_name' => $student->name,
            'father_name' => $student->father_name,
            'class' => $student->schoolClass->name ?? $student->class ?? 'N/A',
            'section' => $student->schoolSection->name ?? $student->section ?? '',
            'roll_no' => $student->roll_number,
            'admission_no' => $student->admission_number,
            'dob' => $student->date_of_birth->format('d/m/Y'),
            'student_photo' => $student->photo ? public_path('storage/' . $student->photo) : public_path('images/default-avatar.png'),
            'school_logo' => public_path('images/school-logo.png'),
            'school_name' => 'HelpingHand Public School',
            'school_address' => 'Sector-12, Chandigarh',
            'affiliation_no' => '1630012',
            'exam_name' => $exam->name,
            'academic_year' => $results->first()->academic_year ?? '2025-26',
            'term' => $results->first()->term ?? 'Annual',
            'total_obtained' => $totalObtained,
            'total_marks' => $totalMarks,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
            'final_result' => $finalResult,
            'attendance' => '245',
            'working_days' => '260',
            'remarks' => 'Good performance. Keep it up!',
            'results_table' => $this->generateResultsTable($results),
            'generated_by' => Auth::user()->name,
            'generated_date' => now()->format('d/m/Y'),
        ];
        
        $pdf = Pdf::loadView('results.final-result-pdf', $data);
        $fileName = 'Final_Result_' . $student->name . '_' . $exam->name . '.pdf';
        
        return $pdf->download($fileName);
    }

    /**
     * Print final result
     */
    public function print($studentId, $examId)
    {
        return $this->generate($studentId, $examId);
    }

    /**
     * Generate results table HTML
     */
    private function generateResultsTable($results)
    {
        $html = '<table style="width:100%;border-collapse:collapse;border:1px solid #000;margin:20px 0;">';
        $html .= '<thead>';
        $html .= '<tr style="background:#e6e6e6;">';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Subject</th>';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Marks Obtained</th>';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Total Marks</th>';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Percentage</th>';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Grade</th>';
        $html .= '<th style="border:1px solid #000;padding:8px;text-align:center;">Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($results as $result) {
            $statusColor = $result->result_status === 'pass' ? '#27ae60' : '#e74c3c';
            $html .= '<tr>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:left;">' . $result->subject . '</td>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $result->marks_obtained . '</td>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $result->total_marks . '</td>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $result->percentage . '%</td>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $result->grade . '</td>';
            $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;color:' . $statusColor . ';font-weight:bold;">' . strtoupper($result->result_status) . '</td>';
            $html .= '</tr>';
        }
        
        // Summary row
        $html .= '<tr style="background:#f2f2f2;font-weight:bold;">';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">TOTAL</td>';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $results->sum('marks_obtained') . '</td>';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $results->sum('total_marks') . '</td>';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . round(($results->sum('marks_obtained') / $results->sum('total_marks')) * 100, 2) . '%</td>';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . $this->calculateGrade(round(($results->sum('marks_obtained') / $results->sum('total_marks')) * 100, 2)) . '</td>';
        $html .= '<td style="border:1px solid #000;padding:8px;text-align:center;">' . ($results->contains('result_status', 'fail') ? 'FAIL' : 'PASS') . '</td>';
        $html .= '</tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Calculate grade based on percentage (CBSE system)
     */
    private function calculateGrade($percentage)
    {
        if ($percentage >= 91) return 'A1';
        if ($percentage >= 81) return 'A2';
        if ($percentage >= 71) return 'B1';
        if ($percentage >= 61) return 'B2';
        if ($percentage >= 51) return 'C1';
        if ($percentage >= 41) return 'C2';
        if ($percentage >= 33) return 'D';
        return 'F';
    }
}