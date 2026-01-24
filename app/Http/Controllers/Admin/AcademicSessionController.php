<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Http\Request;

class AcademicSessionController extends Controller
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
        $this->authorize('viewAny', AcademicSession::class);
        $academicSessions = AcademicSession::withTrashed()->paginate(10);
        return view('admin.academic-sessions.index', compact('academicSessions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', AcademicSession::class);
        return view('admin.academic-sessions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', AcademicSession::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
        ]);

        AcademicSession::create($request->all());

        return redirect()->route('admin.academic-sessions.index')
                         ->with('success', 'Academic Session created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicSession $academicSession)
    {
        $this->authorize('view', $academicSession);
        return view('admin.academic-sessions.show', compact('academicSession'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AcademicSession $academicSession)
    {
        $this->authorize('update', $academicSession);
        return view('admin.academic-sessions.edit', compact('academicSession'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AcademicSession $academicSession)
    {
        $this->authorize('update', $academicSession);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string|max:1000',
            'is_current' => 'boolean',
        ]);

        $academicSession->update($request->all());

        return redirect()->route('admin.academic-sessions.index')
                         ->with('success', 'Academic Session updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicSession $academicSession)
    {
        $this->authorize('delete', $academicSession);
        
        $academicSession->delete();

        return redirect()->route('admin.academic-sessions.index')
                         ->with('success', 'Academic Session deleted successfully.');
    }
    
    /**
     * Restore a soft-deleted academic session.
     */
    public function restore($id)
    {
        $academicSession = AcademicSession::withTrashed()->findOrFail($id);
        $this->authorize('restore', $academicSession);
        
        $academicSession->restore();
        
        return redirect()->route('admin.academic-sessions.index')
                         ->with('success', 'Academic Session restored successfully.');
    }
}
