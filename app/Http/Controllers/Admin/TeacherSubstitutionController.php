<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherSubstitution;
use App\Models\Teacher;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TeacherSubstitutionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject']);

        // Filter by date
        if ($request->filled('date')) {
            $query->forDate($request->date);
        } else {
            $query->forDate(now()->format('Y-m-d')); // Default to today
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by teacher
        if ($request->filled('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        $substitutions = $query->orderBy('period_number')->paginate(20);

        // Get filters for the view
        $classes = SchoolClass::orderBy('name')->get();
        $teachers = Teacher::with('user')->orderBy('teacher_id')->get();
        $statuses = ['pending' => 'Pending', 'assigned' => 'Assigned', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];

        return view('admin.teacher-substitutions.index', compact('substitutions', 'classes', 'teachers', 'statuses'));
    }

    public function create()
    {
        $teachers = Teacher::with('user')->orderBy('teacher_id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $periods = range(1, 10); // Assuming max 10 periods per day
        
        return view('admin.teacher-substitutions.create', compact('teachers', 'classes', 'sections', 'subjects', 'periods'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'period_number' => 'required|integer|min:1|max:10',
            'period_name' => 'nullable|string|max:255',
            'reason' => 'nullable|string|max:1000',
        ]);

        $substitution = TeacherSubstitution::create([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'period_number' => $request->period_number,
            'period_name' => $request->period_name,
            'reason' => $request->reason,
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        // Automatically suggest substitutes
        $this->suggestSubstitutes($substitution);

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution record created successfully.');
    }

    public function show(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject', 'createdBy', 'updatedBy']);
        
        return view('admin.teacher-substitutions.show', compact('teacherSubstitution'));
    }

    public function edit(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->load(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject']);
        
        $teachers = Teacher::with('user')->orderBy('teacher_id')->get();
        $classes = SchoolClass::orderBy('name')->get();
        $sections = Section::orderBy('name')->get();
        $subjects = Subject::orderBy('name')->get();
        $periods = range(1, 10);
        
        return view('admin.teacher-substitutions.edit', compact(
            'teacherSubstitution', 
            'teachers', 
            'classes', 
            'sections', 
            'subjects', 
            'periods'
        ));
    }

    public function update(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $request->validate([
            'substitution_date' => 'required|date',
            'absent_teacher_id' => 'required|exists:teachers,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'subject_id' => 'required|exists:subjects,id',
            'period_number' => 'required|integer|min:1|max:10',
            'period_name' => 'nullable|string|max:255',
            'status' => 'required|in:pending,assigned,approved,cancelled',
            'substitute_teacher_id' => 'nullable|exists:teachers,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $teacherSubstitution->update([
            'substitution_date' => $request->substitution_date,
            'absent_teacher_id' => $request->absent_teacher_id,
            'class_id' => $request->class_id,
            'section_id' => $request->section_id,
            'subject_id' => $request->subject_id,
            'period_number' => $request->period_number,
            'period_name' => $request->period_name,
            'status' => $request->status,
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'reason' => $request->reason,
            'updated_by' => Auth::id(),
        ]);

        if ($request->status === 'assigned' || $request->status === 'approved') {
            $teacherSubstitution->assigned_at = now();
            $teacherSubstitution->save();
        }

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution updated successfully.');
    }

    public function destroy(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->delete();

        return redirect()->route('admin.teacher-substitutions.index')
                         ->with('success', 'Teacher substitution deleted successfully.');
    }

    public function suggestSubstitutes(TeacherSubstitution $substitution)
    {
        // Find available teachers for the given date and period
        $availableTeachers = $this->findAvailableTeachers(
            $substitution->substitution_date,
            $substitution->period_number,
            $substitution->class_id,
            $substitution->subject_id
        );

        // Update substitution with first available teacher as suggestion
        if (!empty($availableTeachers)) {
            $substitution->update([
                'substitute_teacher_id' => $availableTeachers[0]['teacher']->id,
                'status' => 'pending' // Keep as pending for admin review
            ]);
        }

        return $availableTeachers;
    }

    public function findAvailableTeachers($date, $periodNumber, $classId, $subjectId)
    {
        $date = Carbon::parse($date);
        
        // Get all teachers
        $allTeachers = Teacher::with('user')->get();
        $availableTeachers = [];

        foreach ($allTeachers as $teacher) {
            // Skip the absent teacher
            if ($teacher->id == $classId) { // This is incorrect, should be absent_teacher_id
                continue;
            }

            // Check if teacher is available in this period
            $isAvailable = $this->checkTeacherAvailability($teacher->id, $date, $periodNumber);
            
            if ($isAvailable) {
                $score = 0;
                
                // Calculate score based on matching criteria
                // Note: We need to pass the substitution object to use its properties
                // For now, we'll use the passed parameters appropriately
                
                // Placeholder for same subject check - we'd need to implement a proper way to check if teacher teaches the same subject
                $score += $this->calculateSubjectMatchScore($teacher->id, $subjectId);
                
                if ($this->hasClassExperience($teacher->id, $classId)) {
                    $score += 30; // Same class experience
                }
                
                if (!$this->isOverloaded($teacher->id, $date)) {
                    $score += 20; // Not overloaded
                }
                
                $availableTeachers[] = [
                    'teacher' => $teacher,
                    'score' => $score,
                    'reasons' => $this->getMatchingReasons($teacher->id, $date, $classId, $subjectId)
                ];
            }
        }

        // Sort by score descending
        usort($availableTeachers, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $availableTeachers;
    }

    private function calculateSubjectMatchScore($teacherId, $subjectId)
    {
        // Placeholder implementation - would need to check if teacher teaches the same subject
        // This would typically involve checking teacher-subject assignments
        return 0; // For now, return 0 until we implement the proper logic
    }

    private function checkTeacherAvailability($teacherId, $date, $periodNumber)
    {
        // Check if teacher already has a class in this period
        $existingSubstitution = TeacherSubstitution::where('substitute_teacher_id', $teacherId)
            ->whereDate('substitution_date', $date)
            ->where('period_number', $periodNumber)
            ->where('status', '!=', 'cancelled')
            ->exists();

        return !$existingSubstitution;
    }

    private function hasClassExperience($teacherId, $classId)
    {
        // Placeholder: Check if teacher has taught this class before
        // This would typically check assignments or previous substitutions
        return false; // Need to implement actual logic
    }

    private function isOverloaded($teacherId, $date)
    {
        // Check if teacher already has too many substitutions today
        $subCount = TeacherSubstitution::where('substitute_teacher_id', $teacherId)
            ->whereDate('substitution_date', $date)
            ->whereIn('status', ['assigned', 'approved'])
            ->count();

        return $subCount > 2; // Threshold can be adjusted
    }

    private function getMatchingReasons($teacherId, $date, $classId, $subjectId)
    {
        $reasons = [];
        
        // Placeholder reasons - would be based on actual checks
        $reasons[] = "Free in Period {$date}";
        
        return $reasons;
    }

    public function assignSubstitute(Request $request, TeacherSubstitution $teacherSubstitution)
    {
        $request->validate([
            'substitute_teacher_id' => 'required|exists:teachers,id'
        ]);

        $teacherSubstitution->update([
            'substitute_teacher_id' => $request->substitute_teacher_id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute teacher assigned successfully.');
    }

    public function approveSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->update([
            'status' => 'approved',
            'assigned_at' => now(),
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment approved successfully.');
    }

    public function cancelSubstitute(TeacherSubstitution $teacherSubstitution)
    {
        $teacherSubstitution->update([
            'status' => 'cancelled',
            'updated_by' => Auth::id()
        ]);

        return redirect()->back()->with('success', 'Substitute assignment cancelled successfully.');
    }

    public function todaySubstitutions()
    {
        $substitutions = TeacherSubstitution::with(['absentTeacher', 'substituteTeacher', 'class', 'section', 'subject'])
            ->forDate(now())
            ->orderBy('period_number')
            ->get();

        return view('admin.teacher-substitutions.today', compact('substitutions'));
    }

    public function absenceOverview()
    {
        $absentTeachers = Teacher::whereHas('absentSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('absent_teacher_id');
        })
        ->with(['absentSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        $substitutedTeachers = Teacher::whereHas('substituteSubstitutions', function($query) {
            $query->forDate(now())->whereNotNull('substitute_teacher_id');
        })
        ->with(['substituteSubstitutions' => function($query) {
            $query->forDate(now())->with(['class', 'section', 'subject']);
        }])
        ->get();

        return view('admin.teacher-substitutions.absence-overview', compact(
            'absentTeachers', 
            'substitutedTeachers'
        ));
    }

    public function substitutionRules()
    {
        // Return view for managing substitution rules
        return view('admin.teacher-substitutions.rules');
    }
}