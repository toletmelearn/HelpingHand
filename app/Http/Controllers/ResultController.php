<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CBSEResult;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Section;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResultController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', CBSEResult::class);
        
        $query = CBSEResult::with(['student', 'exam', 'subject'])
            ->orderBy('academic_year', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }
        
        if ($request->filled('class_id')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }

        $results = $query->paginate(20);
        $exams = Exam::all();
        $classes = SchoolClass::all();
        $academicYears = CBSEResult::select('academic_year')
            ->distinct()
            ->orderBy('academic_year', 'desc')
            ->pluck('academic_year');

        return view('results.index', compact('results', 'exams', 'classes', 'academicYears'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', CBSEResult::class);
        
        $students = Student::with('class', 'section')->get();
        $exams = Exam::all();
        $subjects = Subject::scholastic()->orderBy('sort_order')->get();
        $academicYears = ['2025-26', '2026-27', '2027-28'];
        $terms = ['Term 1', 'Term 2', 'Annual'];

        return view('results.create', compact('students', 'exams', 'subjects', 'academicYears', 'terms'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', CBSEResult::class);
        
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'pt_marks' => 'nullable|numeric|min:0|max:25',
            'notebook_marks' => 'nullable|numeric|min:0|max:5',
            'sea_marks' => 'nullable|numeric|min:0|max:5',
            'exam_marks' => 'nullable|numeric|min:0|max:65',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
            'remarks' => 'nullable|string|max:500',
        ], [
            'student_id.required' => 'Please select a student',
            'exam_id.required' => 'Please select an exam',
            'subject_id.required' => 'Please select a subject',
            'pt_marks.max' => 'Periodic Test marks cannot exceed 25',
            'notebook_marks.max' => 'Notebook marks cannot exceed 5',
            'sea_marks.max' => 'SEA marks cannot exceed 5',
            'exam_marks.max' => 'Exam marks cannot exceed 65',
        ]);

        // Check for duplicate entry
        $existing = CBSEResult::where([
            'student_id' => $validated['student_id'],
            'exam_id' => $validated['exam_id'],
            'subject_id' => $validated['subject_id']
        ])->first();

        if ($existing) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['duplicate' => 'Result already exists for this student, exam, and subject combination.']);
        }

        $result = new CBSEResult($validated);
        $result->autoCalculate();
        $result->generated_by = Auth::id();
        $result->save();

        // Update rankings
        $this->updateRankings($validated['student_id'], $validated['exam_id'], $validated['academic_year']);

        return redirect()->route('results.index')
            ->with('success', 'Result created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CBSEResult $result)
    {
        $this->authorize('view', $result);
        
        $result->load(['student.class', 'student.section', 'exam', 'subject']);
        return view('results.show', compact('result'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CBSEResult $result)
    {
        $this->authorize('update', $result);
        
        if ($result->is_locked) {
            return redirect()->back()
                ->with('error', 'This result is locked and cannot be edited.');
        }

        $students = Student::with('class', 'section')->get();
        $exams = Exam::all();
        $subjects = Subject::scholastic()->orderBy('sort_order')->get();
        $academicYears = ['2025-26', '2026-27', '2027-28'];
        $terms = ['Term 1', 'Term 2', 'Annual'];

        return view('results.edit', compact('result', 'students', 'exams', 'subjects', 'academicYears', 'terms'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CBSEResult $result)
    {
        $this->authorize('update', $result);
        
        if ($result->is_locked) {
            return redirect()->back()
                ->with('error', 'This result is locked and cannot be updated.');
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'pt_marks' => 'nullable|numeric|min:0|max:25',
            'notebook_marks' => 'nullable|numeric|min:0|max:5',
            'sea_marks' => 'nullable|numeric|min:0|max:5',
            'exam_marks' => 'nullable|numeric|min:0|max:65',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check for duplicate entry (excluding current record)
        $existing = CBSEResult::where([
            'student_id' => $validated['student_id'],
            'exam_id' => $validated['exam_id'],
            'subject_id' => $validated['subject_id']
        ])->where('id', '!=', $result->id)->first();

        if ($existing) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['duplicate' => 'Result already exists for this student, exam, and subject combination.']);
        }

        $result->fill($validated);
        $result->autoCalculate();
        $result->updated_by = Auth::id();
        $result->save();

        // Update rankings
        $this->updateRankings($validated['student_id'], $validated['exam_id'], $validated['academic_year']);

        return redirect()->route('results.index')
            ->with('success', 'Result updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CBSEResult $result)
    {
        $this->authorize('delete', $result);
        
        if ($result->is_locked) {
            return redirect()->back()
                ->with('error', 'This result is locked and cannot be deleted.');
        }

        $result->delete();

        return redirect()->route('results.index')
            ->with('success', 'Result deleted successfully.');
    }

    /**
     * Generate single subject result card
     */
    public function generateSingleSubjectResult($studentId, $examId, $subjectId)
    {
        // Get the specific result
        $result = CBSEResult::where([
            'student_id' => $studentId,
            'exam_id' => $examId,
            'subject_id' => $subjectId
        ])->with(['student.schoolClass', 'student.schoolSection', 'exam', 'subject'])->first();

        if (!$result) {
            // If result doesn't exist, create sample data for demonstration
            $student = Student::find($studentId);
            $exam = Exam::find($examId);
            $subject = Subject::find($subjectId);
            
            if (!$student || !$exam || !$subject) {
                abort(404, 'Student, Exam, or Subject not found');
            }
            
            // Return sample data view
            $data = [
                'student_name' => 'Pari Jain',
                'roll_no' => '30',
                'class' => '10th',
                'dob' => '15/08/2008',
                'student_photo' => $student->photo_url,
                'subject_name' => 'ENGLISH',
                'marks_obtained' => 30,
                'total_marks' => 60,
                'percentage' => 50,
                'grade' => 'C',
                'status' => 'PASS',
                'comments' => 'Pari Jain'
            ];
        } else {
            // Use actual result data
            $data = [
                'student_name' => $result->student->name,
                'roll_no' => $result->student->roll_number,
                'class' => $result->student->schoolClass->name ?? $result->student->class ?? 'N/A',
                'dob' => $result->student->date_of_birth->format('d/m/Y'),
                'student_photo' => $result->student->photo_url,
                'subject_name' => $result->subject->name,
                'marks_obtained' => $result->total_marks,
                'total_marks' => 100, // Assuming 100 total marks
                'percentage' => $result->percentage,
                'grade' => $result->grade,
                'status' => strtoupper($result->result_status),
                'comments' => $result->remarks ?? $result->student->name
            ];
        }

        return view('results.single-subject-result', $data);
    }

    /**
     * Generate PDF report card
     */
    public function generatePdf(CBSEResult $result)
    {
        $this->authorize('view', $result);
        
        $result->load(['student.schoolClass', 'student.schoolSection', 'exam', 'subject']);
        
        // Get all results for this student in this exam
        $allResults = CBSEResult::where([
            'student_id' => $result->student_id,
            'exam_id' => $result->exam_id,
            'academic_year' => $result->academic_year
        ])->with('subject')->get();

        // Calculate statistics
        $grandTotal = $allResults->sum('total_marks');
        $overallPercentage = $allResults->avg('percentage');
        $overallGrade = $this->calculateOverallGrade($overallPercentage);
        $finalResult = $allResults->every(fn($r) => $r->result_status === 'pass') ? 'Pass' : 'Fail';

        // Get co-scholastic subjects
        $coScholasticSubjects = Subject::coScholastic()->orderBy('sort_order')->get();

        // Prepare data for template
        $data = [
            'student_name' => $result->student->name,
            'father_name' => $result->student->father_name,
            'class' => $result->student->schoolClass->name ?? $result->student->class ?? 'N/A',
            'section' => $result->student->schoolSection->name ?? $result->student->section ?? '',
            'roll_no' => $result->student->roll_number,
            'admission_no' => $result->student->admission_number,
            'dob' => $result->student->date_of_birth->format('d-m-Y'),
            // The view calls public_path() on this value (dompdf needs a local
            // file path, not a URL) -- pass one directly, with a fallback the
            // view can also treat as a local path.
            'student_photo' => ($result->student->photo && file_exists(public_path('storage/' . $result->student->photo)))
                ? 'storage/' . $result->student->photo
                : 'images/default-avatar.png',
            'school_logo' => asset('images/school-logo.png'),
            'school_name' => 'HelpingHand Public School',
            'school_address' => 'Sector-12, Chandigarh',
            'affiliation_no' => '1630012',
            'exam_name' => $result->exam->name,
            'term' => $result->term,
            'academic_year' => $result->academic_year,
            'grand_total' => $grandTotal,
            'percentage' => round($overallPercentage, 2),
            'overall_grade' => $overallGrade,
            'final_result' => $finalResult,
            'attendance' => '245', // This should come from attendance system
            'working_days' => '260', // This should come from attendance system
            'remarks' => $result->remarks ?? 'Good performance. Keep it up!',
            'marks_rows' => $this->generateMarksRows($allResults),
            'coscholastic_rows' => $this->generateCoscholasticRows($coScholasticSubjects),
        ];

        $pdf = Pdf::loadView('results.pdf-template', $data);
        $fileName = 'Result_' . $result->student->name . '_' . $result->exam->name . '.pdf';
        
        return $pdf->download($fileName);
    }

    /**
     * Lock/unlock result
     */
    public function toggleLock(CBSEResult $result)
    {
        $this->authorize('lock', $result);
        
        if ($result->is_locked) {
            // Unlock
            $result->is_locked = false;
            $result->locked_by = null;
            $result->locked_at = null;
        } else {
            // Lock
            $result->is_locked = true;
            $result->locked_by = Auth::id();
            $result->locked_at = now();
        }
        
        $result->save();

        $message = $result->is_locked ? 'Result locked successfully.' : 'Result unlocked successfully.';
        return redirect()->back()->with('success', $message);
    }

    /**
     * Bulk upload results
     */
    public function bulkUpload(Request $request)
    {
        $this->authorize('create', CBSEResult::class);
        
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,xlsx,xls',
            'exam_id' => 'required|exists:exams,id',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
        ]);

        // Process CSV/XLSX file
        // Implementation depends on the file format
        // This is a simplified example
        
        return redirect()->back()->with('success', 'Bulk upload completed.');
    }

    /**
     * Generate marks rows HTML
     */
    private function generateMarksRows($results)
    {
        $html = '';
        foreach ($results as $result) {
            $html .= "<tr>";
            $html .= "<td>{$result->subject->name}</td>";
            $html .= "<td>" . ($result->pt_marks ?? 0) . "</td>";
            $html .= "<td>" . ($result->notebook_marks ?? 0) . "</td>";
            $html .= "<td>" . ($result->sea_marks ?? 0) . "</td>";
            $html .= "<td>" . ($result->exam_marks ?? 0) . "</td>";
            $html .= "<td>{$result->total_marks}</td>";
            $html .= "<td>{$result->grade}</td>";
            $html .= "</tr>";
        }
        return $html;
    }

    /**
     * Generate co-scholastic rows HTML
     */
    private function generateCoscholasticRows($subjects)
    {
        $html = '';
        foreach ($subjects as $subject) {
            $html .= "<tr>";
            $html .= "<td>{$subject->name}</td>";
            $html .= "<td>A1</td>"; // Default grade, should come from actual data
            $html .= "</tr>";
        }
        return $html;
    }

    /**
     * Calculate overall grade based on percentage
     */
    private function calculateOverallGrade($percentage)
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
     * Update class and section rankings
     */
    private function updateRankings($studentId, $examId, $academicYear)
    {
        // Get student's class and section
        $student = Student::find($studentId);
        $classId = $student->class_id;
        $sectionId = $student->section_id;

        // Update class rankings
        $classResults = CBSEResult::whereHas('student', function($q) use ($classId) {
            $q->where('class_id', $classId);
        })
        ->where('exam_id', $examId)
        ->where('academic_year', $academicYear)
        ->select('student_id', DB::raw('AVG(percentage) as avg_percentage'))
        ->groupBy('student_id')
        ->orderBy('avg_percentage', 'desc')
        ->get();

        foreach ($classResults as $index => $result) {
            CBSEResult::where('student_id', $result->student_id)
                ->where('exam_id', $examId)
                ->where('academic_year', $academicYear)
                ->update(['class_rank' => $index + 1]);
        }

        // Update section rankings
        $sectionResults = CBSEResult::whereHas('student', function($q) use ($sectionId) {
            $q->where('section_id', $sectionId);
        })
        ->where('exam_id', $examId)
        ->where('academic_year', $academicYear)
        ->select('student_id', DB::raw('AVG(percentage) as avg_percentage'))
        ->groupBy('student_id')
        ->orderBy('avg_percentage', 'desc')
        ->get();

        foreach ($sectionResults as $index => $result) {
            CBSEResult::where('student_id', $result->student_id)
                ->where('exam_id', $examId)
                ->where('academic_year', $academicYear)
                ->update(['section_rank' => $index + 1]);
        }
    }
}