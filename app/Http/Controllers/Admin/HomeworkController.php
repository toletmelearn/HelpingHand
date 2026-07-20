<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomeworkNotice;
use App\Models\Teacher;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class HomeworkController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', HomeworkNotice::class);

        // Get filter options
        $teachers = Teacher::all();
        $classes = SchoolClass::all();

        // Apply filters dynamically - show all if no filters selected
        $query = HomeworkNotice::with(['schoolClass', 'subject', 'teacherLogin'])
                    ->where('type', 'homework');

        if ($request->assigned_by) {
            $query->where('assigned_by', $request->assigned_by);
        }

        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->date) {
            $query->whereDate('created_at', $request->date);
        }

        $homeworks = $query->latest()->get();

        // Only show summary if at least one filter is applied
        $hasFilters = $request->filled(['assigned_by', 'class_id', 'date']);
        
        if ($hasFilters) {
            $summary = [
                'total_homework' => $homeworks->count(),
                'teacher_name' => $request->assigned_by ? Teacher::find($request->assigned_by)?->name : 'All Teachers',
                'class_name' => $request->class_id ? SchoolClass::find($request->class_id)?->name : 'All Classes',
                'date' => $request->date ?: 'All Dates',
            ];
        } else {
            $summary = null;
        }

        return view('admin.homework.index', compact('homeworks', 'teachers', 'classes', 'summary'));
    }
    
    public function show(HomeworkNotice $homeworkNotice)
    {
        $this->authorize('view', $homeworkNotice);
        
        $homeworkNotice->load(['schoolClass', 'subject', 'teacherLogin']);
        
        return view('admin.homework.show', compact('homeworkNotice'));
    }
}