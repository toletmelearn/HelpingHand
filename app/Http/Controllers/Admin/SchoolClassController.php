<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\AcademicSession;
use App\Models\Teacher;
use Illuminate\Http\Request;

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
        
        $request->validate([
            'name' => 'required|string|max:255',
            'section_id' => 'nullable|exists:sections,id',
            'academic_session_id' => 'required|exists:academic_sessions,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'description' => 'nullable|string|max:1000',
        ]);

        SchoolClass::create($request->all());

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
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolClass $schoolClass)
    {
        $this->authorize('delete', $schoolClass);
        
        $schoolClass->delete();

        return redirect()->route('admin.school-classes.index')
                         ->with('success', 'Class deleted successfully.');
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
