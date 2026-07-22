<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicEventRequest;
use App\Models\AcademicEvent;
use App\Models\AcademicSession;

class AcademicEventController extends Controller
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
        $this->authorize('viewAny', AcademicEvent::class);

        $events = AcademicEvent::with('academicSession')
            ->orderByDesc('start_date')
            ->paginate(15);

        return view('admin.academic-events.index', compact('events'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', AcademicEvent::class);

        $academicSessions = AcademicSession::orderByDesc('start_date')->get();

        return view('admin.academic-events.create', compact('academicSessions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AcademicEventRequest $request)
    {
        $this->authorize('create', AcademicEvent::class);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        AcademicEvent::create($data);

        return redirect()->route('admin.academic-events.index')
            ->with('success', 'Academic event created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AcademicEvent $academicEvent)
    {
        $this->authorize('view', $academicEvent);

        return view('admin.academic-events.show', compact('academicEvent'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AcademicEvent $academicEvent)
    {
        $this->authorize('update', $academicEvent);

        $academicSessions = AcademicSession::orderByDesc('start_date')->get();

        return view('admin.academic-events.edit', compact('academicEvent', 'academicSessions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AcademicEventRequest $request, AcademicEvent $academicEvent)
    {
        $this->authorize('update', $academicEvent);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $academicEvent->update($data);

        return redirect()->route('admin.academic-events.index')
            ->with('success', 'Academic event updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AcademicEvent $academicEvent)
    {
        $this->authorize('delete', $academicEvent);

        $academicEvent->delete();

        return redirect()->route('admin.academic-events.index')
            ->with('success', 'Academic event deleted successfully.');
    }
}
