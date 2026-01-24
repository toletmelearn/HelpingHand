<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Subject::query();

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('code', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Filter by subject type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $subjects = $query->orderBy('name')->paginate(15);

        return view('admin.subjects.index', compact('subjects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only admin users can create subjects
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.subjects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Only admin users can create subjects
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:subjects,code',
            'description' => 'nullable|string|max:500',
            'max_marks' => 'required|integer|min:1|max:500',
            'pass_marks' => 'required|integer|min:1|max:500',
            'type' => 'required|in:theory,practical,both',
            'is_active' => 'boolean'
        ]);

        Subject::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_marks' => $request->max_marks,
            'pass_marks' => $request->pass_marks,
            'type' => $request->type,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('subjects.index')
                         ->with('success', 'Subject created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject)
    {
        // Check if user has permission to view this subject
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('teacher'))) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.subjects.show', compact('subject'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Subject $subject)
    {
        // Only admin users can edit subjects
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.subjects.edit', compact('subject'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Subject $subject)
    {
        // Only admin users can update subjects
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
            'description' => 'nullable|string|max:500',
            'max_marks' => 'required|integer|min:1|max:500',
            'pass_marks' => 'required|integer|min:1|max:500',
            'type' => 'required|in:theory,practical,both',
            'is_active' => 'boolean'
        ]);

        $subject->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'max_marks' => $request->max_marks,
            'pass_marks' => $request->pass_marks,
            'type' => $request->type,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('subjects.index')
                         ->with('success', 'Subject updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject)
    {
        // Only admin users can delete subjects
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        // Check if subject is assigned to any classes or teachers
        if ($subject->classes()->count() > 0 || $subject->teachers()->count() > 0) {
            return redirect()->route('subjects.index')
                             ->with('error', 'Cannot delete subject because it is assigned to classes or teachers.');
        }

        $subject->delete();

        return redirect()->route('subjects.index')
                         ->with('success', 'Subject deleted successfully.');
    }
}