<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TeacherExamPaperController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // NOTE: exam_papers.created_by has FK constraint to users.id
        // Since teachers don't have user accounts, we filter by class/subject assignments
        // to show exam papers relevant to this teacher
        $assignedClassIds = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        $examPapers = ExamPaper::whereHas('class', function ($query) use ($assignedClassIds) {
                $query->whereIn('id', $assignedClassIds);
            })
            ->with(['exam', 'class'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('teacher.exam_papers.index', compact('examPapers'));
    }

    public function create()
    {
        $exams = Exam::all();
        $classes = SchoolClass::all();
        return view('teacher.exam_papers.create', compact('exams', 'classes'));
    }

    public function store(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'exam_id' => 'required|exists:exams,id',
            'class_id' => 'required|exists:school_classes,id',
            'class_section' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'paper_content' => 'nullable|string',
            'file' => 'nullable|mimes:pdf,doc,docx|max:2048'
        ]);

        $fileName = null;
        $filePath = null;
        $fileSize = null;
        $fileExtension = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $fileName = time().'_'.$file->getClientOriginalName();
            $fileExtension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();

            $filePath = $file->storeAs('exam_papers', $fileName);
        }

        $examPaper = new ExamPaper();

        $examPaper->title = $request->title;
        $examPaper->exam_id = $request->exam_id;
        $examPaper->exam_type = $request->exam_type ?? 'General';
        $examPaper->class_id = $request->class_id;
        $examPaper->class_section = $request->class_section ?? null;
        $examPaper->subject = $request->subject ?? null;
        $examPaper->instructions = $request->instructions ?? null;
        $examPaper->paper_content = $request->paper_content ?? null;

        $examPaper->file_name = $fileName;
        $examPaper->file_path = $filePath;
        $examPaper->file_size = $fileSize;
        $examPaper->file_extension = $fileExtension;

        // FK-SAFE OWNERSHIP STRATEGY:
        // created_by → NULL (FK-safe, column is nullable)
        // metadata → Stores real teacher ownership as JSON
        // Visibility → Controlled by assignment-based filtering (class_id)
        $examPaper->created_by = null; // FK-safe (nullable column)
        $examPaper->metadata = array_merge($examPaper->metadata ?? [], [
            'teacher_id' => $teacher->id,
            'teacher_name' => $teacher->name ?? null,
            'created_at' => now()->toISOString()
        ]);
        $examPaper->status = 'submitted';
        $examPaper->is_approved = 0;
        $examPaper->is_published = 0;
        $examPaper->paper_type = 'Question Paper';
        $examPaper->access_level = 'private';

        $examPaper->save();

        \Illuminate\Support\Facades\Log::info('Exam paper created', [
            'teacher_id' => $teacher->id,
            'exam_paper_id' => $examPaper->id,
            'title' => $examPaper->title,
            'class_id' => $examPaper->class_id,
            'exam_id' => $examPaper->exam_id
        ]);

        return redirect()->route('teacher.exam-papers.index')->with('success', 'Exam paper submitted to admin for approval');
    }

    public function show($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Get teacher's assigned class IDs
        $assignedClassIds = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        // Teacher can view exam papers for their assigned classes
        $examPaper = ExamPaper::where('id', $id)
            ->whereHas('class', function ($query) use ($assignedClassIds) {
                $query->whereIn('id', $assignedClassIds);
            })
            ->with(['exam', 'class'])
            ->firstOrFail();

        return view('teacher.exam_papers.show', compact('examPaper'));
    }

    public function edit($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Get teacher's assigned class IDs
        $assignedClassIds = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        // Teacher can edit exam papers for their assigned classes (only if draft)
        $examPaper = ExamPaper::where('id', $id)
            ->whereHas('class', function ($query) use ($assignedClassIds) {
                $query->whereIn('id', $assignedClassIds);
            })
            ->where('status', 'draft') // Only allow editing if still in draft
            ->with(['exam', 'class'])
            ->firstOrFail();

        $exams = Exam::all();
        $classes = SchoolClass::all();

        return view('teacher.exam_papers.edit', compact('examPaper', 'exams', 'classes'));
    }

    public function update(Request $request, $id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Get teacher's assigned class IDs
        $assignedClassIds = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        // Teacher can update exam papers for their assigned classes (only if draft)
        $examPaper = ExamPaper::where('id', $id)
            ->whereHas('class', function ($query) use ($assignedClassIds) {
                $query->whereIn('id', $assignedClassIds);
            })
            ->where('status', 'draft') // Only allow updating if still in draft
            ->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
            'exam_id' => 'required|exists:exams,id',
            'class_id' => 'required|exists:school_classes,id',
            'subject' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'paper_content' => 'nullable|string',
            'paper_file' => 'nullable|mimes:pdf,doc,docx|max:2048'
        ]);

        $filePath = $examPaper->file_path;
        $fileName = $examPaper->file_name;
        if($request->hasFile('paper_file')){
            // Delete old file if exists
            if($examPaper->file_path) {
                Storage::disk('public')->delete($examPaper->file_path);
            }
            $file = $request->file('paper_file');
            $fileName = time().'_'.$file->getClientOriginalName();
            $filePath = $file->storeAs('exam_papers', $fileName, 'public');
        }

        $examPaper->update([
            'title' => $request->title,
            'exam_id' => $request->exam_id,
            'class_id' => $request->class_id,
            'subject' => $request->subject,
            'instructions' => $request->instructions,
            'paper_content' => $request->paper_content,
            'file_name' => $fileName,
            'file_path' => $filePath,
        ]);

        return redirect()->route('teacher.exam-papers.index')->with('success', 'Exam paper updated successfully');
    }
    
    public function destroy($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        // Get teacher's assigned class IDs
        $assignedClassIds = \App\Models\TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        // Teacher can delete exam papers for their assigned classes (only if draft)
        $examPaper = ExamPaper::where('id', $id)
            ->whereHas('class', function ($query) use ($assignedClassIds) {
                $query->whereIn('id', $assignedClassIds);
            })
            ->where('status', 'draft') // Only allow deleting if still in draft
            ->firstOrFail();
        
        // Delete file if exists
        if($examPaper->file_path) {
            Storage::disk('public')->delete($examPaper->file_path);
        }
        
        $examPaper->delete();
        
        return redirect()->route('teacher.exam-papers.index')->with('success', 'Exam paper deleted successfully');
    }
}