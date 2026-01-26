<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SpecialDayOverride;
use App\Models\BellSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class SpecialDayOverrideController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('viewAny', SpecialDayOverride::class);
        
        $query = SpecialDayOverride::with(['bellSchedule', 'createdBy', 'updatedBy'])
                    ->orderBy('override_date', 'desc');
        
        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('override_date')) {
            $query->whereDate('override_date', $request->override_date);
        }
        
        $overrides = $query->paginate(15);
        
        $types = SpecialDayOverride::select('type')->distinct()->pluck('type');
        
        return view('admin.special-day-overrides.index', compact('overrides', 'types'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', SpecialDayOverride::class);
        
        $schedules = BellSchedule::where('status', 'active')->get();
        
        return view('admin.special-day-overrides.create', compact('schedules'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', SpecialDayOverride::class);
        
        $request->validate([
            'override_date' => 'required|date|unique:special_day_overrides,override_date',
            'type' => 'required|in:exam_day,half_day,event_day,emergency_closure',
            'bell_schedule_id' => 'nullable|exists:bell_schedules,id',
            'custom_periods' => 'nullable|array',
            'custom_periods.*.period_number' => 'required_with:custom_periods|integer|min:1',
            'custom_periods.*.period_name' => 'required_with:custom_periods|string|max:100',
            'custom_periods.*.start_time' => 'required_with:custom_periods|date_format:H:i',
            'custom_periods.*.end_time' => 'required_with:custom_periods|date_format:H:i|after:custom_periods.*.start_time',
            'custom_periods.*.type' => 'required_with:custom_periods|in:teaching_period,short_break,lunch_break,assembly',
            'remarks' => 'nullable|string|max:500',
        ]);
        
        $data = $request->all();
        $data['created_by'] = Auth::id();
        
        SpecialDayOverride::create($data);
        
        return redirect()->route('admin.special-day-overrides.index')->with('success', 'Special day override created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(SpecialDayOverride $specialDayOverride)
    {
        Gate::authorize('view', $specialDayOverride);
        
        $specialDayOverride->load(['bellSchedule', 'createdBy', 'updatedBy']);
        
        return view('admin.special-day-overrides.show', compact('specialDayOverride'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SpecialDayOverride $specialDayOverride)
    {
        Gate::authorize('update', $specialDayOverride);
        
        $schedules = BellSchedule::where('status', 'active')->get();
        
        return view('admin.special-day-overrides.edit', compact('specialDayOverride', 'schedules'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SpecialDayOverride $specialDayOverride)
    {
        Gate::authorize('update', $specialDayOverride);
        
        $request->validate([
            'override_date' => 'required|date|unique:special_day_overrides,override_date,' . $specialDayOverride->id,
            'type' => 'required|in:exam_day,half_day,event_day,emergency_closure',
            'bell_schedule_id' => 'nullable|exists:bell_schedules,id',
            'custom_periods' => 'nullable|array',
            'custom_periods.*.period_number' => 'required_with:custom_periods|integer|min:1',
            'custom_periods.*.period_name' => 'required_with:custom_periods|string|max:100',
            'custom_periods.*.start_time' => 'required_with:custom_periods|date_format:H:i',
            'custom_periods.*.end_time' => 'required_with:custom_periods|date_format:H:i|after:custom_periods.*.start_time',
            'custom_periods.*.type' => 'required_with:custom_periods|in:teaching_period,short_break,lunch_break,assembly',
            'remarks' => 'nullable|string|max:500',
        ]);
        
        $data = $request->all();
        $data['updated_by'] = Auth::id();
        
        $specialDayOverride->update($data);
        
        return redirect()->route('admin.special-day-overrides.index')->with('success', 'Special day override updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SpecialDayOverride $specialDayOverride)
    {
        Gate::authorize('delete', $specialDayOverride);
        
        $specialDayOverride->delete();
        
        return redirect()->route('admin.special-day-overrides.index')->with('success', 'Special day override deleted successfully.');
    }
}
