<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\HomeworkNotice;
use App\Models\TeacherClassSubjectAssignment;

class TeacherHomeworkController extends Controller
{
    /**
     * Display a listing of homework.
     */
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        // Get homework for this teacher's assigned classes
        // NOTE: assigned_by stores users.id (FK constraint), not teacher_id
        // We filter by class assignments instead of assigned_by
        $classIds = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        $homeworks = HomeworkNotice::whereHas('schoolClass', function ($query) use ($classIds) {
                $query->whereIn('id', $classIds);
            })
            ->with(['schoolClass', 'subject'])
            ->latest()
            ->paginate(15);

        // Get assigned classes and subjects for the form
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'subject'])
            ->get();
        
        $classes = $assignments->pluck('schoolClass')->unique('id')->values();
        $subjects = $assignments->pluck('subject')->unique('id')->values();

        return view('teacher.homework.index', compact('homeworks', 'classes', 'subjects'));
    }

    /**
     * Show the form for creating new homework.
     */
    public function create()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }

        // Get assigned classes and subjects
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'subject'])
            ->get();

        return view('teacher.homework.create', compact('assignments'));
    }

    /**
     * Store a newly created homework.
     */
    public function store(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:homework,notice,announcement',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'due_date' => 'nullable|date|after:today',
            'priority' => 'required|in:low,medium,high',
            'visible_to_parent' => 'boolean',
            'parent_notes' => 'nullable|string',
        ]);

        $homework = new HomeworkNotice();
        $homework->title = $request->title;
        $homework->description = $request->description;
        $homework->type = $request->type;
        $homework->class_id = $request->class_id;
        $homework->section_id = $request->section_id;
        $homework->subject_id = $request->subject_id;
        
        // FK-SAFE OWNERSHIP STRATEGY:
        // assigned_by → System user ID (FK-safe, non-nullable column)
        // Real teacher ownership → Tracked via TeacherClassSubjectAssignment (class_id + teacher_id)
        // Visibility → Controlled by assignment-based filtering, NOT assigned_by field
        $systemUserId = \App\Models\User::orderBy('id')->value('id');
        $homework->assigned_by = $systemUserId ?? 1; // FK compliance (non-nullable column)
        
        $homework->due_date = $request->due_date;
        $homework->publish_date = now();
        $homework->status = 'active';
        $homework->priority = $request->priority;
        $homework->visible_to_parent = $request->has('visible_to_parent') ? 1 : 0;
        $homework->parent_notes = $request->parent_notes;
        
        $homework->save();

        \Illuminate\Support\Facades\Log::info('Homework created', [
            'teacher_id' => $teacher->id,
            'homework_id' => $homework->id,
            'title' => $homework->title,
            'class_id' => $homework->class_id,
            'type' => $homework->type
        ]);

        return redirect()->route('teacher.homework.index')
            ->with('success', 'Homework created successfully!');
    }

    /**
     * Show the form for editing homework.
     */
    public function edit($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin ? $teacherLogin->teacher : null;
        
        if (!$teacher) {
            return redirect()->back()->with('error', 'Teacher record not found.');
        }
        
        $homework = HomeworkNotice::findOrFail($id);

        // Get assigned classes and subjects
        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->with(['schoolClass', 'subject'])
            ->get();

        return view('teacher.homework.edit', compact('homework', 'assignments'));
    }

    /**
     * Update the specified homework.
     */
    public function update(Request $request, $id)
    {
        $homework = HomeworkNotice::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => 'required|in:homework,notice,announcement',
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'due_date' => 'nullable|date',
            'priority' => 'required|in:low,medium,high',
            'visible_to_parent' => 'boolean',
            'parent_notes' => 'nullable|string',
        ]);

        $homework->title = $request->title;
        $homework->description = $request->description;
        $homework->type = $request->type;
        $homework->class_id = $request->class_id;
        $homework->subject_id = $request->subject_id;
        $homework->due_date = $request->due_date;
        $homework->priority = $request->priority;
        $homework->visible_to_parent = $request->has('visible_to_parent') ? 1 : 0;
        $homework->parent_notes = $request->parent_notes;
        $homework->save();

        return redirect()->route('teacher.homework.index')
            ->with('success', 'Homework updated successfully!');
    }

    /**
     * Remove the specified homework.
     */
    public function destroy($id)
    {
        $homework = HomeworkNotice::findOrFail($id);
        $homework->delete();

        return redirect()->route('teacher.homework.index')
            ->with('success', 'Homework deleted successfully!');
    }

    /**
     * Display the specified homework.
     */
    public function show($id)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        
        if (!$teacherLogin || !$teacherLogin->teacher) {
            return redirect()->back()->with('error', 'Teacher not found');
        }
        
        $teacher = $teacherLogin->teacher;
        
        // Show homework details
        // Teacher can view homework for their assigned classes
        $classIds = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)
            ->pluck('class_id')
            ->unique();
        
        $homework = HomeworkNotice::where(function ($query) use ($classIds) {
                $query->whereHas('schoolClass', function ($subQuery) use ($classIds) {
                    $subQuery->whereIn('id', $classIds);
                });
            })
            ->with(['schoolClass', 'subject', 'section'])
            ->findOrFail($id);

        return view('teacher.homework.show', compact('homework'));
    }
}
