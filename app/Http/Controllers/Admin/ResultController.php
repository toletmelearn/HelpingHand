<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\ClassManagement;
use Illuminate\Http\Request;

class ResultController extends Controller
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
        $this->authorize('viewAny', Result::class);
        $results = Result::with(['student', 'exam'])->paginate(15);
        return view('admin.results.index', compact('results'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Result::class);
        $exams = Exam::all();
        $students = Student::all();
        return view('admin.results.create', compact('exams', 'students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject' => 'required|string|max:100',
            'marks_obtained' => 'required|numeric|min:0',
            'total_marks' => 'required|numeric|min:marks_obtained',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'comments' => 'nullable|string',
            'result_status' => 'required|in:pass,fail'
        ]);

        $result = Result::create($request->all());
        
        // Update percentage, grade and status
        $result->updateResultStatus();

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Result $result)
    {
        $this->authorize('view', $result);
        return view('admin.results.show', compact('result'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Result $result)
    {
        $this->authorize('update', $result);
        $exams = Exam::all();
        $students = Student::all();
        return view('admin.results.edit', compact('result', 'exams', 'students'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Result $result)
    {
        $this->authorize('update', $result);
        
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject' => 'required|string|max:100',
            'marks_obtained' => 'required|numeric|min:0',
            'total_marks' => 'required|numeric|min:marks_obtained',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:50',
            'comments' => 'nullable|string',
            'result_status' => 'required|in:pass,fail'
        ]);

        $result->update($request->all());
        
        // Update percentage, grade and status
        $result->updateResultStatus();

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Result $result)
    {
        $this->authorize('delete', $result);
        
        $result->delete();

        return redirect()->route('admin.results.index')
                         ->with('success', 'Result deleted successfully.');
    }
}
