<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alumni;
use App\Models\Student;
use Illuminate\Http\Request;

class AdminAlumniController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

    public function index()
    {
        $alumniList = Alumni::with('student')->get();
        $students = Student::all(); // Potential candidates for graduation

        return view('admin.alumni.index', compact('alumniList', 'students'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id|unique:alumni,student_id',
            'graduation_year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'current_occupation' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'feedback' => 'nullable|string|max:1000',
        ]);

        Alumni::create($request->all());

        return redirect()->route('admin.alumni.index')->with('success', 'Student archived as Alumni successfully.');
    }
}
