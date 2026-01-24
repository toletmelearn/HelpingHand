<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamPaperTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamPaperTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->hasRole('admin')) {
                abort(403, 'Unauthorized access');
            }
            return $next($request);
        });
    }
    
    public function index()
    {
        $templates = ExamPaperTemplate::orderBy('name')->paginate(15);
        return view('admin.exam-paper-templates.index', compact('templates'));
    }
    
    public function create()
    {
        return view('admin.exam-paper-templates.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_content' => 'required|string',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:100',
            'academic_year' => 'nullable|string|max:20',
            'header_content' => 'nullable|string',
            'instruction_block' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'section_config' => 'nullable|array',
            'marks_distribution' => 'nullable|array',
        ]);
        
        $data = $request->all();
        $data['created_by'] = Auth::id();
        
        ExamPaperTemplate::create($data);
        
        return redirect()->route('admin.exam-paper-templates.index')->with('success', 'Template created successfully.');
    }
    
    public function show(ExamPaperTemplate $examPaperTemplate)
    {
        return view('admin.exam-paper-templates.show', compact('examPaperTemplate'));
    }
    
    public function edit(ExamPaperTemplate $examPaperTemplate)
    {
        return view('admin.exam-paper-templates.edit', compact('examPaperTemplate'));
    }
    
    public function update(Request $request, ExamPaperTemplate $examPaperTemplate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_content' => 'required|string',
            'subject' => 'required|string|max:100',
            'class_section' => 'required|string|max:100',
            'academic_year' => 'nullable|string|max:20',
            'header_content' => 'nullable|string',
            'instruction_block' => 'nullable|string',
            'footer_content' => 'nullable|string',
            'section_config' => 'nullable|array',
            'marks_distribution' => 'nullable|array',
        ]);
        
        $data = $request->all();
        $data['updated_by'] = Auth::id();
        
        $examPaperTemplate->update($data);
        
        return redirect()->route('admin.exam-paper-templates.index')->with('success', 'Template updated successfully.');
    }
    
    public function destroy(ExamPaperTemplate $examPaperTemplate)
    {
        $examPaperTemplate->delete();
        
        return redirect()->route('admin.exam-paper-templates.index')->with('success', 'Template deleted successfully.');
    }
    
    public function toggleStatus(ExamPaperTemplate $examPaperTemplate)
    {
        $examPaperTemplate->update(['is_active' => !$examPaperTemplate->is_active]);
        
        $status = $examPaperTemplate->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "Template {$status} successfully.");
    }
    
    public function preview(ExamPaperTemplate $examPaperTemplate)
    {
        // Return a preview of the template
        return view('admin.exam-paper-templates.preview', compact('examPaperTemplate'));
    }
}
