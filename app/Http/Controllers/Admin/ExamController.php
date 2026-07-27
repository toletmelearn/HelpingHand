<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Exam::class);
        $exams = Exam::with(['createdBy'])->paginate(10);
        return view('admin.exams.index', compact('exams'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Exam::class);
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::all();
        $teachers = Teacher::all();
        return view('admin.exams.create', compact('classes', 'subjects', 'teachers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Exam::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'exam_type' => 'required|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'subject' => 'required|string|max:100',
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'status' => 'required|in:active,scheduled,ongoing,cancelled,completed'
        ]);

        // Validate that passing marks don't exceed total marks
        if ($request->passing_marks > $request->total_marks) {
            return redirect()->back()->withErrors(['passing_marks' => 'Passing marks cannot be greater than total marks.']);
        }

        // Normalize status value to match database enum
        $normalizedStatus = $request->status === 'active' ? 'scheduled' : $request->status;

        // class_name is derived from the chosen class_id, never trusted
        // from the request -- kept populated for backward display
        // compatibility with readers that still show the free-text label.
        $schoolClass = SchoolClass::findOrFail($request->class_id);

        Exam::create(array_merge(
            $request->except(['status', 'class_name']),
            [
                'class_name' => $schoolClass->name,
                'status' => $normalizedStatus,
                'created_by' => Auth::id()
            ]
        ));

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);
        return view('admin.exams.show', compact('exam'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Exam $exam)
    {
        $this->authorize('update', $exam);
        $classes = SchoolClass::active()->orderByOrder()->get();
        $subjects = Subject::all();
        $teachers = Teacher::all();
        return view('admin.exams.edit', compact('exam', 'classes', 'subjects', 'teachers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Exam $exam)
    {
        $this->authorize('update', $exam);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'exam_type' => 'required|string|max:100',
            'class_id' => 'required|exists:school_classes,id',
            'subject' => 'required|string|max:100',
            'exam_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'status' => 'required|in:active,scheduled,ongoing,cancelled,completed'
        ]);

        // Validate that passing marks don't exceed total marks
        if ($request->passing_marks > $request->total_marks) {
            return redirect()->back()->withErrors(['passing_marks' => 'Passing marks cannot be greater than total marks.']);
        }

        // Normalize status value to match database enum
        $normalizedStatus = $request->status === 'active' ? 'scheduled' : $request->status;

        $schoolClass = SchoolClass::findOrFail($request->class_id);

        $exam->update(array_merge(
            $request->except(['status', 'class_name']),
            [
                'class_name' => $schoolClass->name,
                'status' => $normalizedStatus,
            ]
        ));

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam)
    {
        $this->authorize('delete', $exam);
        
        $exam->delete();

        return redirect()->route('admin.exams.index')
                         ->with('success', 'Exam deleted successfully.');
    }
    
    /**
     * Get exam details for AJAX requests
     */
    public function getExamDetails(Exam $exam)
    {
        return response()->json([
            'subject' => $exam->subject,
            'total_marks' => $exam->total_marks,
            'passing_marks' => $exam->passing_marks,
            'name' => $exam->name
        ]);
    }
}
