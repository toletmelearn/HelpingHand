<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParentExamPaperController extends Controller
{
    public function index()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            return redirect()->back()->with('error', 'No student associated with this parent account.');
        }
        
        $student = $parent->student;
        
        $examPapers = ExamPaper::where('is_published', true)
            ->where('class_id', $student->class_id)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('parent.exam_papers.index', compact('examPapers', 'student'));
    }
    
    public function show($id)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            abort(403, 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        $examPaper = ExamPaper::where('id', $id)
            ->where('is_published', true)
            ->firstOrFail();
        
        // SECURITY CHECK: Verify exam paper belongs to student's class
        if ($examPaper->class_id != $student->class_id) {
            abort(403, 'Unauthorized access to this exam paper.');
        }
        
        return view('parent.exam_papers.show', compact('examPaper', 'student'));
    }
    
    public function download($id)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            abort(403, 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        $examPaper = ExamPaper::where('id', $id)
            ->where('is_published', true)
            ->firstOrFail();
        
        // SECURITY CHECK: Verify exam paper belongs to student's class
        if ($examPaper->class_id != $student->class_id) {
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
