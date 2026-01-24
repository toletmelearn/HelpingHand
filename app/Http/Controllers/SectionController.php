<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\ClassManagement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Section::with('class');

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('class', function($q) use ($searchTerm) {
                      $q->where('name', 'LIKE', "%{$searchTerm}%");
                  });
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $sections = $query->orderBy('name')->paginate(15);
        $classes = ClassManagement::orderBy('name')->get();

        return view('admin.sections.index', compact('sections', 'classes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only admin users can create sections
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $classes = ClassManagement::orderBy('name')->get();
        return view('admin.sections.create', compact('classes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Only admin users can create sections
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:10|unique:sections,name,NULL,id,class_id,' . $request->class_id,
            'class_id' => 'required|exists:class_management,id',
            'description' => 'nullable|string|max:500',
            'capacity' => 'required|integer|min:1|max:500',
            'is_active' => 'boolean'
        ]);

        Section::create([
            'name' => $request->name,
            'class_id' => $request->class_id,
            'description' => $request->description,
            'capacity' => $request->capacity,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('sections.index')
                         ->with('success', 'Section created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section)
    {
        // Check if user has permission to view this section
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('teacher'))) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.sections.show', compact('section'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Section $section)
    {
        // Only admin users can edit sections
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $classes = ClassManagement::orderBy('name')->get();
        return view('admin.sections.edit', compact('section', 'classes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Section $section)
    {
        // Only admin users can update sections
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:10|unique:sections,name,' . $section->id . ',id,class_id,' . $request->class_id,
            'class_id' => 'required|exists:class_management,id',
            'description' => 'nullable|string|max:500',
            'capacity' => 'required|integer|min:1|max:500',
            'is_active' => 'boolean'
        ]);

        $section->update([
            'name' => $request->name,
            'class_id' => $request->class_id,
            'description' => $request->description,
            'capacity' => $request->capacity,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('sections.index')
                         ->with('success', 'Section updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section)
    {
        // Only admin users can delete sections
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        // Check if section has any students assigned to it
        if ($section->students()->count() > 0) {
            return redirect()->route('sections.index')
                             ->with('error', 'Cannot delete section because it has students assigned to it.');
        }

        $section->delete();

        return redirect()->route('sections.index')
                         ->with('success', 'Section deleted successfully.');
    }
}