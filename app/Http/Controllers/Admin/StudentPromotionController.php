<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\ClassManagement;
use App\Models\AcademicSession;
use App\Models\StudentPromotionLog;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentPromotionController extends Controller
{
    /**
     * Display a listing of students eligible for promotion.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get current academic session
        $currentSession = AcademicSession::current()->first();
        
        // Get students grouped by current class
        $studentsByClass = Student::select('class', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->groupBy('class')
            ->orderBy('class')
            ->get();
        
        $classes = ClassManagement::orderBy('name')->get();
        
        // Get recent promotion logs
        $recentPromotions = StudentPromotionLog::with(['student', 'promotedBy', 'academicSession'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('admin.student-promotion.index', compact('studentsByClass', 'classes', 'currentSession', 'recentPromotions'));
    }

    /**
     * Show the form for promoting students.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $currentSession = AcademicSession::current()->first();
        $currentClasses = SchoolClass::with('students')
            ->whereHas('students')
            ->active()
            ->orderByOrder()
            ->get();
        $allClasses = SchoolClass::active()->orderByOrder()->get();
        
        return view('admin.student-promotion.create', compact('currentSession', 'currentClasses', 'allClasses'));
    }

    /**
     * Promote students to next class.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'from_class' => 'required|integer|exists:school_classes,id',
            'to_class' => 'required|integer|exists:school_classes,id',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'students' => 'required|array',
            'students.*' => 'exists:students,id',
            'remarks' => 'nullable|string',
        ]);

        $currentSession = AcademicSession::current()->first();
        $sessionId = $request->academic_session_id ?? $currentSession?->id;
        
        // Get source and destination classes
        $sourceClass = SchoolClass::findOrFail($request->from_class);
        $destinationClass = SchoolClass::findOrFail($request->to_class);
        
        // Verify that destination class has higher order
        if ($destinationClass->class_order <= $sourceClass->class_order) {
            return redirect()->back()
                ->with('error', 'Destination class must have higher class order than source class');
        }
        
        // Get students before update
        $students = Student::whereIn('id', $request->students)->get();
        
        // Update selected students' class_id and class name
        Student::whereIn('id', $request->students)
            ->update([
                'class_id' => $request->to_class,
                'class' => $destinationClass->name
            ]);

        // Log the promotions
        foreach ($students as $student) {
            StudentPromotionLog::create([
                'student_id' => $student->id,
                'academic_session_id' => $sessionId,
                'from_class' => $sourceClass->name,
                'to_class' => $destinationClass->name,
                'promoted_by' => Auth::id(),
                'promoted_at' => now(),
                'remarks' => $request->remarks,
            ]);
        }

        return redirect()->route('admin.student-promotions.index')
            ->with('success', count($request->students) . ' students promoted from ' . $sourceClass->name . ' to ' . $destinationClass->name);
    }

    /**
     * Get students by class via AJAX.
     *
     * @param  int  $classId
     * @return \Illuminate\Http\Response
     */
    public function getStudentsByClass($classId)
    {
        $students = Student::where('class_id', $classId)->get();
        
        return response()->json([
            'students' => $students
        ]);
    }

    /**
     * Get eligible destination classes for promotion.
     *
     * @param  int  $classId
     * @return \Illuminate\Http\Response
     */
    public function getDestinationClasses($classId)
    {
        $sourceClass = SchoolClass::findOrFail($classId);

        $destinationClasses = SchoolClass::where('is_active', 1)
            ->where('class_order', '>', $sourceClass->class_order)
            ->orderBy('class_order')
            ->get(['id', 'name']);

        return response()->json($destinationClasses);
    }

    /**
     * Show promotion history for a specific student.
     *
     * @param  int  $studentId
     * @return \Illuminate\Http\Response
     */
    public function studentHistory($studentId)
    {
        $student = Student::findOrFail($studentId);
        $promotions = StudentPromotionLog::where('student_id', $studentId)
            ->with(['academicSession', 'promotedBy'])
            ->orderBy('promoted_at', 'desc')
            ->get();
        
        return view('admin.student-promotion.history', compact('student', 'promotions'));
    }

    /**
     * Mark student as passed out.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $studentId
     * @return \Illuminate\Http\Response
     */
    public function markAsPassedOut(Request $request, $studentId)
    {
        $request->validate([
            'remarks' => 'nullable|string',
        ]);

        $student = Student::findOrFail($studentId);
        $currentSession = AcademicSession::current()->first();
        
        // Update student status
        $student->update([
            'status' => 'passed_out',
            'class_id' => null,
            'class' => 'Passed Out'
        ]);

        // Log the action
        StudentPromotionLog::create([
            'student_id' => $student->id,
            'academic_session_id' => $currentSession?->id,
            'from_class' => $student->schoolClass->name ?? $student->class,
            'to_class' => 'Passed Out',
            'promoted_by' => Auth::id(),
            'promoted_at' => now(),
            'remarks' => $request->remarks ?? 'Student marked as passed out',
        ]);

        return redirect()->route('admin.student-promotions.index')
            ->with('success', 'Student ' . $student->name . ' marked as passed out');
    }
}