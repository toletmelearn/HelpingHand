<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ResultEntryController extends Controller
{
    /**
     * Display subject-wise result entry form for teachers
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Result::class);
        
        $user = Auth::user();
        $query = Result::with(['student.class', 'student.section', 'exam']);
        
        // If user is a teacher, show only their subject results
        if ($user->roles()->where('name', 'teacher')->exists() && $user->teacher) {
            $query->where('subject', $user->teacher->subject_specialization ?? '');
        }
        
        // Apply filters
        if ($request->filled('exam_id')) {
            $query->where('exam_id', $request->exam_id);
        }
        
        if ($request->filled('class_id')) {
            $query->whereHas('student', function($q) use ($request) {
                $q->where('class_id', $request->class_id);
            });
        }
        
        if ($request->filled('subject')) {
            $query->where('subject', $request->subject);
        }
        
        $results = $query->orderBy('created_at', 'desc')->paginate(20);
        
        $exams = Exam::all();
        $classes = SchoolClass::all();
        $subjects = Subject::all();
        
        return view('results.entry.index', compact('results', 'exams', 'classes', 'subjects'));
    }

    /**
     * Show form for entering/editing subject marks
     */
    public function create(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $students = Student::with('class', 'section')->get();
        $exams = Exam::all();
        $subjects = Subject::all();
        $academicYears = ['2025-26', '2026-27', '2027-28'];
        $terms = ['Term 1', 'Term 2', 'Annual'];
        
        return view('results.entry.create', compact('students', 'exams', 'subjects', 'academicYears', 'terms'));
    }

    /**
     * Store subject marks
     */
    public function store(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'marks_obtained' => 'required|numeric|min:0|lte:total_marks',
            'total_marks' => 'required|numeric|min:1',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
            'comments' => 'nullable|string|max:500',
        ], [
            'student_id.required' => 'Please select a student',
            'exam_id.required' => 'Please select an exam',
            'subject_id.required' => 'Please select a subject',
            'marks_obtained.required' => 'Please enter marks obtained',
            'marks_obtained.lte' => 'Marks obtained cannot exceed total marks',
            'total_marks.required' => 'Please enter total marks',
        ]);

        // Convert subject_id to subject name
        $subject = Subject::findOrFail($validated['subject_id']);
        $validated['subject'] = $subject->name;
        
        // Check for duplicate entry
        $existing = Result::where([
            'student_id' => $validated['student_id'],
            'exam_id' => $validated['exam_id'],
            'subject' => $validated['subject']
        ])->first();

        if ($existing) {
            // Update existing record
            if ($existing->is_verified) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['verified' => 'This result is already verified and cannot be modified.']);
            }
            
            $existing->update([
                'marks_obtained' => $validated['marks_obtained'],
                'total_marks' => $validated['total_marks'],
                'academic_year' => $validated['academic_year'],
                'term' => $validated['term'],
                'comments' => $validated['comments'],
                'is_verified' => false, // Reset verification status
                'verified_by' => null,
                'verified_at' => null,
                'verification_comments' => null,
            ]);
            
            $existing->updateResultStatus();
            
            return redirect()->route('results.entry.index')
                ->with('success', 'Result updated successfully.');
        }

        // Create new result
        $result = new Result($validated);
        $result->updateResultStatus();
        $result->save();

        return redirect()->route('results.entry.index')
            ->with('success', 'Result entered successfully.');
    }

    /**
     * Show form for editing subject marks
     */
    public function edit(Result $result)
    {
        $this->authorize('update', $result);
        
        if ($result->is_verified) {
            return redirect()->back()
                ->with('error', 'This result is verified and cannot be edited.');
        }

        $students = Student::with('class', 'section')->get();
        $exams = Exam::all();
        $subjects = Subject::all();
        $academicYears = ['2025-26', '2026-27', '2027-28'];
        $terms = ['Term 1', 'Term 2', 'Annual'];

        return view('results.entry.edit', compact('result', 'students', 'exams', 'subjects', 'academicYears', 'terms'));
    }

    /**
     * Update subject marks
     */
    public function update(Request $request, Result $result)
    {
        $this->authorize('update', $result);
        
        if ($result->is_verified) {
            return redirect()->back()
                ->with('error', 'This result is verified and cannot be updated.');
        }

        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'marks_obtained' => 'required|numeric|min:0|lte:total_marks',
            'total_marks' => 'required|numeric|min:1',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
            'comments' => 'nullable|string|max:500',
        ], [
            'marks_obtained.lte' => 'Marks obtained cannot exceed total marks',
        ]);

        // Convert subject_id to subject name
        $subject = Subject::findOrFail($validated['subject_id']);
        $validated['subject'] = $subject->name;

        $result->update($validated);
        $result->updateResultStatus();

        return redirect()->route('results.entry.index')
            ->with('success', 'Result updated successfully.');
    }

    /**
     * Delete result entry
     */
    public function destroy(Result $result)
    {
        $this->authorize('delete', $result);
        
        if ($result->is_verified) {
            return redirect()->back()
                ->with('error', 'This result is verified and cannot be deleted.');
        }

        $result->delete();

        return redirect()->route('results.entry.index')
            ->with('success', 'Result deleted successfully.');
    }

    /**
     * Verify result entry (Admin only)
     */
    public function verify(Result $result, Request $request)
    {
        $this->authorize('verify', $result);
        
        $validated = $request->validate([
            'verification_comments' => 'nullable|string|max:500',
        ]);

        $result->update([
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'verification_comments' => $validated['verification_comments'] ?? null,
        ]);

        return redirect()->back()
            ->with('success', 'Result verified successfully.');
    }

    /**
     * Unverify result entry (Admin only)
     */
    public function unverify(Result $result)
    {
        $this->authorize('verify', $result);
        
        $result->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'verification_comments' => null,
        ]);

        return redirect()->back()
            ->with('success', 'Result unverified successfully.');
    }

    /**
     * Bulk entry form
     */
    public function bulkEntryForm(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $exam = Exam::find($request->exam_id);
        $class = SchoolClass::find($request->class_id);
        $subject = Subject::find($request->subject_id);
        
        if (!$exam || !$class || !$subject) {
            return redirect()->back()->with('error', 'Please select exam, class, and subject.');
        }
        
        $students = Student::where('class_id', $class->id)->get();
        
        return view('results.entry.bulk-entry', compact('exam', 'class', 'subject', 'students'));
    }

    /**
     * Process bulk entry
     */
    public function processBulkEntry(Request $request)
    {
        $this->authorize('create', Result::class);
        
        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'required|string|max:20',
            'term' => 'required|string|max:20',
            'student_marks' => 'required|array',
            'student_marks.*.student_id' => 'required|exists:students,id',
            'student_marks.*.marks_obtained' => 'required|numeric|min:0|lte:student_marks.*.total_marks',
            'student_marks.*.total_marks' => 'required|numeric|min:1',
        ], [
            'student_marks.*.marks_obtained.lte' => 'Marks obtained cannot exceed total marks',
        ]);

        $subject = Subject::findOrFail($validated['subject_id']);
        
        DB::beginTransaction();
        try {
            foreach ($validated['student_marks'] as $studentMark) {
                $existing = Result::where([
                    'student_id' => $studentMark['student_id'],
                    'exam_id' => $validated['exam_id'],
                    'subject' => $subject->name
                ])->first();

                if ($existing && $existing->is_verified) {
                    continue; // Skip verified results
                }

                $data = [
                    'student_id' => $studentMark['student_id'],
                    'exam_id' => $validated['exam_id'],
                    'subject' => $subject->name,
                    'marks_obtained' => $studentMark['marks_obtained'],
                    'total_marks' => $studentMark['total_marks'],
                    'academic_year' => $validated['academic_year'],
                    'term' => $validated['term'],
                    'is_verified' => false,
                ];

                if ($existing) {
                    $existing->update($data);
                    $existing->updateResultStatus();
                } else {
                    $result = new Result($data);
                    $result->updateResultStatus();
                    $result->save();
                }
            }
            
            DB::commit();
            return redirect()->route('results.entry.index')
                ->with('success', 'Bulk entry completed successfully.');
                
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error processing bulk entry: ' . $e->getMessage());
        }
    }
}