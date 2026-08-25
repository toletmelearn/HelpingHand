<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HomeworkNotice;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\TeacherLogin;

class ProfessionalHomeworkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = HomeworkNotice::with(['schoolClass', 'subject', 'teacherLogin']);
        
        // Apply filters
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        
        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }
        
        if ($request->filled('teacher_id')) {
            $query->where('assigned_by', $request->teacher_id);
        }
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        
        if ($request->filled('parent_visible')) {
            $query->where('visible_to_parent', $request->parent_visible);
        }
        
        if ($request->filled('date_from')) {
            $query->where('publish_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('publish_date', '<=', $request->date_to);
        }
        
        $homeworks = $query->latest()->paginate(20);
        
        // Get filter options
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $subjects = Subject::where('status', 'active')->orderBy('name')->get();
        $teachers = TeacherLogin::orderBy('username')->get();
        
        return view('admin.homework.professional-index', compact('homeworks', 'classes', 'subjects', 'teachers'));
    }

    public function show(HomeworkNotice $homework)
    {
        $homework->load(['schoolClass', 'subject', 'teacherLogin', 'section']);
        
        return view('admin.homework.professional-show', compact('homework'));
    }

    public function destroy(HomeworkNotice $homework)
    {
        $homework->delete();
        
        return redirect()->route('admin.professional-homework.index')
            ->with('success', 'Homework deleted successfully!');
    }
}
