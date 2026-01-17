<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Display a listing of all students.
     */
    public function index()
    {
        $students = Student::all();
        return view('students.index', ['students' => $students]);
    }

    /**
     * Show the form for creating a new student.
     */
    public function create()
    {
        return view('students.create');
    }

    /**
     * Store a newly created student in database.
     */
    public function store(Request $request)
    {
        // Validate the request data
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'aadhar_number' => 'required|digits:12|unique:students',
            'address' => 'required|string',
            'phone' => 'required|digits:10'
        ]);

        // Create the student
        Student::create($validated);

        // Redirect with success message
        return redirect()->route('students.create')
                         ->with('success', 'Student successfully added!');
    }

    /**
     * Display the specified student.
     */
    public function show($id)
    {
        $student = Student::findOrFail($id);
        return view('students.show', ['student' => $student]);
    }

    /**
     * Show the form for editing a student.
     */
    public function edit($id)
    {
        $student = Student::findOrFail($id);
        return view('students.edit', ['student' => $student]);
    }

    /**
     * Update the specified student.
     */
    public function update(Request $request, $id)
    {
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'mother_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'aadhar_number' => 'required|digits:12|unique:students,aadhar_number,'.$id,
            'address' => 'required|string',
            'phone' => 'required|digits:10'
        ]);

        $student->update($validated);
        
        return redirect()->route('students.index')
                         ->with('success', 'Student updated successfully!');
    }

    /**
     * Remove the specified student.
     */
    public function destroy($id)
    {
        $student = Student::findOrFail($id);
        $student->delete();
        
        return redirect()->route('students.index')
                         ->with('success', 'Student deleted successfully!');
    }
}