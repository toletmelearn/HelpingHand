<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentResultController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display student's own results
     */
    public function index()
    {
        $student = Auth::user()->student;
        
        $results = Result::with(['exam'])
                       ->where('student_id', $student->id)
                       ->paginate(15);
        
        return view('student.results.index', compact('results'));
    }
    
    /**
     * Show a specific result
     */
    public function show(Result $result)
    {
        $student = Auth::user()->student;
        
        // Verify that this result belongs to the current student
        if ($result->student_id != $student->id) {
            abort(403, 'You are not authorized to view this result.');
        }
        
        return view('student.results.show', compact('result'));
    }
    
    /**
     * Generate printable result
     */
    public function generatePDF(Result $result)
    {
        $student = Auth::user()->student;
        
        // Verify that this result belongs to the current student
        if ($result->student_id != $student->id) {
            abort(403, 'You are not authorized to view this result.');
        }
        
        // For now, we'll return a view that can be printed
        return view('student.results.pdf', compact('result'));
    }
}
