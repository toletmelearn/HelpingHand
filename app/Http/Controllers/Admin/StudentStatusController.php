<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentStatus;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentStatusController extends Controller
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
        $studentStatuses = StudentStatus::with('student')->orderBy('created_at', 'desc')->get();
        return view('admin.student-statuses.index', compact('studentStatuses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $students = Student::orderBy('name')->get();
        return view('admin.student-statuses.create', compact('students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'status' => 'required|string|in:passed_out,tc_issued,left_school,active,inactive',
            'status_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'document_number' => 'nullable|string|max:100',
            'document_issue_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        StudentStatus::create($validator->validated());
        
        return redirect()->route('admin.student-statuses.index')
            ->with('success', 'Student status updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $studentStatus = StudentStatus::with('student')->findOrFail($id);
        return view('admin.student-statuses.show', compact('studentStatus'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $studentStatus = StudentStatus::findOrFail($id);
        $students = Student::orderBy('name')->get();
        return view('admin.student-statuses.edit', compact('studentStatus', 'students'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $studentStatus = StudentStatus::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|exists:students,id',
            'status' => 'required|string|in:passed_out,tc_issued,left_school,active,inactive',
            'status_date' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'document_number' => 'nullable|string|max:100',
            'document_issue_date' => 'nullable|date',
            'issued_by' => 'nullable|string|max:100',
        ]);
        
        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }
        
        $studentStatus->update($validator->validated());
        
        return redirect()->route('admin.student-statuses.index')
            ->with('success', 'Student status updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $studentStatus = StudentStatus::findOrFail($id);
        $studentStatus->delete();
        
        return redirect()->route('admin.student-statuses.index')
            ->with('success', 'Student status deleted successfully.');
    }
}