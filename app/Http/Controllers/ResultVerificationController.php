<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Result;
use App\Models\Student;
use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResultVerificationController extends Controller
{
    /**
     * Display verification dashboard
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Result::class);
        
        $query = Student::with(['class', 'section', 'results.exam']);
        
        // Apply filters
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        
        if ($request->filled('exam_id')) {
            $query->whereHas('results', function($q) use ($request) {
                $q->where('exam_id', $request->exam_id);
            });
        }
        
        $students = $query->paginate(20);
        
        // Enhance students with verification stats
        $students->getCollection()->transform(function ($student) use ($request) {
            $examId = $request->exam_id;
            
            $studentResults = $student->results()
                ->when($examId, function($q) use ($examId) {
                    $q->where('exam_id', $examId);
                })->get();
            
            $totalSubjects = $studentResults->count();
            $verifiedCount = $studentResults->where('is_verified', true)->count();
            $pendingCount = $totalSubjects - $verifiedCount;
            
            $student->total_subjects = $totalSubjects;
            $student->verified_count = $verifiedCount;
            $student->pending_count = $pendingCount;
            $student->verification_progress = $totalSubjects > 0 ? round(($verifiedCount / $totalSubjects) * 100, 1) : 0;
            $student->all_verified = $pendingCount === 0 && $totalSubjects > 0;
            
            return $student;
        });
        
        $classes = SchoolClass::all();
        $exams = Exam::all();
        
        return view('results.verification.index', compact('students', 'classes', 'exams'));
    }

    /**
     * Show detailed verification page for a student
     */
    public function show($studentId, Request $request)
    {
        $this->authorize('viewAny', Result::class);
        
        $student = Student::with(['class', 'section'])->findOrFail($studentId);
        $examId = $request->exam_id;
        
        $results = $student->results()
            ->with('exam')
            ->when($examId, function($q) use ($examId) {
                $q->where('exam_id', $examId);
            })
            ->orderBy('subject')
            ->get();
        
        $exams = Exam::all();
        $selectedExam = $examId ? Exam::find($examId) : null;
        
        return view('results.verification.show', compact('student', 'results', 'exams', 'selectedExam'));
    }

    /**
     * Bulk verify results
     */
    public function bulkVerify(Request $request)
    {
        $this->authorize('verify', Result::class);
        
        $validated = $request->validate([
            'result_ids' => 'required|array',
            'result_ids.*' => 'exists:results,id',
            'verification_comments' => 'nullable|string|max:500',
        ]);
        
        $results = Result::whereIn('id', $validated['result_ids'])->get();
        
        DB::beginTransaction();
        try {
            foreach ($results as $result) {
                if (!$result->is_verified) {
                    $result->update([
                        'is_verified' => true,
                        'verified_by' => Auth::id(),
                        'verified_at' => now(),
                        'verification_comments' => $validated['verification_comments'] ?? null,
                    ]);
                }
            }
            
            DB::commit();
            return redirect()->back()
                ->with('success', count($results) . ' results verified successfully.');
                
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Error verifying results: ' . $e->getMessage());
        }
    }

    /**
     * Bulk unverify results
     */
    public function bulkUnverify(Request $request)
    {
        $this->authorize('verify', Result::class);
        
        $validated = $request->validate([
            'result_ids' => 'required|array',
            'result_ids.*' => 'exists:results,id',
        ]);
        
        Result::whereIn('id', $validated['result_ids'])->update([
            'is_verified' => false,
            'verified_by' => null,
            'verified_at' => null,
            'verification_comments' => null,
        ]);
        
        return redirect()->back()
            ->with('success', count($validated['result_ids']) . ' results unverified successfully.');
    }

    /**
     * Get verification statistics
     */
    public function statistics(Request $request)
    {
        $this->authorize('viewAny', Result::class);
        
        $examId = $request->exam_id;
        
        $stats = [
            'total_results' => Result::when($examId, function($q) use ($examId) {
                $q->where('exam_id', $examId);
            })->count(),
            
            'verified_results' => Result::when($examId, function($q) use ($examId) {
                $q->where('exam_id', $examId);
            })->where('is_verified', true)->count(),
            
            'pending_results' => Result::when($examId, function($q) use ($examId) {
                $q->where('exam_id', $examId);
            })->where('is_verified', false)->count(),
            
            'total_students' => Student::whereHas('results', function($q) use ($examId) {
                $q->when($examId, function($q2) use ($examId) {
                    $q2->where('exam_id', $examId);
                });
            })->count(),
            
            'fully_verified_students' => Student::whereHas('results', function($q) use ($examId) {
                $q->when($examId, function($q2) use ($examId) {
                    $q2->where('exam_id', $examId);
                })->where('is_verified', false);
            }, '=', 0)->count(),
        ];
        
        $stats['verification_rate'] = $stats['total_results'] > 0 ? 
            round(($stats['verified_results'] / $stats['total_results']) * 100, 1) : 0;
            
        $stats['student_completion_rate'] = $stats['total_students'] > 0 ? 
            round(($stats['fully_verified_students'] / $stats['total_students']) * 100, 1) : 0;
        
        return response()->json($stats);
    }
}