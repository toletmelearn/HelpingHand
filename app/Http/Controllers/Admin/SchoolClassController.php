<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicSession;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolClassController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', SchoolClass::class);
        $schoolClasses = SchoolClass::with(['section', 'academicSession', 'teacher'])->withTrashed()->paginate(10);
        return view('admin.school-classes.index', compact('schoolClasses'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', SchoolClass::class);
        $sections = Section::all();
        $academicSessions = AcademicSession::all();
        $teachers = Teacher::all();
        return view('admin.school-classes.create', compact('sections', 'academicSessions', 'teachers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', SchoolClass::class);

        // class_order drives promotion "next class" logic (SchoolClass::
        // getNextClasses()/getPreviousClasses()) and every class dropdown's
        // sort order -- it was previously accepted nowhere in this form and
        // always saved as null. unique:school_classes,name only checks
        // non-deleted rows by default, matching how a soft-deleted class's
        // name should be reusable.
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:school_classes,name',
            'class_order' => 'required|integer|min:1|unique:school_classes,class_order',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['is_active'] = true;

        SchoolClass::create($validated);

        return redirect()->route('admin.school-classes.index')
                         ->with('success', 'Class created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolClass $schoolClass)
    {
        $this->authorize('view', $schoolClass);
        return view('admin.school-classes.show', compact('schoolClass'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SchoolClass $schoolClass)
    {
        $this->authorize('update', $schoolClass);
        $sections = Section::all();
        $academicSessions = AcademicSession::all();
        $teachers = Teacher::all();
        return view('admin.school-classes.edit', compact('schoolClass', 'sections', 'academicSessions', 'teachers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SchoolClass $schoolClass)
    {
        $this->authorize('update', $schoolClass);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'section_id' => 'nullable|exists:sections,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string|max:1000',
        ]);

        $schoolClass->update($request->all());

        return redirect()->route('admin.school-classes.index')
                         ->with('success', 'Class updated successfully.');
    }

    /**
     * Remove the specified resource from storage. This is the "safe" path --
     * it refuses if any student is still assigned, same guard pattern as
     * FeeStructureController::destroy()/FeeTypeController::destroy(). Use
     * destroyWithStudents() for the explicit "delete the class and every
     * student in it" action.
     */
    public function destroy(SchoolClass $schoolClass)
    {
        $this->authorize('delete', $schoolClass);

        $hasStudents = Student::where('class_id', $schoolClass->id)
            ->orWhere('school_class_id', $schoolClass->id)
            ->exists();

        if ($hasStudents) {
            return redirect()->back()
                ->with('error', 'This class still has students assigned to it. Use "Delete Class & Students" if you want to remove them together, or move the students out first.');
        }

        $schoolClass->delete();

        return redirect()->route('admin.school-classes.index')
                         ->with('success', 'Class deleted successfully.');
    }

    /**
     * Delete a class AND every student currently assigned to it, in one
     * action -- an explicitly destructive shortcut for "this class shouldn't
     * exist, remove it and everyone in it" (e.g. a class entered by mistake,
     * or one that no longer runs). Confirmed with the school: this should
     * actually remove the students, not just detach them.
     *
     * Matches both class_id and school_class_id -- SchoolClass::students()
     * only covers class_id and would silently miss students who only have
     * school_class_id set (the same inconsistency documented on
     * Student::booted()).
     */
    public function destroyWithStudents(SchoolClass $schoolClass)
    {
        $this->authorize('delete', $schoolClass);

        $students = Student::where('class_id', $schoolClass->id)
            ->orWhere('school_class_id', $schoolClass->id)
            ->get();

        $studentCount = $students->count();
        $className = $schoolClass->name;

        DB::transaction(function () use ($students, $schoolClass) {
            foreach ($students as $student) {
                $student->delete();
            }
            $schoolClass->delete();
        });

        try {
            activity()->causedBy(auth()->user())
                ->withProperties(['class_name' => $className, 'class_id' => $schoolClass->id, 'students_deleted' => $studentCount])
                ->log("Deleted class '{$className}' and {$studentCount} student(s) in it");
        } catch (\Throwable $e) {
            // Audit logging must never block the actual deletion.
        }

        return redirect()->route('admin.students.index')
            ->with('success', "Deleted class '{$className}' and {$studentCount} student(s).");
    }
    
    /**
     * Restore a soft-deleted class.
     */
    public function restore($id)
    {
        $schoolClass = SchoolClass::withTrashed()->findOrFail($id);
        $this->authorize('restore', $schoolClass);
        
        $schoolClass->restore();
        
        return redirect()->route('admin.school-classes.index')
                         ->with('success', 'Class restored successfully.');
    }
}
