<?php

namespace App\Http\Controllers;

use App\Models\ClassTeacherAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassTeacherAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', ClassTeacherAssignment::class);

        $query = ClassTeacherAssignment::with('teacher');

        // Apply filters
        if ($request->filled('teacher_id')) {
            $query->forTeacher($request->teacher_id);
        }

        if ($request->filled('assigned_class')) {
            $query->forClass($request->assigned_class);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'inactive') {
                $query->inactive();
            }
        }

        $assignments = $query->orderBy('assigned_class')->paginate(15);

        // Get all teachers for filter dropdown
        $teachers = User::role('teacher')->get();

        return view('admin.class-teacher-assignments.index', compact('assignments', 'teachers'));
    }

    public function create()
    {
        $this->authorize('create', ClassTeacherAssignment::class);

        $teachers = User::role('teacher')->get();
        return view('admin.class-teacher-assignments.create', compact('teachers'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', ClassTeacherAssignment::class);

        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'assigned_class' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        ClassTeacherAssignment::create([
            'teacher_id' => $request->teacher_id,
            'assigned_class' => $request->assigned_class,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active'),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.class-teacher-assignments.index')
                         ->with('success', 'Class teacher assignment created successfully.');
    }

    public function show(ClassTeacherAssignment $class_teacher_assignment)
    {
        $this->authorize('view', $class_teacher_assignment);

        $class_teacher_assignment->load('teacher');
        return view('admin.class-teacher-assignments.show', ['assignment' => $class_teacher_assignment]);
    }

    public function edit(ClassTeacherAssignment $class_teacher_assignment)
    {
        $this->authorize('update', $class_teacher_assignment);

        $class_teacher_assignment->load('teacher');
        $teachers = User::role('teacher')->get();
        return view('admin.class-teacher-assignments.edit', ['assignment' => $class_teacher_assignment, 'teachers' => $teachers]);
    }

    public function update(Request $request, ClassTeacherAssignment $class_teacher_assignment)
    {
        $this->authorize('update', $class_teacher_assignment);

        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'assigned_class' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $class_teacher_assignment->update([
            'teacher_id' => $request->teacher_id,
            'assigned_class' => $request->assigned_class,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'is_active' => $request->has('is_active'),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('admin.class-teacher-assignments.index')
                         ->with('success', 'Class teacher assignment updated successfully.');
    }

    public function destroy(ClassTeacherAssignment $class_teacher_assignment)
    {
        $this->authorize('delete', $class_teacher_assignment);

        $class_teacher_assignment->delete();

        return redirect()->route('admin.class-teacher-assignments.index')
                         ->with('success', 'Class teacher assignment deleted successfully.');
    }

    public function studentRecords(Request $request)
    {
        $this->authorize('viewStudentRecords');

        $query = \App\Models\Student::with(['user', 'class', 'section']);

        // Filter by class if user is a class teacher
        if (auth()->user() && auth()->user()->roles->pluck('name')->contains('class-teacher')) {
            $classTeacher = \App\Models\Teacher::where('user_id', auth()->user()->id)->first();
            if ($classTeacher) {
                // Teacher::classes() is keyed to class_management, but
                // Student::class_id is a school_classes id -- translate
                // through legacy_class_map so this actually scopes to the
                // teacher's real classes.
                $classManagementIds = $classTeacher->classes()->pluck('class_management.id')->toArray();
                $classIds = \App\Models\LegacyClassMap::whereIn('class_management_id', $classManagementIds)
                    ->pluck('school_class_id')
                    ->toArray();
                $query->whereIn('class_id', $classIds);
            }
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        $students = $query->paginate(20);
        $classes = \App\Models\ClassManagement::all();
        $sections = \App\Models\Section::all();

        return view('admin.class-teacher-control.student-records', compact('students', 'classes', 'sections'));
    }
}