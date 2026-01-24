<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmitCard;
use App\Models\AdmitCardFormat;
use App\Models\Exam;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdmitCardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the admit cards.
     */
    public function index()
    {
        $admitCards = AdmitCard::with(['student', 'exam', 'format'])->latest()->paginate(15);
        return view('admin.admit-cards.index', compact('admitCards'));
    }
    
    /**
     * Show the form to generate admit cards.
     */
    public function create()
    {
        $exams = Exam::all();
        $formats = AdmitCardFormat::where('is_active', true)->get();
        return view('admin.admit-cards.create', compact('exams', 'formats'));
    }
    
    /**
     * Generate admit cards for selected exam and class.
     */
    public function store(Request $request)
    {
        $this->authorize('create', AdmitCard::class);
        
        $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'admit_card_format_id' => 'required|exists:admit_card_formats,id',
            'academic_session' => 'required|string|max:20',
        ]);
        
        $exam = Exam::findOrFail($request->exam_id);
        $format = AdmitCardFormat::findOrFail($request->admit_card_format_id);
        
        // Get students enrolled in the exam's class
        $students = Student::where('class', $exam->class_name)->get();
        
        $generatedCount = 0;
        $skippedCount = 0;
        $errors = [];
        
        foreach ($students as $student) {
            // Check if admit card already exists for this student and exam
            $existingAdmitCard = AdmitCard::where('student_id', $student->id)
                                          ->where('exam_id', $exam->id)
                                          ->first();
            
            if (!$existingAdmitCard) {
                // Validate student eligibility before creating admit card
                $mockAdmitCard = new AdmitCard();
                $mockAdmitCard->student = $student;
                $mockAdmitCard->exam = $exam;
                
                $validationErrors = $mockAdmitCard->validateForGeneration();
                
                if (empty($validationErrors)) {
                    // Create the admit card data
                    $admitCardData = [
                        'school_name' => config('app.name', 'School Name'),
                        'academic_session' => $request->academic_session,
                        'exam_name' => $exam->name,
                        'exam_date' => $exam->exam_date,
                        'exam_time' => $exam->start_time . ' - ' . $exam->end_time,
                        'student_name' => $student->name,
                        'roll_number' => $student->roll_number,
                        'class_name' => $student->class,
                        'section' => $student->section,
                        'dob' => $student->date_of_birth,
                        'subjects' => [$exam->subject], // Simplified for single subject exam
                        'instructions' => $exam->instructions ?? 'Follow exam instructions carefully.',
                    ];
                    
                    AdmitCard::create([
                        'student_id' => $student->id,
                        'exam_id' => $exam->id,
                        'admit_card_format_id' => $format->id,
                        'academic_session' => $request->academic_session,
                        'status' => 'draft', // Set initial status to draft
                        'validation_data' => ['validation_passed' => true, 'checks' => []],
                        'version' => 1,
                        'data' => $admitCardData,
                        'generated_by' => Auth::id(),
                    ]);
                    
                    $generatedCount++;
                } else {
                    $errors[] = "Student {$student->name} (ID: {$student->id}) - " . implode(', ', $validationErrors);
                    $skippedCount++;
                }
            }
        }
        
        $message = "Successfully generated {$generatedCount} admit cards for {$exam->name}.";
        if ($skippedCount > 0) {
            $message .= " Skipped {$skippedCount} students due to validation errors.";
        }
        
        if (!empty($errors)) {
            session()->flash('warning', implode('<br>', $errors));
        }
        
        return redirect()->route('admin.admit-cards.index')->with('success', $message);
    }
    
    /**
     * Publish selected admit cards.
     */
    public function publish($id)
    {
        $admitCard = AdmitCard::with(['student', 'exam'])->findOrFail($id);
        $this->authorize('update', $admitCard);
        
        // Validate before publishing
        $validationErrors = $admitCard->validateForGeneration();
        if (!empty($validationErrors)) {
            return redirect()->back()->with('error', 'Cannot publish: ' . implode(', ', $validationErrors));
        }
        
        if ($admitCard->transitionTo('published', Auth::id())) {
            return redirect()->back()->with('success', 'Admit card published successfully.');
        } else {
            return redirect()->back()->with('error', 'Cannot transition to published status.');
        }
    }
    
    /**
     * Lock selected admit cards.
     */
    public function lock($id)
    {
        $admitCard = AdmitCard::findOrFail($id);
        $this->authorize('update', $admitCard);
        
        if ($admitCard->transitionTo('locked', Auth::id())) {
            return redirect()->back()->with('success', 'Admit card locked successfully.');
        } else {
            return redirect()->back()->with('error', 'Cannot transition to locked status.');
        }
    }
    
    /**
     * Preview an admit card.
     */
    public function preview($id)
    {
        $admitCard = AdmitCard::with(['student', 'exam', 'format'])->findOrFail($id);
        return view('admin.admit-cards.preview', compact('admitCard'));
    }
    
    /**
     * Bulk publish admit cards.
     */
    public function bulkPublish(Request $request)
    {
        $ids = $request->input('ids', []);
        
        AdmitCard::whereIn('id', $ids)
                  ->update([
                      'status' => 'published',
                      'published_at' => now(),
                      'published_by' => Auth::id(),
                  ]);
        
        return redirect()->back()->with('success', 'Selected admit cards published successfully.');
    }
    
    /**
     * Bulk lock admit cards.
     */
    public function bulkLock(Request $request)
    {
        $ids = $request->input('ids', []);
        
        AdmitCard::whereIn('id', $ids)->update(['status' => 'locked']);
        
        return redirect()->back()->with('success', 'Selected admit cards locked successfully.');
    }
    
    /**
     * Revoke selected admit cards.
     */
    public function revoke($id)
    {
        $admitCard = AdmitCard::findOrFail($id);
        $this->authorize('update', $admitCard);
        
        if ($admitCard->transitionTo('revoked', Auth::id())) {
            return redirect()->back()->with('success', 'Admit card revoked successfully.');
        } else {
            return redirect()->back()->with('error', 'Cannot transition to revoked status.');
        }
    }
    
    /**
     * Bulk revoke admit cards.
     */
    public function bulkRevoke(Request $request)
    {
        $ids = $request->input('ids', []);
        
        $updatedCount = 0;
        foreach ($ids as $id) {
            $admitCard = AdmitCard::find($id);
            if ($admitCard && $admitCard->transitionTo('revoked', Auth::id())) {
                $updatedCount++;
            }
        }
        
        return redirect()->back()->with('success', "{$updatedCount} selected admit cards revoked successfully.");
    }
}
