<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Models\ExamPaperTemplate;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamPaperController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $query = ExamPaper::with(['uploadedBy', 'approvedBy', 'exam', 'createdBy', 'class']);
        
        // Apply filters
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->filled('class_section')) {
            $query->where('class_section', $request->class_section);
        }
        
        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->exam_type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('teacher_id')) {
            $query->where('created_by', $request->teacher_id);
        }
        
        $papers = $query->orderBy('created_at', 'desc')->paginate(15);
        
        // Get filters for dropdowns
        $teachers = User::role('teacher')->get();
        $subjects = ExamPaper::select('subject')->distinct()->pluck('subject');
        $classes = ExamPaper::select('class_section')->distinct()->pluck('class_section');
        $examTypes = ExamPaper::select('exam_type')->distinct()->pluck('exam_type');
        
        return view('admin.exam-papers.index', compact('papers', 'teachers', 'subjects', 'classes', 'examTypes'));
    }
    
    public function create()
    {
        $exams = Exam::all();
        $templates = ExamPaperTemplate::active()->get();
        
        return view('admin.exam-papers.create', compact('exams', 'templates'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:100',
            'exam_type' => 'required|string|max:50',
            'paper_type' => 'required|string|max:50',
            'exam_id' => 'nullable|exists:exams,id',
            'file' => 'nullable|mimes:pdf,doc,docx,txt|max:10240', // 10MB max
            'instructions' => 'nullable|string',
            'template_id' => 'nullable|exists:exam_paper_templates,id',
            'questions_data' => 'nullable|array',
        ]);
        
        $data = $request->except(['file']);
        $data['created_by'] = Auth::id();
        $data['uploaded_by'] = Auth::id();
        $data['status'] = 'draft';
        $data['version'] = 1;
        
        // Handle file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('exam-papers', $filename, 'public');
            
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['file_extension'] = $file->getClientOriginalExtension();
        }
        
        // Process questions data if provided
        if ($request->filled('questions_data')) {
            $data['questions_data'] = $request->questions_data;
            $data['auto_calculated_total'] = collect($request->questions_data)->sum('marks');
        }
        
        // Use template if provided
        if ($request->filled('template_id')) {
            $template = ExamPaperTemplate::find($request->template_id);
            $data['template_used'] = $template->name;
        }
        
        $examPaper = ExamPaper::create($data);
        
        return redirect()->route('admin.exam-papers.index')->with('success', 'Exam paper created successfully.');
    }
    
    public function show($id)
    {
        $examPaper = ExamPaper::with(['createdBy', 'approvedByAdmin', 'exam', 'class', 'uploadedBy', 'approvedBy'])->findOrFail($id);
        $this->authorize('view', $examPaper);

        return view('admin.exam-papers.show', compact('examPaper'));
    }
    
    public function edit(ExamPaper $examPaper)
    {
        $exams = Exam::all();
        $templates = ExamPaperTemplate::active()->get();
        
        return view('admin.exam-papers.edit', compact('examPaper', 'exams', 'templates'));
    }
    
    public function update(Request $request, ExamPaper $examPaper)
    {
        if (!$examPaper->canAdminEdit()) {
            return redirect()->back()->with('error', 'Cannot edit this exam paper as it is locked.');
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:100',
            'exam_type' => 'required|string|max:50',
            'paper_type' => 'required|string|max:50',
            'exam_id' => 'nullable|exists:exams,id',
            'file' => 'nullable|mimes:pdf,doc,docx,txt|max:10240', // 10MB max
            'instructions' => 'nullable|string',
            'template_id' => 'nullable|exists:exam_paper_templates,id',
            'questions_data' => 'nullable|array',
        ]);
        
        $data = $request->except(['file']);
        
        // Handle file upload
        if ($request->hasFile('file')) {
            // Delete old file if exists
            if ($examPaper->file_path) {
                Storage::disk('public')->delete($examPaper->file_path);
            }
            
            $file = $request->file('file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('exam-papers', $filename, 'public');
            
            $data['file_path'] = $path;
            $data['file_name'] = $file->getClientOriginalName();
            $data['file_size'] = $file->getSize();
            $data['file_extension'] = $file->getClientOriginalExtension();
        }
        
        // Process questions data if provided
        if ($request->filled('questions_data')) {
            $data['questions_data'] = $request->questions_data;
            $data['auto_calculated_total'] = collect($request->questions_data)->sum('marks');
        }
        
        // Use template if provided
        if ($request->filled('template_id')) {
            $template = ExamPaperTemplate::find($request->template_id);
            $data['template_used'] = $template->name;
        }
        
        $examPaper->update($data);
        
        return redirect()->route('admin.exam-papers.index')->with('success', 'Exam paper updated successfully.');
    }
    
    public function destroy($id)
    {
        $paper = ExamPaper::findOrFail($id);
        $this->authorize('delete', $paper);
        
        if ($paper->file_path) {
            Storage::disk('public')->delete($paper->file_path);
        }
        
        $paper->delete();
        
        return redirect()->route('admin.exam-papers.index')->with('success', 'Exam paper deleted successfully.');
    }
    
    public function showApproveConfirmation($id)
    {
        $examPaper = ExamPaper::with(['createdBy', 'approvedByAdmin', 'exam', 'class'])->findOrFail($id);
        $this->authorize('approve', $examPaper);
        
        return view('admin.exam-papers.confirm-approval', compact('examPaper'));
    }

    public function submit(ExamPaper $examPaper)
    {
        $this->authorize('submit', $examPaper);
        if ($examPaper->status !== 'draft') {
            return redirect()->back()->with('error', 'Cannot submit exam paper that is not in draft status.');
        }
        
        $examPaper->update(['status' => 'submitted']);
        
        return redirect()->back()->with('success', 'Exam paper submitted for approval successfully.');
    }
    
    public function approve($id)
    {
        $paper = ExamPaper::findOrFail($id);
        $this->authorize('approve', $paper);
        $paper->is_approved = 1;
        $paper->status = 'admin_approved';
        $paper->save();

        return back()->with('success','Approved');
    }
    
    public function examApprove($id)
    {
        $examPaper = ExamPaper::findOrFail($id);
        $this->authorize('approve', $examPaper);
        
        $examPaper->update([
            'status' => 'exam_approved',
            'exam_approved_by' => Auth::id(),
            'is_approved' => true
        ]);

        return back()->with('success', 'Exam paper approved by exam department successfully');
    }
    
    public function publish($id)
    {
        $paper = ExamPaper::findOrFail($id);
        $this->authorize('approve', $paper);
        $paper->is_published = 1;
        $paper->status = 'published';
        $paper->save();

        return back()->with('success','Published');
    }

    public function reject($id)
    {
        $paper = ExamPaper::findOrFail($id);
        $this->authorize('approve', $paper);
        $paper->status = 'draft';
        $paper->is_approved = 0;
        $paper->save();

        return back()->with('error','Rejected');
    }

    public function lock(ExamPaper $examPaper)
    {
        $this->authorize('lock', $examPaper);
        $examPaper->update(['status' => 'locked']);
        
        return redirect()->back()->with('success', 'Exam paper locked successfully.');
    }
    
    public function download($id)
    {
        $examPaper = ExamPaper::findOrFail($id);
        $this->authorize('view', $examPaper);

        if (!$examPaper->file_path) {
            return back()->with('error', 'No file available for download');
        }

        $filePath = storage_path('app/public/' . $examPaper->file_path);
        
        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found');
        }

        return response()->download($filePath);
    }
    
    public function print(ExamPaper $examPaper)
    {
        $this->authorize('view', $examPaper);
        return view('admin.exam-papers.print', compact('examPaper'));
    }
    
    public function clone(ExamPaper $examPaper)
    {
        $this->authorize('clone', $examPaper);
        $newPaper = $examPaper->replicate();
        $newPaper->title = $examPaper->title . ' (Copy)';
        $newPaper->status = 'draft';
        $newPaper->version = 1;
        $newPaper->save();
        
        return redirect()->route('admin.exam-papers.edit', $newPaper->id)->with('success', 'Exam paper cloned successfully.');
    }
    
    public function availableForClass(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $papers = ExamPaper::where('is_published', true)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('admin.exam-papers.available', compact('papers'));
    }
    
    public function search(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $query = ExamPaper::query();
        
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%$searchTerm%")
                  ->orWhere('subject', 'LIKE', "%$searchTerm%")
                  ->orWhereHas('createdBy', function($q) use ($searchTerm) {
                      $q->where('name', 'LIKE', "%$searchTerm%");
                  });
            });
        }
        
        $examPapers = $query->with(['createdBy', 'exam', 'class'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('admin.exam-papers.search', compact('examPapers'));
    }
    
    public function upcoming(Request $request)
    {
        $this->authorize('viewAny', ExamPaper::class);
        $upcomingPapers = ExamPaper::where('is_published', true)
            ->where('is_approved', true)
            ->whereNotNull('exam_date')
            ->where('exam_date', '>=', now())
            ->orderBy('exam_date')
            ->paginate(15);
        
        return view('admin.exam-papers.upcoming', compact('upcomingPapers'));
    }
    
    public function togglePublish(ExamPaper $examPaper)
    {
        $this->authorize('approve', $examPaper);
        $examPaper->update(['is_published' => !$examPaper->is_published]);
        
        return response()->json(['success' => true, 'is_published' => $examPaper->is_published]);
    }
}
