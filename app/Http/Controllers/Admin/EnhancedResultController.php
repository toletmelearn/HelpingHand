<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OptimizedResultService;
use App\Services\AccessibilityService;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EnhancedResultController extends Controller
{
    protected $resultService;
    protected $accessibilityService;

    public function __construct(
        OptimizedResultService $resultService,
        AccessibilityService $accessibilityService
    ) {
        $this->resultService = $resultService;
        $this->accessibilityService = $accessibilityService;
    }

    /**
     * Display results dashboard with performance optimizations
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $perPage = $request->get('per_page', 50);
        $examId = $request->get('exam_id');
        $classId = $request->get('class_id');
        $search = $request->get('search');

        // Optimize query with eager loading and caching
        $query = Result::with([
            'student:id,name,roll_number,class_id',
            'student.schoolClass:id,class_name,section',
            'exam:id,name,subject_id',
            'subject:id,name',
            'uploadedByTeacher:id,name'
        ])->latest();

        // Apply filters
        if ($examId) {
            $query->where('exam_id', $examId);
        }

        if ($classId) {
            $query->whereHas('student', function($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        if ($search) {
            $query->whereHas('student', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('roll_number', 'like', "%{$search}%");
            });
        }

        $results = $query->paginate($perPage);
        $exams = Exam::pluck('name', 'id');
        $classes = \App\Models\SchoolClass::pluck('class_name', 'id');

        // Calculate performance metrics
        $stats = $examId ? $this->resultService->getExamStatistics($examId) : null;

        return view('admin.results.enhanced-index', compact(
            'results', 'exams', 'classes', 'stats', 'perPage', 'search'
        ));
    }

    /**
     * Show optimized student result view
     */
    public function showStudentResult($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);

        $student = Student::with('schoolClass:id,class_name,section')->findOrFail($studentId);
        $exam = Exam::with('subject:id,name')->findOrFail($examId);
        
        // Get optimized results with caching
        $results = $this->resultService->getStudentResults($studentId, $examId);
        $overallResult = $this->resultService->calculateOverallResult($studentId, $examId);

        return view('admin.results.enhanced-student-result', compact(
            'student', 'exam', 'results', 'overallResult'
        ));
    }

    /**
     * Generate accessible and optimized result report
     */
    public function generateOptimizedReport(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $examId = $request->get('exam_id');
        $classId = $request->get('class_id');
        $format = $request->get('format', 'html'); // html, pdf, excel
        
        $this->validate($request, [
            'exam_id' => 'required|exists:exams,id',
            'format' => 'in:html,pdf,excel,csv'
        ]);

        // Generate data using optimized service
        $reportData = $this->generateOptimizedReportData($examId, $classId);
        
        // Add accessibility enhancements
        $reportData['accessibility'] = $this->generateAccessibilityData($reportData);

        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($reportData);
            case 'excel':
                return $this->generateExcelReport($reportData);
            case 'csv':
                return $this->generateCSVReport($reportData);
            default:
                return view('admin.reports.optimized-results', $reportData);
        }
    }

    /**
     * Generate class performance report with charts
     */
    public function generatePerformanceAnalysis($examId, $classId = null)
    {
        $this->authorize('viewAny', Result::class);

        $exam = Exam::with('subject')->findOrFail($examId);
        $chartData = $this->generateAnalysisChartData($examId, $classId);
        $statistics = $this->resultService->getExamStatistics($examId);
        $subjectPerformance = $this->resultService->getSubjectPerformance($examId);

        return view('admin.reports.performance-analysis', compact(
            'exam', 'chartData', 'statistics', 'subjectPerformance', 'classId'
        ));
    }

    /**
     * Bulk result operations with performance optimizations
     */
    public function bulkOperations(Request $request)
    {
        $this->authorize('update', Result::class);

        $this->validate($request, [
            'operation' => 'required|in:lock,unlock,recalculate,clear_cache',
            'result_ids' => 'required|array',
            'result_ids.*' => 'exists:results,id'
        ]);

        $operation = $request->operation;
        $resultIds = $request->result_ids;

        DB::beginTransaction();
        
        try {
            switch ($operation) {
                case 'lock':
                    Result::whereIn('id', $resultIds)->update(['is_locked' => true]);
                    break;
                case 'unlock':
                    Result::whereIn('id', $resultIds)->update(['is_locked' => false]);
                    break;
                case 'recalculate':
                    $this->recalculateResults($resultIds);
                    break;
                case 'clear_cache':
                    $this->clearResultCaches($resultIds);
                    break;
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => ucfirst($operation) . ' operation completed successfully'
            ]);
            
        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Operation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate subject comparison report
     */
    public function subjectComparison(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $examId = $request->get('exam_id');
        $classId = $request->get('class_id');
        $subjectIds = $request->get('subjects', []);
        
        $exam = Exam::findOrFail($examId);
        $subjects = Subject::whereIn('id', $subjectIds)->get();
        $comparisonData = $this->generateSubjectComparisonData($examId, $classId, $subjectIds);

        return view('admin.reports.subject-comparison', compact(
            'exam', 'subjects', 'comparisonData', 'classId'
        ));
    }

    /**
     * Generate trend analysis for multiple exams
     */
    public function trendAnalysis(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        $studentId = $request->get('student_id');
        $subjectId = $request->get('subject_id');
        $examIds = $request->get('exams', []);
        
        $student = Student::findOrFail($studentId);
        $subject = Subject::findOrFail($subjectId);
        $trendData = $this->generateTrendData($studentId, $subjectId, $examIds);

        return view('admin.reports.trend-analysis', compact(
            'student', 'subject', 'trendData', 'examIds'
        ));
    }

    /**
     * Generate accessibility compliant result interface
     */
    public function accessibleResultView($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);

        $student = Student::with('schoolClass')->findOrFail($studentId);
        $exam = Exam::with('subject')->findOrFail($examId);
        
        $results = $this->resultService->getStudentResults($studentId, $examId);
        $overallResult = $this->resultService->calculateOverallResult($studentId, $examId);
        
        // Generate accessible table structure
        $accessibleTable = $this->generateAccessibleResultTable($results, $overallResult);
        
        // Generate screen reader content
        $screenReaderContent = $this->generateScreenReaderResultSummary($student, $exam, $overallResult);

        return view('admin.results.accessible-result', compact(
            'student', 'exam', 'results', 'overallResult', 
            'accessibleTable', 'screenReaderContent'
        ));
    }

    /**
     * Private helper methods
     */
    private function generateOptimizedReportData($examId, $classId = null)
    {
        $exam = Exam::with('subject')->findOrFail($examId);
        
        $query = Result::with([
            'student:id,name,roll_number,class_id,father_name',
            'student.schoolClass:id,class_name,section'
        ])->where('exam_id', $examId);

        if ($classId) {
            $query->whereHas('student', function($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }

        $results = $query->orderBy('student_id')->get();
        
        // Group results by student for easier processing
        $groupedResults = $results->groupBy('student_id');
        
        return [
            'exam' => $exam,
            'class_id' => $classId,
            'results' => $results,
            'grouped_results' => $groupedResults,
            'total_students' => $groupedResults->count(),
            'generated_at' => now()
        ];
    }

    private function generateAccessibilityData($reportData)
    {
        $data = $reportData['grouped_results'];
        
        return [
            'summary' => "Report contains {$reportData['total_students']} students with results from {$reportData['exam']->name}",
            'navigation_hints' => [
                'Use tab to navigate between interactive elements',
                'Use arrow keys to navigate through data tables',
                'Screen reader users can access full data descriptions'
            ],
            'keyboard_shortcuts' => [
                'Tab' => 'Move to next focusable element',
                'Shift+Tab' => 'Move to previous focusable element',
                'Arrow Keys' => 'Navigate within data grids',
                'Enter' => 'Activate buttons and links'
            ]
        ];
    }

    private function generateAnalysisChartData($examId, $classId = null)
    {
        $subjectPerformance = $this->resultService->getSubjectPerformance($examId, $classId);
        $examStats = $this->resultService->getExamStatistics($examId);
        
        return [
            'subject_performance' => $subjectPerformance->map(function($subject) {
                return [
                    'subject' => $subject->subject->name ?? 'Unknown',
                    'average_marks' => round($subject->average_marks, 2),
                    'pass_rate' => $subject->total_students > 0 ? 
                        round(($subject->passed / $subject->total_students) * 100, 2) : 0
                ];
            }),
            'overall_stats' => [
                'pass_percentage' => $examStats['pass_percentage'],
                'average_percentage' => $examStats['average_percentage'],
                'highest_percentage' => $examStats['highest_percentage'],
                'lowest_percentage' => $examStats['lowest_percentage']
            ]
        ];
    }

    private function recalculateResults($resultIds)
    {
        $results = Result::whereIn('id', $resultIds)->get();
        
        foreach ($results as $result) {
            $percentage = $result->total_marks > 0 ? 
                round(($result->marks_obtained / $result->total_marks) * 100, 2) : 0;
            
            $grade = $this->resultService->calculateGrade($percentage);
            $status = $percentage >= 33 ? 'pass' : 'fail';
            
            $result->update([
                'percentage' => $percentage,
                'grade' => $grade,
                'result_status' => $status
            ]);
        }
    }

    private function clearResultCaches($resultIds)
    {
        $studentIds = Result::whereIn('id', $resultIds)->pluck('student_id')->unique();
        $examIds = Result::whereIn('id', $resultIds)->pluck('exam_id')->unique();
        
        foreach ($studentIds as $studentId) {
            $this->resultService->clearStudentCache($studentId);
        }
        
        foreach ($examIds as $examId) {
            $this->resultService->clearExamCache($examId);
        }
    }

    private function generateSubjectComparisonData($examId, $classId, $subjectIds)
    {
        $query = Result::with('student:id,name,roll_number')
                      ->where('exam_id', $examId)
                      ->whereIn('subject_id', $subjectIds);
        
        if ($classId) {
            $query->whereHas('student', function($q) use ($classId) {
                $q->where('class_id', $classId);
            });
        }
        
        $results = $query->get();
        
        $comparison = [];
        foreach ($results->groupBy('student_id') as $studentId => $studentResults) {
            $studentData = [
                'student' => $studentResults->first()->student,
                'subjects' => []
            ];
            
            foreach ($studentResults as $result) {
                $studentData['subjects'][$result->subject_id] = [
                    'marks' => $result->marks_obtained,
                    'percentage' => $result->percentage,
                    'grade' => $result->grade
                ];
            }
            
            $comparison[] = $studentData;
        }
        
        return $comparison;
    }

    private function generateTrendData($studentId, $subjectId, $examIds)
    {
        $results = Result::with('exam:id,name,date')
                        ->where('student_id', $studentId)
                        ->where('subject_id', $subjectId)
                        ->whereIn('exam_id', $examIds)
                        ->orderBy('exam_id')
                        ->get();
        
        return $results->map(function($result) {
            return [
                'exam' => $result->exam->name,
                'date' => $result->exam->date ?? now(),
                'marks' => $result->marks_obtained,
                'percentage' => $result->percentage,
                'grade' => $result->grade
            ];
        });
    }

    private function generateAccessibleResultTable($results, $overallResult)
    {
        $headers = ['Subject', 'Marks Obtained', 'Total Marks', 'Percentage', 'Grade', 'Status'];
        
        $data = [];
        foreach ($results as $result) {
            $statusClass = $result->result_status === 'pass' ? 'text-success' : 'text-danger';
            $data[] = [
                $result->subject->name ?? $result->subject,
                $result->marks_obtained,
                $result->total_marks,
                $result->percentage . '%',
                '<span class="badge bg-primary">' . $result->grade . '</span>',
                '<span class="' . $statusClass . '">' . strtoupper($result->result_status) . '</span>'
            ];
        }
        
        // Add overall row
        $data[] = [
            '<strong>OVERALL</strong>',
            '<strong>' . $overallResult['total_obtained'] . '</strong>',
            '<strong>' . $overallResult['total_marks'] . '</strong>',
            '<strong>' . $overallResult['overall_percentage'] . '%</strong>',
            '<span class="badge bg-success"><strong>' . $overallResult['overall_grade'] . '</strong></span>',
            '<span class="text-' . strtolower($overallResult['final_result']) . '"><strong>' . $overallResult['final_result'] . '</strong></span>'
        ];
        
        return $this->accessibilityService->generateAccessibleTable($data, $headers, 'student-results-table');
    }

    private function generateScreenReaderResultSummary($student, $exam, $overallResult)
    {
        $summary = "Student result for {$student->name} in {$exam->name} examination. ";
        $summary .= "Overall performance: {$overallResult['overall_percentage']} percent, ";
        $summary .= "Grade: {$overallResult['overall_grade']}, ";
        $summary .= "Final result: {$overallResult['final_result']}. ";
        $summary .= "Total marks obtained: {$overallResult['total_obtained']} out of {$overallResult['total_marks']}.";
        
        return $summary;
    }

    private function generatePDFReport($reportData)
    {
        // PDF generation implementation would go here
        return response()->json(['message' => 'PDF report generation functionality to be implemented']);
    }

    private function generateExcelReport($reportData)
    {
        // Excel generation implementation would go here
        return response()->json(['message' => 'Excel report generation functionality to be implemented']);
    }

    private function generateCSVReport($reportData)
    {
        // CSV generation implementation would go here
        return response()->json(['message' => 'CSV report generation functionality to be implemented']);
    }
}