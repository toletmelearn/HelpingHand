<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
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
        $this->authorize('viewAny', Subject::class);
        $subjects = Subject::withTrashed()->paginate(10);
        return view('admin.subjects.index', compact('subjects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Subject::class);
        return view('admin.subjects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Subject::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects')],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        Subject::create($request->all());

        return redirect()->route('admin.subjects.index')
                         ->with('success', 'Subject created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject)
    {
        $this->authorize('view', $subject);
        return view('admin.subjects.show', compact('subject'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        $this->authorize('update', $subject);
        return view('admin.subjects.edit', compact('subject'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        $this->authorize('update', $subject);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects')->ignore($subject->id)],
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $subject->update($request->all());

        return redirect()->route('admin.subjects.index')
                         ->with('success', 'Subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * Priority audit finding F1: this previously deleted (soft-deleted) any
     * Subject with zero dependency check -- even one actively referenced by
     * timetable slots or teacher class/subject assignments. Since Subject
     * uses SoftDeletes, the real DB foreign keys on both tables (ON DELETE
     * CASCADE) never fire on this path -- this is the actual, only real
     * safeguard, matching the same pattern already applied to Sections and
     * Teachers. exam_papers.subject is a free-text string column (no FK),
     * and fee_structures has no subject reference at all, so neither is
     * checked here.
     */
    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);

        $timetableSlotCount = \Illuminate\Support\Facades\DB::table('timetable_slots')->where('subject_id', $subject->id)->count();
        if ($timetableSlotCount > 0) {
            return redirect()->route('admin.subjects.index')
                ->with('error', "Cannot delete subject \"{$subject->name}\": {$timetableSlotCount} timetable slot(s) reference it.");
        }

        $assignmentCount = \Illuminate\Support\Facades\DB::table('teacher_class_subject_assignments')->where('subject_id', $subject->id)->count();
        if ($assignmentCount > 0) {
            return redirect()->route('admin.subjects.index')
                ->with('error', "Cannot delete subject \"{$subject->name}\": {$assignmentCount} teacher/class assignment(s) reference it. Remove those assignments first.");
        }

        $subject->delete();

        return redirect()->route('admin.subjects.index')
                         ->with('success', 'Subject deleted successfully.');
    }
    
    /**
     * Restore a soft-deleted subject.
     */
    public function restore($id)
    {
        $subject = Subject::withTrashed()->findOrFail($id);
        $this->authorize('restore', $subject);
        
        $subject->restore();
        
        return redirect()->route('admin.subjects.index')
                         ->with('success', 'Subject restored successfully.');
    }
}
