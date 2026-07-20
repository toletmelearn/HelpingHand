<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentExamPaperController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student not authenticated.');
        }
        
        $examPapers = ExamPaper::where('is_published', true)
            ->where('class_id', $student->class_id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('student.exam_papers.index', compact('examPapers', 'student'));
    }
    
    public function download($id)
    {
        $examPaper = ExamPaper::where('id', $id)
            ->where('is_published', true)
            ->firstOrFail();
        
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