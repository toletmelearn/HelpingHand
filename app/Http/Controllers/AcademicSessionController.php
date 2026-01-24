<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AcademicSession::query();

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

        // Filter by current session
        if ($request->filled('current')) {
            $query->where('is_current', $request->current === 'yes');
        }

        $sessions = $query->orderByDesc('start_date')->paginate(15);

        return view('admin.sessions.index', compact('sessions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only admin users can create academic sessions
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.sessions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Only admin users can create academic sessions
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:academic_sessions,code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_current' => 'boolean'
        ]);

        DB::transaction(function () use ($request) {
            // If setting as current, unset other current sessions
            if ($request->is_current) {
                AcademicSession::where('is_current', true)->update(['is_current' => false]);
            }

            AcademicSession::create([
                'name' => $request->name,
                'code' => $request->code,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
                'is_current' => $request->has('is_current'),
            ]);
        });

        return redirect()->route('academic-sessions.index')
                         ->with('success', 'Academic session created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicSession $academicSession)
    {
        // Check if user has permission to view academic sessions
        $user = Auth::user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('teacher'))) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.sessions.show', compact('academicSession'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AcademicSession $academicSession)
    {
        // Only admin users can edit academic sessions
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        return view('admin.sessions.edit', compact('academicSession'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AcademicSession $academicSession)
    {
        // Only admin users can update academic sessions
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:20|unique:academic_sessions,code,' . $academicSession->id,
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'is_current' => 'boolean'
        ]);

        DB::transaction(function () use ($request, $academicSession) {
            // If setting as current, unset other current sessions
            if ($request->is_current) {
                AcademicSession::where('is_current', true)->where('id', '!=', $academicSession->id)->update(['is_current' => false]);
            }

            $academicSession->update([
                'name' => $request->name,
                'code' => $request->code,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
                'is_current' => $request->has('is_current'),
            ]);
        });

        return redirect()->route('academic-sessions.index')
                         ->with('success', 'Academic session updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicSession $academicSession)
    {
        // Only admin users can delete academic sessions
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        // Check if session is currently active and is the current session
        if ($academicSession->is_current) {
            return redirect()->route('academic-sessions.index')
                             ->with('error', 'Cannot delete the current academic session. Please set another session as current first.');
        }

        // Check if session has any related records
        if ($academicSession->students()->count() > 0 || 
            $academicSession->exams()->count() > 0 || 
            $academicSession->fees()->count() > 0 || 
            $academicSession->results()->count() > 0) {
            return redirect()->route('academic-sessions.index')
                             ->with('error', 'Cannot delete academic session because it has related records.');
        }

        $academicSession->delete();

        return redirect()->route('academic-sessions.index')
                         ->with('success', 'Academic session deleted successfully.');
    }

    /**
     * Set the specified session as current.
     */
    public function setCurrent(AcademicSession $academicSession)
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            abort(403, 'Unauthorized access');
        }

        DB::transaction(function () use ($academicSession) {
            // Unset all current sessions
            AcademicSession::where('is_current', true)->update(['is_current' => false]);
            
            // Set the selected session as current
            $academicSession->update(['is_current' => true]);
        });

        return redirect()->route('academic-sessions.index')
                         ->with('success', 'Academic session set as current successfully.');
    }
}