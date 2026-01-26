<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailyTeachingWork;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentDailyTeachingWorkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        $student = Auth::user()->student;
        
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }
        
        $query = DailyTeachingWork::with(['teacher', 'createdBy'])
                    ->where('class_name', $student->class)
                    ->where('section', $student->section)
                    ->where('status', 'published')
                    ->orderBy('date', 'desc');
        
        // Apply filters
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        
        $dailyWorks = $query->paginate(15);
        
        // Get unique subjects for the student's class
        $subjects = DailyTeachingWork::where('class_name', $student->class)
                                  ->where('section', $student->section)
                                  ->select('subject')
                                  ->distinct()
                                  ->pluck('subject');
        
        return view('student.daily-teaching-work.index', compact('dailyWorks', 'subjects'));
    }
    
    public function show(DailyTeachingWork $dailyTeachingWork)
    {
        $student = Auth::user()->student;
        
        if (!$student || $dailyTeachingWork->class_name !== $student->class || $dailyTeachingWork->section !== $student->section) {
            abort(403, 'Unauthorized to view this daily teaching work.');
        }
        
        if ($dailyTeachingWork->status !== 'published') {
            abort(403, 'This daily teaching work is not available yet.');
        }
        
        $dailyTeachingWork->load(['teacher', 'createdBy']);
        
        return view('student.daily-teaching-work.show', compact('dailyTeachingWork'));
    }
    
    public function downloadAttachment(DailyTeachingWork $dailyTeachingWork, $index)
    {
        $student = Auth::user()->student;
        
        if (!$student || $dailyTeachingWork->class_name !== $student->class || $dailyTeachingWork->section !== $student->section) {
            abort(403, 'Unauthorized to download this attachment.');
        }
        
        if ($dailyTeachingWork->status !== 'published') {
            abort(403, 'This daily teaching work is not available yet.');
        }
        
        $attachment = $dailyTeachingWork->attachments[$index] ?? null;
        
        if (!$attachment) {
            abort(404, 'Attachment not found');
        }
        
        $filePath = storage_path('app/public/' . $attachment['path']);
        
        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }
        
        return response()->download($filePath, $attachment['name']);
    }
}
