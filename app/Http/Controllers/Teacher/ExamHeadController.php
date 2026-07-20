<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Result;
use App\Models\Exam;
use App\Models\Student;

class ExamHeadController extends Controller
{
    /**
     * Display all submitted marks for review.
     */
    public function index(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher not logged in.');
        }

        // Verify teacher is exam head
        if (!$teacher->isExamHead()) {
            abort(403, 'You do not have exam head privileges.');
        }

        // Get all submitted results
        $query = Result::where('status', 'submitted')
            ->with(['student', 'exam', 'uploadedByTeacher']);

        // Apply filters
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }

        $results = $query->latest('uploaded_at')->paginate(50);

        // Get exams for filter
        $exams = Exam::orderBy('name')->get();

        // Get unique subjects
        $subjects = Result::distinct('subject')->pluck('subject');

        return view('teacher.examhead.marks', compact('results', 'exams', 'subjects'));
    }

    /**
     * Approve submitted marks.
     */
    public function approve($markId)
    {
        $teacher = Auth::guard('teacher')->user();
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher not logged in.');
        }

        // Verify teacher is exam head
        if (!$teacher->isExamHead()) {
            abort(403, 'You do not have exam head privileges.');
        }

        $result = Result::findOrFail($markId);
        
        $result->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Marks approved successfully!');
    }

    /**
     * Edit and update marks.
     */
    public function edit(Request $request, $markId)
    {
        $teacherLogin = Auth::guard('teacher')->user();

        // Verify teacher is exam head
        if (!$teacherLogin->isExamHead()) {
            abort(403, 'You do not have exam head privileges.');
        }

        $request->validate([
            'marks_obtained' => 'required|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        $result = Result::findOrFail($markId);
        
        // Recalculate percentage and grade
        $percentage = $result->total_marks > 0 
            ? ($request->marks_obtained / $result->total_marks) * 100 
            : 0;
        
        $grade = $this->calculateGrade($percentage);

        $result->update([
            'marks_obtained' => $request->marks_obtained,
            'percentage' => $percentage,
            'grade' => $grade,
            'remarks' => $request->remarks,
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'is_locked' => true, // Keep locked after exam head edit
            'locked_at' => now(),
        ]);

        return back()->with('success', 'Marks updated and approved successfully!');
    }

    /**
     * Calculate grade based on percentage.
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
