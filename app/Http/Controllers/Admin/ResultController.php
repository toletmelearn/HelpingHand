<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\ClassManagement;
use App\Services\ProfessionalResultService;
use App\Services\BulkResultImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ResultController extends Controller
{
    protected $professionalResultService;
    protected $bulkImportService;
    
    public function __construct(
        ProfessionalResultService $professionalResultService,
        BulkResultImportService $bulkImportService
    )
    {
        $this->middleware('auth');
        $this->professionalResultService = $professionalResultService;
        $this->bulkImportService = $bulkImportService;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Result::class);
        // Fetch all submitted results with proper relationships
        $results = Result::with(['student', 'exam'])
            ->where('status', 'submitted')
            ->latest()
            ->paginate(15);
        return view('admin.results.index', compact('results'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Result::class);
        $exams = Exam::all();
        $students = Student::all();
        return view('admin.results.create', compact('exams', 'students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject' => 'required|string|max:100',
            'marks_obtained' => 'required|numeric|min:0|lte:total_marks',
            'total_marks' => 'required|numeric|min:1',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'comments' => 'nullable|string'
        ], [
            'marks_obtained.lte' => 'Marks obtained cannot exceed total marks',
        ]);

        // Calculate percentage
        $percentage = 0;
        if ($request->total_marks > 0) {
            $percentage = ($request->marks_obtained / $request->total_marks) * 100;
        }
        
        // Calculate grade based on percentage (CBSE style)
        if ($percentage >= 91) {
            $grade = 'A1';
        } elseif ($percentage >= 81) {
            $grade = 'A2';
        } elseif ($percentage >= 71) {
            $grade = 'B1';
        } elseif ($percentage >= 61) {
            $grade = 'B2';
        } elseif ($percentage >= 51) {
            $grade = 'C1';
        } elseif ($percentage >= 41) {
            $grade = 'C2';
        } elseif ($percentage >= 33) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }
        
        // Auto-determine result status based on passing marks
        $exam = Exam::find($request->exam_id);
        $passingMarks = $exam ? $exam->passing_marks : ($request->total_marks * 0.33);
        $resultStatus = $request->marks_obtained >= $passingMarks ? 'pass' : 'fail';

        $result = Result::create([
            'student_id' => $request->student_id,
            'exam_id' => $request->exam_id,
            'subject' => $request->subject,
            'marks_obtained' => $request->marks_obtained,
            'total_marks' => $request->total_marks,
            'percentage' => $percentage,
            'grade' => $grade,
            'academic_year' => $request->academic_year,
            'term' => $request->term,
            'result_status' => $resultStatus,
            'comments' => $request->comments,
            'uploaded_by_teacher_id' => Auth::id(), // Assuming admin is creating this
            'status' => 'submitted' // Default status
        ]);

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Result $result)
    {
        $this->authorize('view', $result);
        return view('admin.results.show', compact('result'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Result $result)
    {
        // Allow exam heads to edit results
        if (!$this->userCanEditResults()) {
            $this->authorize('update', $result);
        }
        
        $exams = Exam::all();
        $students = Student::all();
        return view('admin.results.edit', compact('result', 'exams', 'students'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Result $result)
    {
        // Allow exam heads to update results
        if (!$this->userCanEditResults()) {
            $this->authorize('update', $result);
        }
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject' => 'required|string|max:100',
            'marks_obtained' => 'required|numeric|min:0|lte:total_marks',
            'total_marks' => 'required|numeric|min:1',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'comments' => 'nullable|string'
        ], [
            'marks_obtained.lte' => 'Marks obtained cannot exceed total marks',
        ]);

        // Calculate percentage
        $percentage = 0;
        if ($request->total_marks > 0) {
            $percentage = ($request->marks_obtained / $request->total_marks) * 100;
        }
        
        // Calculate grade based on percentage (CBSE style)
        if ($percentage >= 91) {
            $grade = 'A1';
        } elseif ($percentage >= 81) {
            $grade = 'A2';
        } elseif ($percentage >= 71) {
            $grade = 'B1';
        } elseif ($percentage >= 61) {
            $grade = 'B2';
        } elseif ($percentage >= 51) {
            $grade = 'C1';
        } elseif ($percentage >= 41) {
            $grade = 'C2';
        } elseif ($percentage >= 33) {
            $grade = 'D';
        } else {
            $grade = 'F';
        }
        
        // Auto-determine result status based on passing marks
        $exam = Exam::find($request->exam_id);
        $passingMarks = $exam ? $exam->passing_marks : ($request->total_marks * 0.33);
        $resultStatus = $request->marks_obtained >= $passingMarks ? 'pass' : 'fail';
        
        // Update result with calculated percentage and grade
        $result->update([
            'student_id' => $request->student_id,
            'exam_id' => $request->exam_id,
            'subject' => $request->subject,
            'marks_obtained' => $request->marks_obtained,
            'total_marks' => $request->total_marks,
            'percentage' => $percentage,
            'grade' => $grade,
            'academic_year' => $request->academic_year,
            'term' => $request->term,
            'result_status' => $resultStatus,
            'comments' => $request->comments,
            'remarks' => $request->remarks ?? $result->remarks,
        ]);

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Result $result)
    {
        $this->authorize('delete', $result);
        
        $result->delete();

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result deleted successfully.');
    }
    
    /**
     * Check if the current user can edit results (admin or exam head)
     */
    private function userCanEditResults()
    {
        // Check if user is admin
        if (Auth::check() && Auth::user()->role === 'admin') {
            return true;
        }
        
        // Check if teacher is logged in and is an exam head
        $teacherLogin = Auth::guard('teacher')->user();
        if ($teacherLogin && $teacherLogin->teacher) {
            return $teacherLogin->teacher->isExamHead();
        }
        
        return false;
    }
    
    /**
     * Generate report card for a student using the default result format template
     */
    public function generateReportCard($studentId, $examId)
    {
        return $this->generateFinalResult($studentId, $examId);
    }

    /**
     * Generate professional result format for a student
     */
    public function generateProfessionalFormat($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $professionalFormatHtml = $this->professionalResultService->generateProfessionalResultFormat($studentId, $examId);
        
        return response($professionalFormatHtml)
            ->header('Content-Type', 'text/html');
    }
    
    /**
     * Generate CBSE-style professional result format for a student
     */
    public function generateCBSEProfessionalFormat($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $cbseFormatHtml = $this->professionalResultService->generateCBSEProfessionalResultFormat($studentId, $examId);
        
        return response($cbseFormatHtml)
            ->header('Content-Type', 'text/html');
    }
    public function generateRankings($examId)
    {
        $this->authorize('create', Result::class);
        
        $this->professionalResultService->generateCompleteRankings($examId);
        
        return redirect()->back()->with('success', 'Rankings generated successfully.');
    }
    
    /**
     * Get class toppers
     */
    public function getClassToppers($examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $toppers = $this->professionalResultService->getClassToppers($examId);
        $exam = Exam::findOrFail($examId);
        
        return view('admin.results.toppers', compact('toppers', 'exam'));
    }
    
    /**
     * Lock exam results
     */
    public function lockResults($examId)
    {
        $this->authorize('update', Result::class);
        
        $this->professionalResultService->lockExamResults($examId);
        
        return redirect()->back()->with('success', 'Results locked successfully.');
    }
    
    /**
     * Unlock exam results
     */
    public function unlockResults($examId)
    {
        $this->authorize('update', Result::class);
        
        $this->professionalResultService->unlockExamResults($examId);
        
        return redirect()->back()->with('success', 'Results unlocked successfully.');
    }
    
    /**
     * Get exam statistics
     */
    public function getStatistics($examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $statistics = $this->professionalResultService->getExamStatistics($examId);
        $exam = Exam::findOrFail($examId);
        
        return view('admin.results.statistics', compact('statistics', 'exam'));
    }
    
    /**
     * Show bulk import form
     */
    public function showBulkImport($examId)
    {
        $this->authorize('create', Result::class);
        $exam = Exam::findOrFail($examId);
        
        return view('admin.results.bulk-import', compact('exam'));
    }
    
    /**
     * Process bulk import
     */
    public function processBulkImport(Request $request, $examId)
    {
        $this->authorize('create', Result::class);
        
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
        ]);
        
        $result = $this->bulkImportService->importFromExcel($request->file('excel_file'), $examId);
        
        if ($result['success']) {
            return redirect()->route('admin.results.index')
                ->with('success', "Successfully imported {$result['imported_count']} results.")
                ->with('import_errors', $result['errors'])
                ->with('processed_students', $result['processed_students']);
        } else {
            return redirect()->back()
                ->with('error', $result['error']);
        }
    }
    
    /**
     * Download sample template
     */
    public function downloadSampleTemplate()
    {
        $this->authorize('create', Result::class);
        
        $tempFile = $this->bulkImportService->generateSampleTemplate();
        
        return response()->download($tempFile, 'result_import_template.xlsx')->deleteFileAfterSend(true);
    }
    
    /**
     * Export results to Excel
     */
    public function exportResults($examId)
    {
        $this->authorize('viewAny', Result::class);
        
        $tempFile = $this->bulkImportService->exportResults($examId);
        $exam = Exam::findOrFail($examId);
        
        return response()->download($tempFile, "results_{$exam->name}_export.xlsx")->deleteFileAfterSend(true);
    }
    
    /**
     * Get subject and total marks by exam ID for AJAX requests
     */
    public function getSubjectByExam($examId)
    {
        $exam = Exam::find($examId);

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'Exam not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'subject' => $exam->subject,
            'total_marks' => $exam->total_marks
        ]);
    }
    
    /**
     * Show the generate report card form
     */
    public function showGenerateReportCardForm()
    {
        $this->authorize('viewAny', Result::class);
        
        $students = Student::orderBy('name')->get();
        $exams = Exam::orderBy('name')->get();
        
        return view('admin.results.generate', compact('students', 'exams'));
    }
    
    /**
     * Handle generate report card form submission
     */
    public function generateReportCardFromForm(Request $request)
    {
        $this->authorize('viewAny', Result::class);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
        ], [
            'student_id.required' => 'Please select a student',
            'student_id.exists' => 'Selected student does not exist',
            'exam_id.required' => 'Please select an exam',
            'exam_id.exists' => 'Selected exam does not exist',
        ]);
        
        $studentId = $request->student_id;
        $examId = $request->exam_id;
        
        // Check if results exist for this student and exam
        $hasResults = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->exists();
            
        if (!$hasResults) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'No results found for this student in the selected exam. Please add results first.']);
        }
        
        // Redirect to report card
        return redirect()->route('admin.results.report-card', [
            'studentId' => $studentId,
            'examId' => $examId
        ]);
    }
    
    /**
     * Generate Final Result (CBSE Style Report Card)
     * Aggregates all subjects for a student in one exam
     */
    public function generateFinalResult($studentId, $examId)
    {
        $this->authorize('viewAny', Result::class);
        
        // Fetch all results for this student in this exam
        $results = Result::where('student_id', $studentId)
            ->where('exam_id', $examId)
            ->get();
            
        // Check if results exist
        if ($results->isEmpty()) {
            return redirect()->back()
                ->with('error', 'No results found for this student in the selected exam.');
        }
        
        // Get student and exam details
        $student = Student::findOrFail($studentId);
        $exam = Exam::findOrFail($examId);
        
        // Calculate final results
        $totalObtained = $results->sum('marks_obtained');
        $totalMax = $results->sum('total_marks');
        $percentage = $totalMax > 0 ? round(($totalObtained / $totalMax) * 100, 2) : 0;
        
        // Check if any subject failed (below 33%)
        $hasFailedSubject = $results->contains(function ($result) {
            return $result->percentage < 33;
        });
        
        $finalResult = $hasFailedSubject ? 'FAIL' : 'PASS';
        
        // Determine overall grade (CBSE style)
        $overallGrade = $this->calculateCBSEGrade($percentage);
        
        // Get exam name for grouping
        $examName = $exam->name;
        
        // Debug: Log the results count
        \Log::info('Final Result Generation', [
            'student_id' => $studentId,
            'exam_id' => $examId,
            'results_count' => $results->count(),
            'results' => $results->toArray()
        ]);
        
        return view('admin.results.final-result', compact(
            'student',
            'examName',
            'results',
            'totalObtained',
            'totalMax',
            'percentage',
            'finalResult',
            'overallGrade'
        ));
    }
    
    /**
     * Calculate CBSE grade based on percentage
     */
    private function calculateCBSEGrade($percentage)
    {
        if ($percentage >= 90) return 'A1';
        if ($percentage >= 80) return 'A2';
        if ($percentage >= 70) return 'B1';
        if ($percentage >= 60) return 'B2';
        if ($percentage >= 50) return 'C';
        if ($percentage >= 40) return 'D';
        if ($percentage >= 33) return 'E';
        return 'F';
    }
}
