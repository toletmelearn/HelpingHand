<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Teacher;
use App\Models\ClassManagement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherResultController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display teacher's assigned exams for marks entry
     */
    public function index()
    {
        $teacher = Auth::user()->teacher;
        
        // Get exams for the teacher based on their specialization
        $exams = Exam::where('subject', $teacher->subject_specialization)
                     ->get();
        
        $results = Result::with(['student', 'exam'])
                       ->whereIn('exam_id', $exams->pluck('id')->toArray())
                       ->paginate(15);
        
        return view('teacher.results.index', compact('exams', 'results'));
    }
    
    /**
     * Show form for entering marks for a specific exam
     */
    public function enterMarks(Exam $exam)
    {
        $teacher = Auth::user()->teacher;
        
        // Check if teacher is assigned to this exam's subject
        $isAssigned = $teacher->subject_specialization == $exam->subject;
        if (!$isAssigned) {
            abort(403, 'You are not authorized to enter marks for this subject.');
        }
        
        $students = Student::where('class_name', $exam->class_name)->get();
        
        // Get existing results for this exam and class
        $existingResults = Result::where('exam_id', $exam->id)
                                ->whereIn('student_id', $students->pluck('id')->toArray())
                                ->get()
                                ->keyBy('student_id');
        
        return view('teacher.results.enter-marks', compact('exam', 'students', 'existingResults'));
    }
    
    /**
     * Save marks for students in an exam
     */
    public function saveMarks(Request $request, Exam $exam)
    {
        $teacher = Auth::user()->teacher;
        
        // Check if teacher is assigned to this exam's subject
        $isAssigned = $teacher->subject_specialization == $exam->subject;
        if (!$isAssigned) {
            abort(403, 'You are not authorized to enter marks for this subject.');
        }
        
        $request->validate([
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:students,id',
            'marks.*.marks_obtained' => 'required|numeric|min:0|max:' . $exam->total_marks,
        ]);
        
        foreach ($request->marks as $markData) {
            $result = Result::updateOrCreate(
                [
                    'student_id' => $markData['student_id'],
                    'exam_id' => $exam->id,
                    'subject' => $exam->subject,
                ],
                [
                    'marks_obtained' => $markData['marks_obtained'],
                    'total_marks' => $exam->total_marks,
                    'academic_year' => $exam->academic_year,
                    'term' => $exam->term,
                    'result_status' => 'pending', // Will be updated by updateResultStatus()
                ]
            );
            
            // Update percentage, grade and status
            $result->updateResultStatus();
        }
        
        return redirect()->route('teacher.results.index')
                         ->with('success', 'Marks saved successfully.');
    }
    
    /**
     * Show teacher's own results
     */
    public function showResults()
    {
        $teacher = Auth::user()->teacher;
        $results = Result::with(['student', 'exam'])
                       ->where('subject', $teacher->subject_specialization)
                       ->paginate(15);
        
        return view('teacher.results.show-results', compact('results'));
    }
}
