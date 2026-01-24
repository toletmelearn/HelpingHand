<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SectionController extends Controller
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
        $this->authorize('viewAny', Section::class);
        $sections = Section::withTrashed()->paginate(10);
        return view('admin.sections.index', compact('sections'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Section::class);
        return view('admin.sections.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Section::class);
        
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sections')],
            'capacity' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        Section::create($request->all());

        return redirect()->route('admin.sections.index')
                         ->with('success', 'Section created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        $this->authorize('view', $section);
        return view('admin.sections.show', compact('section'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Section $section)
    {
        $this->authorize('update', $section);
        return view('admin.sections.edit', compact('section'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        $this->authorize('update', $section);
        
        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('sections')->ignore($section->id)],
            'capacity' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        $section->update($request->all());

        return redirect()->route('admin.sections.index')
                         ->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        $this->authorize('delete', $section);
        
        $section->delete();

        return redirect()->route('admin.sections.index')
                         ->with('success', 'Section deleted successfully.');
    }
    
    /**
     * Restore a soft-deleted section.
     */
    public function restore($id)
    {
        $section = Section::withTrashed()->findOrFail($id);
        $this->authorize('restore', $section);
        
        $section->restore();
        
        return redirect()->route('admin.sections.index')
                         ->with('success', 'Section restored successfully.');
    }
}
