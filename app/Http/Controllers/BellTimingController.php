<?php

namespace App\Http\Controllers;

use App\Models\BellTiming;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BellTimingController extends Controller
{
    /**
     * Display a listing of the bell timings.
     */
    public function index(Request $request)
    {
        $query = BellTiming::with('createdBy');
        
        // Filter by day of week
        if ($request->filled('day_of_week')) {
            $query->where('day_of_week', $request->day_of_week);
        }
        
        // Filter by class section
        if ($request->filled('class_section')) {
            $query->where('class_section', $request->class_section);
        }
        
        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        // Filter by academic year
        if ($request->filled('academic_year')) {
            $query->where('academic_year', $request->academic_year);
        }
        
        $bellTimings = $query->orderBy('day_of_week')
                            ->orderBy('order_index')
                            ->paginate(20);
        
        // Get unique values for filters
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter();
        
        return view('bell-timing.index', compact('bellTimings', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Show the form for creating a new bell timing.
     */
    public function create()
    {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];
        
        return view('bell-timing.create', compact('daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Store a newly created bell timing in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where(function($query) use ($request) {
                                  $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                                        ->orWhere(function($q) use ($request) {
                                            $q->where('start_time', '<=', $request->start_time)
                                              ->where('end_time', '>=', $request->end_time);
                                        });
                              })
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' . 
                                     $conflicts->first()->period_name . ' (' . 
                                     $conflicts->first()->start_time . ' - ' . 
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming = new BellTiming();
        $bellTiming->fill($request->all());
        $bellTiming->created_by = Auth::id(); // Current authenticated user
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing created successfully!');
    }

    /**
     * Display the specified bell timing.
     */
    public function show(BellTiming $bellTiming)
    {
        $bellTiming->load('createdBy');
        return view('bell-timing.show', compact('bellTiming'));
    }

    /**
     * Show the form for editing the specified bell timing.
     */
    public function edit(BellTiming $bellTiming)
    {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        $currentYear = date('Y');
        $academicYears = [
            $currentYear . '-' . ($currentYear + 1),
            ($currentYear - 1) . '-' . $currentYear,
            ($currentYear + 1) . '-' . ($currentYear + 2)
        ];
        
        return view('bell-timing.edit', compact('bellTiming', 'daysOfWeek', 'classSections', 'academicYears'));
    }

    /**
     * Update the specified bell timing in storage.
     */
    public function update(Request $request, BellTiming $bellTiming)
    {
        $request->validate([
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'period_name' => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'class_section' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'is_break' => 'boolean',
            'order_index' => 'required|integer|min:0',
            'academic_year' => 'nullable|string|max:20',
            'semester' => 'nullable|string|max:20',
            'custom_label' => 'nullable|string|max:100',
            'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
        ]);

        // Check for time conflicts (excluding current record)
        $conflicts = BellTiming::where('day_of_week', $request->day_of_week)
                              ->where('class_section', $request->class_section)
                              ->where('id', '!=', $bellTiming->id)
                              ->where(function($query) use ($request) {
                                  $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                                        ->orWhere(function($q) use ($request) {
                                            $q->where('start_time', '<=', $request->start_time)
                                              ->where('end_time', '>=', $request->end_time);
                                        });
                              })
                              ->where('is_active', true)
                              ->get();

        if ($conflicts->count() > 0) {
            return back()->withErrors(['time_conflict' => 'Time conflict detected with existing schedule: ' . 
                                     $conflicts->first()->period_name . ' (' . 
                                     $conflicts->first()->start_time . ' - ' . 
                                     $conflicts->first()->end_time . ')']);
        }

        $bellTiming->fill($request->all());
        $bellTiming->save();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing updated successfully!');
    }

    /**
     * Remove the specified bell timing from storage.
     */
    public function destroy(BellTiming $bellTiming)
    {
        $bellTiming->delete();

        return redirect()->route('bell-timing.index')
                         ->with('success', 'Bell timing deleted successfully!');
    }

    /**
     * Display weekly timetable for a class.
     */
    public function weeklyTimetable(Request $request)
    {
        $classSection = $request->class_section;
        $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);
        
        if ($classSection) {
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
        } else {
            $timetable = collect();
        }
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        $schedules = $timetable; // Rename variable to match view expectation
        $academicYears = BellTiming::distinct()->pluck('academic_year')->filter(); // Get all academic years for filter
        
        return view('bell-timing.weekly', compact('schedules', 'classSection', 'academicYear', 'classSections', 'academicYears'));
    }

    /**
     * Display today's schedule.
     */
    public function todaysSchedule(Request $request)
    {
        $classSection = $request->class_section;
        $day = now()->format('l'); // Current day of week
        
        $schedule = BellTiming::getTodaysSchedule($day, $classSection);
        
        $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
        
        return view('bell-timing.daily', compact('schedule', 'classSection', 'day', 'classSections'));
    }

    /**
     * Get current period (AJAX endpoint).
     */
    public function currentPeriod()
    {
        $currentPeriod = BellTiming::getCurrentPeriod();
        
        return response()->json([
            'current_period' => $currentPeriod,
            'current_time' => now()->format('H:i:s'),
            'current_day' => now()->format('l')
        ]);
    }

    /**
     * Bulk create schedule for a week.
     */
    public function bulkCreate(Request $request)
    {
        if ($request->isMethod('get')) {
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $classSections = Student::distinct()->pluck('class')->filter()->sortBy('class');
            $currentYear = date('Y');
            $academicYears = [
                $currentYear . '-' . ($currentYear + 1),
                ($currentYear - 1) . '-' . $currentYear,
                ($currentYear + 1) . '-' . ($currentYear + 2)
            ];
            
            return view('bell-timing.bulk-create', compact('daysOfWeek', 'classSections', 'academicYears'));
        }

        // Validate bulk creation
        $request->validate([
            'days' => 'required|array',
            'days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'class_section' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'periods' => 'required|array',
            'periods.*.period_name' => 'required|string|max:100',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.is_break' => 'boolean',
            'periods.*.order_index' => 'required|integer|min:0'
        ]);

        $createdCount = 0;
        $errors = [];

        foreach ($request->days as $day) {
            foreach ($request->periods as $index => $period) {
                try {
                    // Check for time conflicts
                    $conflicts = BellTiming::where('day_of_week', $day)
                                          ->where('class_section', $request->class_section)
                                          ->where(function($query) use ($period) {
                                              $query->whereBetween('start_time', [$period['start_time'], $period['end_time']])
                                                    ->orWhereBetween('end_time', [$period['start_time'], $period['end_time']])
                                                    ->orWhere(function($q) use ($period) {
                                                        $q->where('start_time', '<=', $period['start_time'])
                                                          ->where('end_time', '>=', $period['end_time']);
                                                    });
                                          })
                                          ->where('is_active', true)
                                          ->get();

                    if ($conflicts->count() > 0) {
                        $errors[] = "Conflict on $day for " . $period['period_name'];
                        continue;
                    }

                    BellTiming::create([
                        'day_of_week' => $day,
                        'period_name' => $period['period_name'],
                        'start_time' => $period['start_time'],
                        'end_time' => $period['end_time'],
                        'class_section' => $request->class_section,
                        'is_active' => true,
                        'is_break' => $period['is_break'] ?? false,
                        'order_index' => $period['order_index'],
                        'academic_year' => $request->academic_year,
                        'semester' => $request->semester,
                        'custom_label' => $period['custom_label'] ?? null,
                        'color_code' => $period['color_code'] ?? '#007bff',
                        'created_by' => Auth::id() // Current authenticated user
                    ]);

                    $createdCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error creating period on $day: " . $e->getMessage();
                }
            }
        }

        $message = "Successfully created $createdCount bell timings.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors occurred.";
        }

        return redirect()->route('bell-timing.index')
                         ->with('success', $message)
                         ->with('errors', $errors);
    }
}