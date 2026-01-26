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
        $teachers = User::role('teacher')->get();
        return view('admin.class-teacher-assignments.create', compact('teachers'));
    }

    public function store(Request $request)
    {
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

    public function show(ClassTeacherAssignment $assignment)
    {
        $assignment->load('teacher');
        return view('admin.class-teacher-assignments.show', compact('assignment'));
    }

    public function edit(ClassTeacherAssignment $assignment)
    {
        $assignment->load('teacher');
        $teachers = User::role('teacher')->get();
        return view('admin.class-teacher-assignments.edit', compact('assignment', 'teachers'));
    }

    public function update(Request $request, ClassTeacherAssignment $assignment)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'assigned_class' => 'required|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'boolean',
        ]);

        $assignment->update([
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

    public function destroy(ClassTeacherAssignment $assignment)
    {
        $assignment->delete();

        return redirect()->route('admin.class-teacher-assignments.index')
                         ->with('success', 'Class teacher assignment deleted successfully.');
    }
}