<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentExamPaperController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Exams V1: students authenticate through the default 'web' guard
     * (a User with a student() relation) exactly like every sibling
     * student-facing controller (StudentResultController,
     * StudentAdmitCardController, etc.) -- there is no 'student' guard
     * registered in config/auth.php at all (only web/parent/teacher).
     * Auth::guard('student')->user() previously used here threw
     * InvalidArgumentException("Auth guard [student] is not defined.")
     * on every single request, so this entire feature 500'd unconditionally
     * for every real student before this fix.
     */
    private function currentStudent(): ?Student
    {
        return Auth::user()?->student;
    }

    public function index()
    {
        $student = $this->currentStudent();

        if (!$student) {
            return redirect()->back()->with('error', 'Student not authenticated.');
        }

        $examPapers = ExamPaper::where('is_published', true)
            ->where('class_id', $student->school_class_id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('student.exam_papers.index', compact('examPapers', 'student'));
    }

    public function show($id)
    {
        $student = $this->currentStudent();

        if (!$student) {
            return redirect()->back()->with('error', 'Student not authenticated.');
        }

        $examPaper = ExamPaper::where('id', $id)
            ->where('is_published', true)
            ->firstOrFail();

        // SECURITY CHECK: Verify exam paper belongs to student's class
        // (same check Parent\ParentExamPaperController::show() already
        // applies -- this sibling controller was missing both this method
        // and the check entirely).
        if ($examPaper->class_id != $student->school_class_id) {
            abort(403, 'Unauthorized access to this exam paper.');
        }

        return view('student.exam_papers.show', compact('examPaper', 'student'));
    }

    public function download($id)
    {
        $student = $this->currentStudent();

        if (!$student) {
            return redirect()->back()->with('error', 'Student not authenticated.');
        }

        $examPaper = ExamPaper::where('id', $id)
            ->where('is_published', true)
            ->firstOrFail();

        // SECURITY CHECK: Verify exam paper belongs to student's class --
        // previously missing entirely, so any authenticated account could
        // download any published exam paper for any class.
        if ($examPaper->class_id != $student->school_class_id) {
            abort(403, 'Unauthorized access to this exam paper.');
        }

        if (!$examPaper->file_path) {
            return back()->with('error', 'No file available for download');
        }

        $filePath = storage_path('app/public/' . $examPaper->file_path);

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found');
        }

        return response()->download($filePath);
    }
}
