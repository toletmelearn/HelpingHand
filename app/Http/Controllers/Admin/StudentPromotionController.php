<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\ClassManagement;
use App\Models\AcademicSession;
use App\Models\StudentStatus;
use App\Models\StudentPromotionLog;
use App\Models\SchoolClass;
use App\Models\FeeStructure;
use App\Services\StructureAdjustmentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentPromotionController extends Controller
{
    public function index()
    {
        $currentSession = AcademicSession::current()->first();

        // Grouping by the legacy raw `students.class` text column (in
        // addition to class_id) used to fragment a single real class into
        // several rows -- e.g. class_id=22 is one class ("Class 11" in
        // school_classes) but showed as three separate rows ("Class 11",
        // "XI", "XI-") because those are three different strings that ended
        // up in that free-text column over time. Group by the canonical FK
        // only (COALESCE'd the same way every other class listing in this
        // app handles the class_id/school_class_id split) and resolve the
        // display name from school_classes, never the stale string.
        $studentsByClass = Student::select(
                DB::raw('COALESCE(school_class_id, class_id) as class_id'),
                DB::raw('COUNT(*) as total')
            )
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNotNull('class_id')->orWhereNotNull('school_class_id');
            })
            ->groupBy(DB::raw('COALESCE(school_class_id, class_id)'))
            ->get();

        $studentsByClass->each(function ($item) {
            $item->school_class_id = $item->class_id;
        });
        $studentsByClass->load(['schoolClass' => function ($query) {
            $query->select('id', 'name', 'class_order');
        }]);
        $studentsByClass = $studentsByClass->sortBy(
            fn($item) => $item->schoolClass->class_order ?? PHP_INT_MAX
        )->values();

        $recentPromotions = StudentPromotionLog::with(['student', 'promotedBy', 'academicSession'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.student-promotion.index', compact('studentsByClass', 'currentSession', 'recentPromotions'));
    }

    /*
     * SHOW CREATE FORM
     * Updated to pre-load data from SchoolClass table (which has 19 active classes)
     */
    public function create(Request $request)
    {
        $currentSession = AcademicSession::current()->first();
        
        // SOURCE: SchoolClass (Table school_classes, 19 rows) - Always load these
        $currentClasses = SchoolClass::active()->orderByOrder()->get();
        
        // ALL CLASSES: include inactive for robust fallback - Always load these
        $allClasses = SchoolClass::orderByOrder()->get();
        
        $selectedClass = null;
        $preLoadedStudents = collect([]);
        $preLoadedDestinations = collect([]);
        $fromClassParam = $request->get('from_class');

        if ($fromClassParam) {
            $selectedClass = SchoolClass::where('id', $fromClassParam)
                                      ->orWhere('name', $fromClassParam)
                                      ->orWhere('name', str_replace('+', ' ', $fromClassParam))
                                      ->first();

            if ($selectedClass) {
                // 1. Fetch Students
                $preLoadedStudents = Student::where(function($q) use ($selectedClass) {
                                            $q->where('class_id', $selectedClass->id)
                                              ->orWhere('class', $selectedClass->name)
                                              ->orWhere('class', 'like', $selectedClass->name . '%');
                                        })
                                        ->select('id', 'name', 'roll_number')
                                        ->orderBy('name')
                                        ->get();

                // 2. Fetch Destinations from SchoolClass (Where data actually lives)
                // Filter: Active, Order > Current (if strict) or All Others, or finally all classes
                $preLoadedDestinations = SchoolClass::where('is_active', true)
                                              ->where('class_order', '>', $selectedClass->class_order ?? 0)
                                              ->orderBy('class_order')
                                              ->get(['id', 'name']);

                // Fallback: If no higher classes found, show ALL other active classes
                if ($preLoadedDestinations->isEmpty()) {
                    $preLoadedDestinations = SchoolClass::where('is_active', true)
                                                  ->where('id', '!=', $selectedClass->id)
                                                  ->orderBy('class_order')
                                                  ->get(['id', 'name']);
                }
                
                // Final Fallback: If still empty (e.g., only one active class exists), show ALL other classes (including inactive)
                if ($preLoadedDestinations->isEmpty()) {
                    $preLoadedDestinations = SchoolClass::where('id', '!=', $selectedClass->id)
                                                   ->orderBy('class_order')
                                                   ->get(['id', 'name']);
                }
            }
        }
        
        return view('admin.student-promotion.create', compact('currentSession', 'currentClasses', 'allClasses', 'selectedClass', 'preLoadedStudents', 'preLoadedDestinations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'from_class' => 'required|integer|exists:school_classes,id',
            'to_class' => 'required|integer|exists:school_classes,id', // Reverted to school_classes
            'students' => 'required|array|min:1',
            'students.*' => 'exists:students,id',
            'remarks' => 'nullable|string|max:1000',
        ], [
            'academic_session_id.required' => 'Please select an academic session.',
            'from_class.required' => 'Please select the source class.',
            'to_class.required' => 'Please select the destination class.',
            'students.required' => 'Please select at least one student to promote.',
        ]);

        $sessionId = $request->academic_session_id;

        // Get Both from SchoolClass (Data Confirmed)
        $sourceClass = SchoolClass::findOrFail($request->from_class);
        $destinationClass = SchoolClass::findOrFail($request->to_class);
        
        if (($destinationClass->class_order ?? 0) <= ($sourceClass->class_order ?? 0)) {
            return back()->withErrors(['to_class' => 'Destination class must be higher than source class.'])->withInput();
        }
        
        $students = Student::whereIn('id', $request->students)->get();
        $promotedBy = Auth::id();
        $remarks = $request->remarks;

        // Promotion used to only sync the class fields -- it never touched
        // fee assignments at all, so a promoted student kept accruing dues
        // under the OLD class's structure and never got billed for the new
        // one unless a staff member separately re-ran fee assignment by
        // hand (that's what the existing test for this does). This resolves
        // the destination class's active structure for the session and
        // reuses the already-tested, previously-orphaned
        // StructureAdjustmentService::changeFeeStructure() to do it
        // properly: drop the old structure's future-dated unpaid debits and
        // bill the new one from today. No structure for the destination
        // class yet is a normal, expected state and must never block
        // promotion itself.
        $session = AcademicSession::find($sessionId);
        $newFeeStructure = ($session && \Illuminate\Support\Facades\Schema::hasTable('fee_structures'))
            ? FeeStructure::where('class_name', $destinationClass->name)
                ->where('status', 'active')
                ->where(function ($q) use ($session) {
                    $q->where('academic_year', $session->code)
                      ->orWhere('academic_year', $session->name);
                })
                ->latest('id')
                ->first()
            : null;
        $structureAdjustment = new StructureAdjustmentService();
        $today = now()->toDateString();

        DB::transaction(function () use ($students, $destinationClass, $sourceClass, $sessionId, $promotedBy, $remarks, $newFeeStructure, $structureAdjustment, $today) {
            foreach ($students as $student) {
                $student->class_id = $destinationClass->id;
                $student->school_class_id = $destinationClass->id;
                $student->class = $destinationClass->name;
                $student->save();

                StudentPromotionLog::create([
                    'student_id' => $student->id,
                    'academic_session_id' => $sessionId,
                    'from_class' => $sourceClass->name,
                    'to_class' => $destinationClass->name,
                    'promoted_by' => $promotedBy,
                    'promoted_at' => now(),
                    'remarks' => $remarks,
                ]);

                if ($newFeeStructure) {
                    $structureAdjustment->changeFeeStructure($student, $newFeeStructure, $today);
                }
            }
        });

        return redirect()->route('admin.student-promotions.index')
            ->with('success', count($request->students) . ' students promoted from ' . $sourceClass->name . ' to ' . $destinationClass->name);
    }

    public function getStudentsByClass($class)
    {
        $id = ($class instanceof SchoolClass) ? $class->id : $class;
        $sourceClass = SchoolClass::find($id);
        
        $students = Student::where(function($q) use ($id, $sourceClass) {
                    $q->where('class_id', $id);
                    if ($sourceClass && $sourceClass->name) {
                        $q->orWhere('class', $sourceClass->name)
                          ->orWhere('class', 'like', $sourceClass->name . '%');
                    }
                })
            ->select('id', 'name', 'roll_number')
            ->orderBy('name')
            ->get();
        
        return response()->json(['students' => $students]);
    }

    /*
    // OLD FUNCTION - COMMENTED OUT AS REQUESTED
    // ...
    */

    /*
     * AJAX Get Destination Classes
     * Reverted to use SchoolClass table (19 rows detected)
     */
    public function getDestinationClasses($class)
    {
        Log::info('getDestinationClasses called', ['param' => $class]);

        $id = ($class instanceof SchoolClass) ? $class->id : $class;
        $sourceClass = SchoolClass::find($id);
        
        Log::info('Resolved Source Class', [
            'id' => $id, 
            'found' => $sourceClass ? 'Yes' : 'No',
            'name' => $sourceClass->name ?? 'N/A',
            'order' => $sourceClass->class_order ?? 'N/A'
        ]);

        if ($sourceClass) {
            // Try Strict Promotion First
            $destinations = SchoolClass::where('is_active', true)
                ->where('class_order', '>', $sourceClass->class_order)
                ->orderBy('class_order')
                ->get(['id', 'name']);
            
            Log::info('Strict Query Result', ['count' => $destinations->count()]);

            // Fallback: If strict yields nothing, return ALL others
            if ($destinations->isEmpty()) {
                Log::info('Strict failed, trying Fallback (All other active classes)');
                $destinations = SchoolClass::where('is_active', true)
                    ->where('id', '!=', $id)
                    ->orderBy('class_order')
                    ->get(['id', 'name']);
            }
            
            // Final Fallback: Include inactive if still empty
            if ($destinations->isEmpty()) {
                Log::info('Active fallback empty, returning all classes excluding source');
                $destinations = SchoolClass::where('id', '!=', $id)
                    ->orderBy('class_order')
                    ->get(['id', 'name']);
            }
            
            Log::info('Final Result', ['count' => $destinations->count()]);
            return response()->json($destinations);
        }

        // If no source class known, return all active classes
        Log::info('Source class not found, returning all active classes');
        return response()->json(SchoolClass::where('is_active', true)->orderBy('class_order')->get(['id', 'name']));
    }

    public function studentHistory($studentId)
    {
        $student = Student::findOrFail($studentId);
        $promotions = StudentPromotionLog::where('student_id', $studentId)
            ->with(['academicSession', 'promotedBy'])
            ->orderBy('promoted_at', 'desc')
            ->get();
        
        return view('admin.student-promotion.history', compact('student', 'promotions'));
    }

    public function markAsPassedOut(Request $request, $studentId)
    {
        $request->validate(['remarks' => 'nullable|string']);

        $student = Student::findOrFail($studentId);
        $currentSession = AcademicSession::current()->first();
        $originalClassLabel = $student->schoolClass->name ?? $student->class ?? 'Unknown';
        $passedOutBy = Auth::id();
        $remarks = $request->remarks ?? 'Student marked as passed out';
        $today = now()->toDateString();

        DB::transaction(function () use ($student, $currentSession, $originalClassLabel, $passedOutBy, $remarks, $today) {
            $student->class_id = null;
            $student->school_class_id = null;
            $student->class = 'Passed Out';
            $student->section_id = null;
            $student->section = null;
            $student->save();

            StudentStatus::create([
                'student_id' => $student->id,
                'status' => 'passed_out',
                'status_date' => now()->toDateString(),
                'reason' => 'Passed out',
                'remarks' => $remarks,
                'issued_by' => $passedOutBy !== null ? (string) $passedOutBy : null,
            ]);

            StudentPromotionLog::create([
                'student_id' => $student->id,
                'academic_session_id' => $currentSession?->id,
                'from_class' => $originalClassLabel,
                'to_class' => 'Passed Out',
                'promoted_by' => $passedOutBy,
                'promoted_at' => now(),
                'remarks' => $remarks,
            ]);

            // This used to only clear the class fields and log the status
            // change -- a passed-out student's future-dated ledger debits
            // (next month's tuition, next term's fees, etc.) kept accruing
            // forever with nothing to ever stop them. The code that prunes
            // these correctly (StructureAdjustmentService::withdrawStudent())
            // already existed, fully tested, but only ever fired via a hard
            // Student::delete() -- not how staff actually mark someone as
            // passed out. Past-dated (already-due) debits are deliberately
            // left untouched -- this only stops FUTURE billing, it doesn't
            // waive money already owed.
            if (\Illuminate\Support\Facades\Schema::hasTable('student_fee_ledgers')) {
                (new \App\Services\StructureAdjustmentService())->withdrawStudent($student, $today);
            }
        });

        return redirect()->route('admin.student-promotions.index')
            ->with('success', 'Student ' . $student->name . ' marked as passed out');
    }
}
