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
        
        // Check for Result Hold or TC Hold
        $defStage = \App\Models\DefaulterStage::where('student_id', $student->id)->first();
        $isHold = $defStage && in_array($defStage->stage, ['Result Hold', 'TC Hold']);

        if ($isHold) {
            $results = new \Illuminate\Pagination\LengthAwarePaginator(collect(), 0, 15);
            $holdMessage = 'Your academic results are on hold due to outstanding fee defaults. Please clear your dues of Rs. ' . number_format($defStage->outstanding_amount, 2) . ' to unlock.';
            return view('student.results.index', compact('results', 'holdMessage'));
        }

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

        // Check for Result Hold or TC Hold
        $defStage = \App\Models\DefaulterStage::where('student_id', $student->id)->first();
        if ($defStage && in_array($defStage->stage, ['Result Hold', 'TC Hold'])) {
            abort(403, 'Your academic results are on hold due to outstanding fee defaults.');
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

        // Check for Result Hold or TC Hold
        $defStage = \App\Models\DefaulterStage::where('student_id', $student->id)->first();
        if ($defStage && in_array($defStage->stage, ['Result Hold', 'TC Hold'])) {
            abort(403, 'Your academic results are on hold due to outstanding fee defaults.');
        }
        
        // For now, we'll return a view that can be printed
        return view('student.results.pdf', compact('result'));
    }
}
