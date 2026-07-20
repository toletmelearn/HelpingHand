<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\CBSEResult;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class CBSEReportCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display CBSE report card generation dashboard
     */
    public function index()
    {
        $students = Student::with('schoolClass')->get();
        $exams = Exam::all();
        $classes = SchoolClass::all();

        return view('admin.cbse-report-cards.index', compact('students', 'exams', 'classes'));
    }

    /**
     * Generate CBSE report card for a student
     */
    public function generate($studentId, $examId)
    {
        $student = Student::with('schoolClass')->findOrFail($studentId);
        $exam = Exam::findOrFail($examId);

        // Get all results for this student in this exam
        $results = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->get();

        if ($results->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No results found for this student in the selected exam.');
        }

        // Calculate aggregate results
        $aggregateData = $this->calculateAggregateResults($results);

        // Get subject-wise grades and remarks
        $subjectDetails = $this->getSubjectDetails($results);

        // Generate rank if possible
        $rank = $this->calculateStudentRank($studentId, $examId);

        return view('admin.cbse-report-cards.generate', compact(
            'student',
            'exam',
            'results',
            'aggregateData',
            'subjectDetails',
            'rank'
        ));
    }

    /**
     * Calculate aggregate results for CBSE report card
     */
    private function calculateAggregateResults($results)
    {
        $totalObtained = $results->sum('marks_obtained');
        $totalMaximum = $results->sum('total_marks');
        $percentage = $totalMaximum > 0 ? round(($totalObtained / $totalMaximum) * 100, 2) : 0;

        // Check for compartment (any subject < 33%)
        $hasFailedSubject = $results->contains(function ($result) {
            return $result->percentage < 33;
        });

        // Check for improvement (if any subject has grade A1/A2)
        $hasHighGrade = $results->contains(function ($result) {
            return in_array($result->grade, ['A1', 'A2']);
        });

        $overallGrade = $this->calculateCBSEGrade($percentage);
        $resultStatus = $hasFailedSubject ? 'FAIL' : 'PASS';

        return [
            'total_obtained' => $totalObtained,
            'total_maximum' => $totalMaximum,
            'percentage' => $percentage,
            'overall_grade' => $overallGrade,
            'result_status' => $resultStatus,
            'has_failed_subject' => $hasFailedSubject,
            'has_high_grade' => $hasHighGrade,
            'grade_points' => $this->convertGradeToPoints($overallGrade)
        ];
    }

    /**
     * Get detailed subject information
     */
    private function getSubjectDetails($results)
    {
        $details = [];
        foreach ($results as $result) {
            $details[] = [
                'subject' => $result->subject,
                'marks_obtained' => $result->marks_obtained,
                'total_marks' => $result->total_marks,
                'percentage' => $result->percentage,
                'grade' => $result->grade,
                'grade_point' => $this->convertGradeToPoints($result->grade),
                'status' => $result->result_status,
                'is_failed' => $result->percentage < 33
            ];
        }
        return $details;
    }

    /**
     * Calculate student rank in class for the exam
     */
    private function calculateStudentRank($studentId, $examId)
    {
        $studentResult = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->first();

        if (!$studentResult) {
            return null;
        }

        // Get student's class
        $student = Student::findOrFail($studentId);
        $classStudents = Student::where('class', $student->class)->pluck('id')->toArray();

        // Get all results for students in the same class for this exam
        $classResults = Result::whereIn('student_id', $classStudents)
            ->where('exam_id', $examId)
            ->select('student_id', \DB::raw('SUM(marks_obtained) as total_marks_obtained'))
            ->groupBy('student_id')
            ->get();

        // Sort by total marks obtained (descending)
        $sortedResults = $classResults->sortByDesc('total_marks_obtained');

        // Find the rank of the current student
        $rank = $sortedResults->search(function ($item) use ($studentId) {
            return $item->student_id == $studentId;
        });

        return $rank !== false ? $rank + 1 : null;
    }

    /**
     * Calculate CBSE grade based on percentage
     */
    private function calculateCBSEGrade($percentage)
    {
        if ($percentage >= 91) return 'A1';
        if ($percentage >= 81) return 'A2';
        if ($percentage >= 71) return 'B1';
        if ($percentage >= 61) return 'B2';
        if ($percentage >= 51) return 'C1';
        if ($percentage >= 41) return 'C2';
        if ($percentage >= 33) return 'D';
        return 'E';
    }

    /**
     * Convert grade to grade points
     */
    private function convertGradeToPoints($grade)
    {
        $gradePoints = [
            'A1' => 10, 'A2' => 9, 'B1' => 8, 'B2' => 7,
            'C1' => 6, 'C2' => 5, 'D' => 4, 'E' => 3, 'F' => 2
        ];

        return $gradePoints[$grade] ?? 0;
    }

    /**
     * Print CBSE report card
     */
    public function print($studentId, $examId)
    {
        $student = Student::with('schoolClass')->findOrFail($studentId);
        $exam = Exam::findOrFail($examId);

        $results = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->get();

        if ($results->isEmpty()) {
            abort(404, 'No results found for this student in the selected exam.');
        }

        $aggregateData = $this->calculateAggregateResults($results);
        $subjectDetails = $this->getSubjectDetails($results);
        $rank = $this->calculateStudentRank($studentId, $examId);

        return view('admin.cbse-report-cards.print', compact(
            'student',
            'exam',
            'results',
            'aggregateData',
            'subjectDetails',
            'rank'
        ));
    }

    /**
     * Download CBSE report card as PDF
     */
    public function downloadPdf($studentId, $examId)
    {
        $student = Student::with('schoolClass')->findOrFail($studentId);
        $exam = Exam::findOrFail($examId);

        $results = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->get();

        if ($results->isEmpty()) {
            abort(404, 'No results found for this student in the selected exam.');
        }

        $aggregateData = $this->calculateAggregateResults($results);
        $subjectDetails = $this->getSubjectDetails($results);
        $rank = $this->calculateStudentRank($studentId, $examId);

        $pdf = Pdf::loadView('admin.cbse-report-cards.pdf', compact(
            'student',
            'exam',
            'results',
            'aggregateData',
            'subjectDetails',
            'rank'
        ));

        return $pdf->download("CBSE_Report_Card_{$student->name}_{$exam->name}.pdf");
    }

    /**
     * Generate batch report cards for a class
     */
    public function batchGenerate(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'exam_id' => 'required|exists:exams,id'
        ]);

        $classId = $request->class_id;
        $examId = $request->exam_id;

        // Get all students in the class
        $students = Student::where('school_class_id', $classId)->get();

        $batchResults = [];

        foreach ($students as $student) {
            $results = Result::where('student_id', $student->id)
                ->where('exam_id', $examId)
                ->get();

            if (!$results->isEmpty()) {
                $aggregateData = $this->calculateAggregateResults($results);
                $rank = $this->calculateStudentRank($student->id, $examId);

                $batchResults[] = [
                    'student' => $student,
                    'aggregate' => $aggregateData,
                    'rank' => $rank,
                    'has_results' => true
                ];
            } else {
                $batchResults[] = [
                    'student' => $student,
                    'aggregate' => null,
                    'rank' => null,
                    'has_results' => false
                ];
            }
        }

        $exam = Exam::findOrFail($examId);
        $class = SchoolClass::findOrFail($classId);

        return view('admin.cbse-report-cards.batch-results', compact(
            'batchResults',
            'exam',
            'class'
        ));
    }

    /**
     * Get class toppers for an exam
     */
    public function classToppers($examId)
    {
        $exam = Exam::findOrFail($examId);

        // Get all results for this exam grouped by student
        $studentResults = Result::where('exam_id', $examId)
            ->select('student_id', \DB::raw('SUM(marks_obtained) as total_marks_obtained, AVG(percentage) as average_percentage'))
            ->groupBy('student_id')
            ->orderByDesc('total_marks_obtained')
            ->limit(10) // Top 10
            ->get();

        $toppers = [];
        foreach ($studentResults as $result) {
            $student = Student::find($result->student_id);
            if ($student) {
                $toppers[] = [
                    'student' => $student,
                    'total_marks' => $result->total_marks_obtained,
                    'average_percentage' => round($result->average_percentage, 2),
                    'rank' => count($toppers) + 1
                ];
            }
        }

        return view('admin.cbse-report-cards.toppers', compact('toppers', 'exam'));
    }

    /**
     * Get subject-wise toppers
     */
    public function subjectToppers($examId)
    {
        $exam = Exam::findOrFail($examId);

        // Get all unique subjects in this exam
        $subjects = Result::where('exam_id', $examId)
            ->distinct('subject')
            ->pluck('subject');

        $subjectToppers = [];
        foreach ($subjects as $subject) {
            $topperResult = Result::where('exam_id', $examId)
                ->where('subject', $subject)
                ->orderByDesc('percentage')
                ->first();

            if ($topperResult) {
                $student = Student::find($topperResult->student_id);
                if ($student) {
                    $subjectToppers[] = [
                        'subject' => $subject,
                        'student' => $student,
                        'marks_obtained' => $topperResult->marks_obtained,
                        'percentage' => $topperResult->percentage,
                        'grade' => $topperResult->grade
                    ];
                }
            }
        }

        return view('admin.cbse-report-cards.subject-toppers', compact('subjectToppers', 'exam'));
    }

    /**
     * Analyze exam results
     */
    public function analyzeResults($examId)
    {
        $exam = Exam::findOrFail($examId);

        // Overall statistics
        $totalStudents = Result::where('exam_id', $examId)
            ->distinct('student_id')
            ->count();

        $totalResults = Result::where('exam_id', $examId)->count();

        $passCount = Result::where('exam_id', $examId)
            ->where('result_status', 'pass')
            ->distinct('student_id')
            ->count();

        $failCount = $totalStudents - $passCount;

        $passPercentage = $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 2) : 0;

        // Average marks
        $avgPercentage = Result::where('exam_id', $examId)
            ->avg('percentage') ?? 0;

        // Grade distribution
        $gradeDistribution = Result::where('exam_id', $examId)
            ->select('grade', \DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->get()
            ->pluck('count', 'grade');

        // Subject-wise analysis
        $subjectAnalysis = Result::where('exam_id', $examId)
            ->select('subject', 
                \DB::raw('AVG(percentage) as avg_percentage'),
                \DB::raw('MIN(percentage) as min_percentage'),
                \DB::raw('MAX(percentage) as max_percentage'),
                \DB::raw('COUNT(*) as total_students')
            )
            ->groupBy('subject')
            ->get();

        return view('admin.cbse-report-cards.analysis', compact(
            'exam',
            'totalStudents',
            'totalResults',
            'passCount',
            'failCount',
            'passPercentage',
            'avgPercentage',
            'gradeDistribution',
            'subjectAnalysis'
        ));
    }
}