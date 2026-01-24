<?php

namespace App\Http\Controllers;

use App\Models\ClassManagement;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ClassManagement::query();
        
        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('section', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('stream', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        
        $classes = $query->orderBy('name')->orderBy('section')->paginate(15);
        
        return view('admin.classes.index', compact('classes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only admin users can create classes
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }
        
        return view('admin.classes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Only admin users can create classes
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }
        
        $request->validate([
            'name' => 'required|string|max:100|unique:class_management,name,NULL,id,section,' . $request->section,
            'section' => 'nullable|string|max:10',
            'stream' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1|max:500',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean'
        ]);

        ClassManagement::create([
            'name' => $request->name,
            'section' => $request->section,
            'stream' => $request->stream,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('classes.index')
                         ->with('success', 'Class created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ClassManagement $class)
    {
        // Check if user has permission to view this class
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('teacher'))) {
            abort(403, 'Unauthorized access');
        }
        
        // Get related teachers for this class
        $teachers = $class->teachers()->with('user')->get();
        
        return view('admin.classes.show', compact('class', 'teachers'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClassManagement $class)
    {
        // Only admin users can edit classes
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }
        
        return view('admin.classes.edit', compact('class'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClassManagement $class)
    {
        // Only admin users can update classes
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }
        
        $request->validate([
            'name' => 'required|string|max:100|unique:class_management,name,' . $class->id . ',id,section,' . $request->section,
            'section' => 'nullable|string|max:10',
            'stream' => 'nullable|string|max:50',
            'capacity' => 'required|integer|min:1|max:500',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean'
        ]);

        $class->update([
            'name' => $request->name,
            'section' => $request->section,
            'stream' => $request->stream,
            'capacity' => $request->capacity,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('classes.index')
                         ->with('success', 'Class updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClassManagement $class)
    {
        // Only admin users can delete classes
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }
        
        // Check if class has any students assigned to it
        if ($class->students()->count() > 0) {
            return redirect()->route('classes.index')
                             ->with('error', 'Cannot delete class because it has students assigned to it.');
        }
        
        $class->delete();

        return redirect()->route('classes.index')
                         ->with('success', 'Class deleted successfully.');
    }
}