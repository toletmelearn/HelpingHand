<?php

namespace App\Services;

use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\ResultFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProfessionalResultService
{
    /**
     * Generate class rankings for an exam
     */
    public function generateClassRankings(int $examId): void
    {
        $results = Result::where('exam_id', $examId)
            ->orderBy('percentage', 'desc')
            ->get()
            ->groupBy('student.class_name');
            
        foreach ($results as $className => $classResults) {
            $rank = 1;
            $previousPercentage = null;
            $sameRankCount = 0;
            
            foreach ($classResults as $result) {
                // Handle ties - same rank for same percentages
                if ($previousPercentage !== null && $result->percentage == $previousPercentage) {
                    $sameRankCount++;
                } else {
                    $rank += $sameRankCount;
                    $sameRankCount = 0;
                    $previousPercentage = $result->percentage;
                }
                
                $result->class_rank = $rank;
                $result->save();
            }
        }
    }
    
    /**
     * Generate section rankings for an exam
     */
    public function generateSectionRankings(int $examId): void
    {
        $results = Result::where('exam_id', $examId)
            ->orderBy('percentage', 'desc')
            ->get()
            ->groupBy(['student.class_name', 'student.section']);
            
        foreach ($results as $className => $sectionResults) {
            foreach ($sectionResults as $sectionName => $resultsInSection) {
                $rank = 1;
                $previousPercentage = null;
                $sameRankCount = 0;
                
                foreach ($resultsInSection as $result) {
                    // Handle ties - same rank for same percentages
                    if ($previousPercentage !== null && $result->percentage == $previousPercentage) {
                        $sameRankCount++;
                    } else {
                        $rank += $sameRankCount;
                        $sameRankCount = 0;
                        $previousPercentage = $result->percentage;
                    }
                    
                    $result->section_rank = $rank;
                    $result->save();
                }
            }
        }
    }
    
    /**
     * Generate complete rankings for an exam
     */
    public function generateCompleteRankings(int $examId): void
    {
        $this->generateClassRankings($examId);
        $this->generateSectionRankings($examId);
    }
    
    /**
     * Get student's overall result for an exam (all subjects)
     */
    public function getStudentOverallResult(int $studentId, int $examId): array
    {
        $results = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->get();
            
        if ($results->isEmpty()) {
            return [];
        }
        
        $totalObtained = $results->sum('marks_obtained');
        $totalMarks = $results->sum('total_marks');
        $overallPercentage = $totalMarks > 0 ? round(($totalObtained / $totalMarks) * 100, 2) : 0;
        
        // Determine overall grade
        $overallGrade = $this->calculateGrade($overallPercentage);
        
        // Check if student failed in any subject
        $hasFailedSubject = $results->contains('result_status', 'fail');
        $finalResult = $hasFailedSubject ? 'FAIL' : 'PASS';
        
        return [
            'student_id' => $studentId,
            'exam_id' => $examId,
            'student_name' => $results->first()->student->name ?? '',
            'class_name' => $results->first()->student->class_name ?? '',
            'section' => $results->first()->student->section ?? '',
            'subjects' => $results->map(function($result) {
                return $result->getReportCardData();
            }),
            'total_obtained' => $totalObtained,
            'total_marks' => $totalMarks,
            'overall_percentage' => $overallPercentage,
            'overall_grade' => $overallGrade,
            'final_result' => $finalResult,
            'class_rank' => $this->getClassRank($studentId, $examId),
            'section_rank' => $this->getSectionRank($studentId, $examId),
        ];
    }
    
    /**
     * Get class toppers for an exam
     */
    public function getClassToppers(int $examId, int $limit = 10): array
    {
        $topResults = Result::select('student_id', DB::raw('AVG(percentage) as avg_percentage'))
            ->where('exam_id', $examId)
            ->groupBy('student_id')
            ->orderBy('avg_percentage', 'desc')
            ->limit($limit)
            ->get();
            
        return $topResults->map(function($result) {
            $student = Student::find($result->student_id);
            return [
                'student_id' => $result->student_id,
                'student_name' => $student->name ?? '',
                'class_name' => $student->class_name ?? '',
                'average_percentage' => round($result->avg_percentage, 2),
            ];
        })->toArray();
    }
    
    /**
     * Calculate grade based on percentage (CBSE style)
     */
    public function calculateGrade(float $percentage): string
    {
        if ($percentage >= 90) {
            return 'A1';
        } elseif ($percentage >= 80) {
            return 'A2';
        } elseif ($percentage >= 70) {
            return 'B1';
        } elseif ($percentage >= 60) {
            return 'B2';
        } elseif ($percentage >= 50) {
            return 'C';
        } elseif ($percentage >= 40) {
            return 'D';
        } else {
            return 'F';
        }
    }
    
    /**
     * Get class rank for a student in an exam
     */
    private function getClassRank(int $studentId, int $examId): ?int
    {
        $result = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->first();
            
        return $result ? $result->class_rank : null;
    }
    
    /**
     * Get section rank for a student in an exam
     */
    private function getSectionRank(int $studentId, int $examId): ?int
    {
        $result = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->first();
            
        return $result ? $result->section_rank : null;
    }
    
    /**
     * Lock all results for an exam
     */
    public function lockExamResults(int $examId): void
    {
        Result::where('exam_id', $examId)->update([
            'is_locked' => true,
            'generated_by' => Auth::id(),
            'generated_at' => now()
        ]);
    }
    
    /**
     * Unlock all results for an exam
     */
    public function unlockExamResults(int $examId): void
    {
        Result::where('exam_id', $examId)->update([
            'is_locked' => false
        ]);
    }
    
    /**
     * Get exam statistics
     */
    public function getExamStatistics(int $examId): array
    {
        $totalStudents = Result::where('exam_id', $examId)->distinct('student_id')->count('student_id');
        $passedStudents = Result::where('exam_id', $examId)->where('result_status', 'pass')->distinct('student_id')->count('student_id');
        $failedStudents = $totalStudents - $passedStudents;
        
        $passPercentage = $totalStudents > 0 ? round(($passedStudents / $totalStudents) * 100, 2) : 0;
        $failPercentage = $totalStudents > 0 ? round(($failedStudents / $totalStudents) * 100, 2) : 0;
        
        $averagePercentage = Result::where('exam_id', $examId)->avg('percentage');
        
        return [
            'total_students' => $totalStudents,
            'passed_students' => $passedStudents,
            'failed_students' => $failedStudents,
            'pass_percentage' => $passPercentage,
            'fail_percentage' => $failPercentage,
            'average_percentage' => round($averagePercentage ?? 0, 2),
        ];
    }
    
    /**
     * Generate professional result format HTML using template
     */
    public function generateProfessionalResultFormat(int $studentId, int $examId): string
    {
        $reportCardData = $this->getStudentOverallResult($studentId, $examId);
        
        if (empty($reportCardData)) {
            return '<div class="alert alert-danger">No results found for this student.</div>';
        }
        
        // Professional result format template
        $template = '<div style="width: 800px; margin: auto; font-family: Arial, sans-serif; border: 2px solid #000; padding: 20px;">

    <h2 style="text-align:center; margin:0;">HelpingHand School ERP</h2>

    <h3 style="text-align:center; margin:5px 0;">Student Result Card</h3>

    <hr>

    <div style="text-align:right;">
        <img src="{student_photo}" style="width:70px;height:80px;object-fit:cover;border:1px solid #000;">
    </div>

    <table style="width:100%; margin-bottom:15px;">
        <tr>
            <td><b>Student Name:</b> {student_name}</td>
            <td><b>Class:</b> {class}</td>
        </tr>
        <tr>
            <td><b>Roll No:</b> {roll_no}</td>
            <td><b>Exam:</b> {exam_name}</td>
        </tr>
        <tr>
            <td><b>Session:</b> {academic_year}</td>
            <td><b>Date:</b> {generated_date}</td>
        </tr>
    </table>

    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; border-collapse:collapse; text-align:center;">
        <thead style="background:#f2f2f2;">
            <tr>
                <th>Subject</th>
                <th>Max Marks</th>
                <th>Marks Obtained</th>
                <th>Grade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            {marks_rows}
        </tbody>
        <tfoot>
            <tr style="font-weight:bold;">
                <td>Total</td>
                <td>{total_max_marks}</td>
                <td>{total_obtained}</td>
                <td colspan="2">{overall_grade}</td>
            </tr>
            <tr style="font-weight:bold;">
                <td colspan="5">Percentage: {percentage}%</td>
            </tr>
            <tr style="font-weight:bold;">
                <td colspan="5">Final Result: {final_result}</td>
            </tr>
        </tfoot>
    </table>

    <br><br>

    <table style="width:100%; margin-top:40px;">
        <tr>
            <td style="text-align:left;">
                ___________________<br>
                Class Teacher
            </td>
            <td style="text-align:right;">
                ___________________<br>
                Principal
            </td>
        </tr>
    </table>

</div>';
        
        // Generate subject rows
        $marksRows = '';
        foreach ($reportCardData['subjects'] as $subjectResult) {
            $marksRows .= '<tr>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['subject']) . '</td>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['total_marks']) . '</td>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['marks_obtained']) . '</td>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['grade']) . '</td>';
            $marksRows .= '<td>' . htmlspecialchars(strtoupper($subjectResult['result_status'])) . '</td>';
            $marksRows .= '</tr>';
        }
        
        // Get exam name
        $exam = Exam::find($examId);
        $examName = $exam ? $exam->name : 'N/A';
        $student = Student::find($studentId);

        // Replace placeholders
        $replacements = [
            '{student_photo}' => $student ? $student->photo_url : asset('images/default-avatar.png'),
            '{student_name}' => htmlspecialchars($reportCardData['student_name']),
            '{class}' => htmlspecialchars($reportCardData['class_name'] . ' - ' . $reportCardData['section']),
            '{roll_no}' => htmlspecialchars($reportCardData['student_id']),
            '{exam_name}' => htmlspecialchars($examName),
            '{academic_year}' => date('Y') . '-' . (date('Y') + 1),
            '{generated_date}' => date('d/m/Y'),
            '{marks_rows}' => $marksRows,
            '{total_max_marks}' => htmlspecialchars($reportCardData['total_marks']),
            '{total_obtained}' => htmlspecialchars($reportCardData['total_obtained']),
            '{percentage}' => htmlspecialchars($reportCardData['overall_percentage']),
            '{overall_grade}' => htmlspecialchars($reportCardData['overall_grade']),
            '{final_result}' => htmlspecialchars($reportCardData['final_result']),
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Generate result using the default result format template from database
     */
    public function generateResultFromTemplate(int $studentId, int $examId): string
    {
        $reportCardData = $this->getStudentOverallResult($studentId, $examId);
        
        if (empty($reportCardData)) {
            return '<div class="alert alert-danger">No results found for this student.</div>';
        }
        
        // Get student details
        $student = Student::find($studentId);
        if (!$student) {
            return '<div class="alert alert-danger">Student not found.</div>';
        }
        
        // Get default result format
        $resultFormat = ResultFormat::where('is_default', 1)
            ->where('is_active', 1)
            ->first();
            
        if (!$resultFormat) {
            // Fallback to any active result format
            $resultFormat = ResultFormat::where('is_active', 1)->first();
        }
        
        if (!$resultFormat) {
            return '<div class="alert alert-danger">No result format template found. Please create one in Result Formats.</div>';
        }
        
        // Use the template from database
        $template = $resultFormat->template_html;
        
        // Get exam details
        $exam = Exam::find($examId);
        $examName = $exam ? $exam->name : 'N/A';
        $examTerm = $exam ? ($exam->term ?? 'Term 1') : 'Term 1';
        
        // Generate all subject rows for the marks table
        $allSubjectRows = '';
        foreach ($reportCardData['subjects'] as $subjectResult) {
            $subjectPercentage = $subjectResult['total_marks'] > 0 
                ? round(($subjectResult['marks_obtained'] / $subjectResult['total_marks']) * 100, 2) 
                : 0;
            
            $allSubjectRows .= '<tr>';
            $allSubjectRows .= '<td>' . htmlspecialchars($subjectResult['subject']) . '</td>';
            $allSubjectRows .= '<td style="text-align:center;">' . htmlspecialchars($subjectResult['marks_obtained']) . '</td>';
            $allSubjectRows .= '<td style="text-align:center;">' . htmlspecialchars($subjectResult['total_marks']) . '</td>';
            $allSubjectRows .= '<td style="text-align:center;">' . $subjectPercentage . '%</td>';
            $allSubjectRows .= '<td style="text-align:center;">' . htmlspecialchars($subjectResult['grade']) . '</td>';
            $allSubjectRows .= '<td style="text-align:center;">' . htmlspecialchars(strtoupper($subjectResult['result_status'])) . '</td>';
            $allSubjectRows .= '</tr>';
        }
        
        // Get rank info
        $classRank = $reportCardData['class_rank'] ?? '-';
        $sectionRank = $reportCardData['section_rank'] ?? '-';
        $rankDisplay = $classRank ? $classRank . ' (Class)' : '-';
        
        // Determine remarks based on percentage
        $percentage = $reportCardData['overall_percentage'];
        if ($percentage >= 90) {
            $remarks = 'Outstanding performance! Keep up the excellent work.';
        } elseif ($percentage >= 75) {
            $remarks = 'Very good performance. Continue your hard work.';
        } elseif ($percentage >= 60) {
            $remarks = 'Good performance. There is room for improvement.';
        } elseif ($percentage >= 40) {
            $remarks = 'Satisfactory performance. Need to work harder.';
        } else {
            $remarks = 'Needs improvement. Please focus on studies.';
        }
        
        // Replace all placeholders with actual data
        $replacements = [
            // Student Info
            '{student_name}' => htmlspecialchars($reportCardData['student_name']),
            '{class}' => htmlspecialchars($reportCardData['class_name']),
            '{section}' => htmlspecialchars($reportCardData['section']),
            '{roll_number}' => htmlspecialchars($student->roll_number ?? $studentId),
            '{roll_no}' => htmlspecialchars($student->roll_number ?? $studentId),
            '{admission_no}' => htmlspecialchars($student->admission_number ?? 'ADM' . str_pad($studentId, 4, '0', STR_PAD_LEFT)),
            '{father_name}' => htmlspecialchars($student->father_name ?? 'N/A'),
            '{dob}' => $student->date_of_birth ? date('d/m/Y', strtotime($student->date_of_birth)) : 'N/A',
            
            // Exam Info
            '{exam_name}' => htmlspecialchars($examName),
            '{exam}' => htmlspecialchars($examName),
            '{term}' => htmlspecialchars($examTerm),
            '{academic_year}' => date('Y') . '-' . (date('Y') + 1),
            '{generated_date}' => date('d/m/Y'),
            
            // Subject Rows (multiple placeholders for compatibility)
            '{all_subject_rows}' => $allSubjectRows,
            '{marks_rows}' => $allSubjectRows,
            '{subject_rows}' => $allSubjectRows,
            
            // Totals and Results
            '{total_obtained}' => htmlspecialchars($reportCardData['total_obtained']),
            '{total_marks}' => htmlspecialchars($reportCardData['total_marks']),
            '{overall_percentage}' => htmlspecialchars($reportCardData['overall_percentage']),
            '{percentage}' => htmlspecialchars($reportCardData['overall_percentage']),
            '{overall_grade}' => htmlspecialchars($reportCardData['overall_grade']),
            '{grade}' => htmlspecialchars($reportCardData['overall_grade']),
            '{final_result}' => htmlspecialchars($reportCardData['final_result']),
            '{result_status}' => htmlspecialchars($reportCardData['final_result']),
            
            // Rankings
            '{rank}' => htmlspecialchars($rankDisplay),
            '{class_rank}' => htmlspecialchars($classRank),
            '{section_rank}' => htmlspecialchars($sectionRank),
            
            // School Info
            '{school_name}' => htmlspecialchars(config('app.name', 'HelpingHand School')),
            '{school_address}' => 'School Address',
            
            // Remarks
            '{remarks}' => htmlspecialchars($remarks),
            '{teacher_remark}' => htmlspecialchars($remarks),
            '{comments}' => htmlspecialchars($remarks),
        ];
        
        // Replace all placeholders
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Generate CBSE-style professional result format HTML using template
     */
    public function generateCBSEProfessionalResultFormat(int $studentId, int $examId): string
    {
        $reportCardData = $this->getStudentOverallResult($studentId, $examId);
        
        if (empty($reportCardData)) {
            return '<div class="alert alert-danger">No results found for this student.</div>';
        }
        
        // Get student details
        $student = Student::find($studentId);
        if (!$student) {
            return '<div class="alert alert-danger">Student not found.</div>';
        }
        
        // CBSE-style professional result format template
        $template = '<div style="width:900px;margin:auto;font-family:Arial;border:1px solid #000;padding:15px;">

    <!-- SCHOOL HEADER -->
    <table style="width:100%;">
        <tr>
            <td style="width:80px;">
                <img src="{school_logo}" style="width:70px;">
            </td>
            <td style="text-align:center;">
                <h2 style="margin:0;">{school_name}</h2>
                <div>{school_address}</div>
                <div><b>Academic Session:</b> {academic_year}</div>
                <h3 style="margin:5px 0;">Academic Report</h3>
            </td>
            <td style="width:80px;text-align:right;">
                <img src="{student_photo}" style="width:70px;height:80px;border:1px solid #000;">
            </td>
        </tr>
    </table>

    <hr>

    <!-- STUDENT DETAILS -->
    <table style="width:100%;margin-top:10px;font-size:14px;">
        <tr>
            <td><b>Student Name:</b> {student_name}</td>
            <td><b>Class:</b> {class}</td>
        </tr>
        <tr>
            <td><b>Father\'s Name:</b> {father_name}</td>
            <td><b>Roll No:</b> {roll_no}</td>
        </tr>
        <tr>
            <td><b>Admission No:</b> {admission_no}</td>
            <td><b>Date of Birth:</b> {dob}</td>
        </tr>
    </table>

    <br>

    <!-- MARKS TABLE -->
    <table border="1" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;text-align:center;">
        <thead style="background:#eaeaea;">
            <tr>
                <th rowspan="2">Subject</th>
                <th colspan="3">Term 1</th>
                <th rowspan="2">Total</th>
                <th rowspan="2">Grade</th>
            </tr>
            <tr>
                <th>PT</th>
                <th>Notebook</th>
                <th>Exam</th>
            </tr>
        </thead>

        <tbody>
            {marks_rows}
        </tbody>

        <tfoot>
            <tr style="font-weight:bold;background:#f5f5f5;">
                <td>Grand Total</td>
                <td colspan="3"></td>
                <td>{total_marks}</td>
                <td>{overall_grade}</td>
            </tr>
            <tr>
                <td colspan="6"><b>Percentage:</b> {percentage}%</td>
            </tr>
            <tr>
                <td colspan="6"><b>Result:</b> {final_result}</td>
            </tr>
        </tfoot>
    </table>

    <br>

    <!-- CO SCHOLASTIC -->
    <table border="1" cellspacing="0" cellpadding="6" style="width:100%;border-collapse:collapse;text-align:center;">
        <tr style="background:#eaeaea;">
            <th>Co-Scholastic Area</th>
            <th>Grade</th>
        </tr>
        {coscholastic_rows}
    </table>

    <br>

    <!-- REMARKS -->
    <table style="width:100%;margin-top:10px;">
        <tr>
            <td><b>Class Teacher Remarks:</b> {remarks}</td>
        </tr>
    </table>

    <br><br>

    <!-- SIGN -->
    <table style="width:100%;margin-top:40px;text-align:center;">
        <tr>
            <td>____________________<br>Class Teacher</td>
            <td>____________________<br>Principal</td>
        </tr>
    </table>

</div>';
        
        // Generate subject rows (CBSE format with PT, Notebook, Exam columns)
        $marksRows = '';
        foreach ($reportCardData['subjects'] as $subjectResult) {
            // For CBSE format, we'll distribute the total marks across PT, Notebook, and Exam
            // This is a simplified distribution - in real scenario, these would come from separate assessments
            $totalMarks = (int)$subjectResult['total_marks'];
            $marksObtained = (int)$subjectResult['marks_obtained'];
            
            // Distribute marks: 20% PT, 10% Notebook, 70% Exam (typical CBSE pattern)
            $ptMax = round($totalMarks * 0.2);
            $notebookMax = round($totalMarks * 0.1);
            $examMax = $totalMarks - $ptMax - $notebookMax;
            
            $ptObtained = round($marksObtained * 0.2);
            $notebookObtained = round($marksObtained * 0.1);
            $examObtained = $marksObtained - $ptObtained - $notebookObtained;
            
            $marksRows .= '<tr>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['subject']) . '</td>';
            $marksRows .= '<td>' . $ptObtained . '</td>';
            $marksRows .= '<td>' . $notebookObtained . '</td>';
            $marksRows .= '<td>' . $examObtained . '</td>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['marks_obtained']) . '</td>';
            $marksRows .= '<td>' . htmlspecialchars($subjectResult['grade']) . '</td>';
            $marksRows .= '</tr>';
        }
        
        // Generate co-scholastic rows (sample data)
        $coScholasticRows = '';
        $coScholasticAreas = [
            'Work Education' => 'A1',
            'Art Education' => 'A2',
            'Health & Physical Education' => 'A1',
            'Discipline' => 'A1',
            'Regularity & Promptness' => 'A2'
        ];
        
        foreach ($coScholasticAreas as $area => $grade) {
            $coScholasticRows .= '<tr>';
            $coScholasticRows .= '<td>' . htmlspecialchars($area) . '</td>';
            $coScholasticRows .= '<td>' . htmlspecialchars($grade) . '</td>';
            $coScholasticRows .= '</tr>';
        }
        
        // Get exam name
        $exam = Exam::find($examId);
        $examName = $exam ? $exam->name : 'N/A';
        
        // Replace placeholders
        $replacements = [
            '{school_name}' => htmlspecialchars(config('app.name', 'HelpingHand School')),
            '{school_logo}' => asset('images/school-logo.png'), // Placeholder - update with actual logo path
            '{school_address}' => 'Sector-12, Dwarka, New Delhi - 110075',
            '{academic_year}' => date('Y') . '-' . (date('Y') + 1),
            '{student_photo}' => $student->photo_url,
            '{student_name}' => htmlspecialchars($reportCardData['student_name']),
            '{father_name}' => htmlspecialchars($student->father_name ?? 'N/A'),
            '{class}' => htmlspecialchars($reportCardData['class_name'] . ' - ' . $reportCardData['section']),
            '{roll_no}' => htmlspecialchars($reportCardData['student_id']),
            '{admission_no}' => htmlspecialchars($student->admission_number ?? 'ADM' . str_pad($studentId, 4, '0', STR_PAD_LEFT)),
            '{dob}' => $student->date_of_birth ? date('d/m/Y', strtotime($student->date_of_birth)) : 'N/A',
            '{marks_rows}' => $marksRows,
            '{coscholastic_rows}' => $coScholasticRows,
            '{total_marks}' => htmlspecialchars($reportCardData['total_obtained']),
            '{percentage}' => htmlspecialchars($reportCardData['overall_percentage']),
            '{overall_grade}' => htmlspecialchars($reportCardData['overall_grade']),
            '{final_result}' => htmlspecialchars($reportCardData['final_result']),
            '{remarks}' => htmlspecialchars('Outstanding performance. Keep up the excellent work!'),
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $template = str_replace($placeholder, $value, $template);
        }
        
        return $template;
    }
}