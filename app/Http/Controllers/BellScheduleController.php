<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BellSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BellScheduleController extends Controller
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
        Gate::authorize('viewAny', BellSchedule::class);
        
        $query = BellSchedule::with(['createdBy', 'updatedBy'])
                    ->orderBy('season_type')
                    ->orderBy('start_date');
        
        // Apply filters
        if ($request->filled('season_type')) {
            $query->where('season_type', $request->season_type);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('target_group')) {
            $query->where('target_group', $request->target_group);
        }
        
        $schedules = $query->paginate(15);
        
        $seasonTypes = BellSchedule::select('season_type')->distinct()->pluck('season_type');
        $targetGroups = BellSchedule::select('target_group')->distinct()->pluck('target_group');
        
        return view('admin.bell-schedules.index', compact('schedules', 'seasonTypes', 'targetGroups'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', BellSchedule::class);
        
        return view('admin.bell-schedules.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('create', BellSchedule::class);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'season_type' => 'required|in:summer,winter',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive',
            'periods' => 'required|array',
            'periods.*.period_number' => 'required|integer|min:1',
            'periods.*.period_name' => 'required|string|max:100',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.type' => 'required|in:teaching_period,short_break,lunch_break,assembly',
            'target_group' => 'nullable|in:all,primary,middle,senior',
        ]);
        
        $data = $request->all();
        $data['created_by'] = Auth::id();
        
        BellSchedule::create($data);
        
        return redirect()->route('admin.bell-schedules.index')->with('success', 'Bell schedule created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BellSchedule $bellSchedule)
    {
        Gate::authorize('view', $bellSchedule);
        
        $bellSchedule->load(['createdBy', 'updatedBy']);
        
        return view('admin.bell-schedules.show', compact('bellSchedule'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BellSchedule $bellSchedule)
    {
        Gate::authorize('update', $bellSchedule);
        
        return view('admin.bell-schedules.edit', compact('bellSchedule'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BellSchedule $bellSchedule)
    {
        Gate::authorize('update', $bellSchedule);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'season_type' => 'required|in:summer,winter',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'required|in:active,inactive',
            'periods' => 'required|array',
            'periods.*.period_number' => 'required|integer|min:1',
            'periods.*.period_name' => 'required|string|max:100',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.type' => 'required|in:teaching_period,short_break,lunch_break,assembly',
            'target_group' => 'nullable|in:all,primary,middle,senior',
        ]);
        
        $data = $request->all();
        $data['updated_by'] = Auth::id();
        
        $bellSchedule->update($data);
        
        return redirect()->route('admin.bell-schedules.index')->with('success', 'Bell schedule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BellSchedule $bellSchedule)
    {
        Gate::authorize('delete', $bellSchedule);
        
        $bellSchedule->delete();
        
        return redirect()->route('admin.bell-schedules.index')->with('success', 'Bell schedule deleted successfully.');
    }
    
    /**
     * Get the active bell schedule for today
     */
    public function getCurrentSchedule()
    {
        $today = now()->toDateString();
        
        // Check for special day override first
        $override = \App\Models\SpecialDayOverride::whereDate('override_date', $today)->first();
        if ($override) {
            if ($override->bell_schedule_id && $override->bellSchedule) {
                return response()->json([
                    'schedule' => $override->bellSchedule,
                    'type' => 'override',
                    'override_type' => $override->getReadableType(),
                ]);
            } elseif ($override->custom_periods) {
                return response()->json([
                    'schedule' => [
                        'name' => 'Custom Schedule - ' . $override->getReadableType(),
                        'periods' => $override->custom_periods,
                    ],
                    'type' => 'custom_override',
                    'override_type' => $override->getReadableType(),
                ]);
            }
        }
        
        // Get active seasonal schedule
        $activeSchedule = BellSchedule::where('status', 'active')
                                    ->whereDate('start_date', '<=', $today)
                                    ->whereDate('end_date', '>=', $today)
                                    ->first();
        
        if ($activeSchedule) {
            return response()->json([
                'schedule' => $activeSchedule,
                'type' => 'seasonal',
            ]);
        }
        
        return response()->json(null);
    }
    
    /**
     * Live monitor dashboard
     */
    public function liveMonitor()
    {
        Gate::authorize('viewLiveMonitor', BellSchedule::class);
        
        $currentSchedule = $this->getCurrentSchedule();
        $now = now();
        
        return view('admin.bell-schedules.live-monitor', compact('currentSchedule', 'now'));
    }
}
