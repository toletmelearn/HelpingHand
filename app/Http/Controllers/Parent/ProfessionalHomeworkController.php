<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\HomeworkNotice;
use App\Models\Student;

class ProfessionalHomeworkController extends Controller
{
    public function index()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            return redirect()->back()->with('error', 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Get homework for the student's class that is visible to parents
        $homeworks = HomeworkNotice::where('class_id', $student->school_class_id)
            ->where('visible_to_parent', 1)
            ->where('type', 'homework')
            ->with(['schoolClass', 'subject', 'teacherLogin'])
            ->latest()
            ->paginate(15);

        return view('parent.homework.professional-index', compact('homeworks'));
    }
    
    public function show(HomeworkNotice $homework)
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent || !$parent->student) {
            abort(403, 'Student not linked to parent');
        }
        
        $student = $parent->student;
        
        // Check if this homework belongs to the student's class and is visible to parents
        if ($homework->class_id != $student->school_class_id || !$homework->visible_to_parent) {
            abort(403, 'Unauthorized access to this homework.');
        }
        
        // Load necessary relationships
        $homework->load(['schoolClass', 'subject', 'teacherLogin', 'section']);
        
        return view('parent.homework.professional-show', compact('homework'));
    }
}
