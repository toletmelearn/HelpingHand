<?php

namespace App\Http\Controllers\API;

use App\Models\BellTiming;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BellTimingController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
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
                                ->get();
            
            return $this->success($bellTimings, 'Bell timings retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve bell timings: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
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
                return $this->error('Time conflict detected with existing schedule: ' . 
                                   $conflicts->first()->period_name . ' (' . 
                                   $conflicts->first()->start_time . ' - ' . 
                                   $conflicts->first()->end_time . ')', 409);
            }

            $bellTiming = new BellTiming();
            $bellTiming->fill($validated);
            $bellTiming->created_by = auth()->id(); // Current authenticated user
            $bellTiming->save();

            return $this->success($bellTiming, 'Bell timing created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::with('createdBy')->findOrFail($id);
            return $this->success($bellTiming, 'Bell timing retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Bell timing not found: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::findOrFail($id);

            $validated = $request->validate([
                'day_of_week' => 'sometimes|required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'period_name' => 'sometimes|required|string|max:100',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'class_section' => 'nullable|string|max:50',
                'is_active' => 'boolean',
                'is_break' => 'boolean',
                'order_index' => 'sometimes|required|integer|min:0',
                'academic_year' => 'nullable|string|max:20',
                'semester' => 'nullable|string|max:20',
                'custom_label' => 'nullable|string|max:100',
                'color_code' => 'nullable|regex:/^#[0-9A-F]{6}$/i'
            ]);

            // Check for time conflicts (excluding current record)
            $conflicts = BellTiming::where('day_of_week', $request->day_of_week ?? $bellTiming->day_of_week)
                                  ->where('class_section', $request->class_section ?? $bellTiming->class_section)
                                  ->where('id', '!=', $bellTiming->id)
                                  ->where(function($query) use ($request, $bellTiming) {
                                      $start_time = $request->start_time ?? $bellTiming->start_time;
                                      $end_time = $request->end_time ?? $bellTiming->end_time;
                                      
                                      $query->whereBetween('start_time', [$start_time, $end_time])
                                            ->orWhereBetween('end_time', [$start_time, $end_time])
                                            ->orWhere(function($q) use ($start_time, $end_time) {
                                                $q->where('start_time', '<=', $start_time)
                                                  ->where('end_time', '>=', $end_time);
                                            });
                                  })
                                  ->where('is_active', true)
                                  ->get();

            if ($conflicts->count() > 0) {
                return $this->error('Time conflict detected with existing schedule: ' . 
                                   $conflicts->first()->period_name . ' (' . 
                                   $conflicts->first()->start_time . ' - ' . 
                                   $conflicts->first()->end_time . ')', 409);
            }

            $bellTiming->fill($validated);
            $bellTiming->save();

            return $this->success($bellTiming, 'Bell timing updated successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to update bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $bellTiming = BellTiming::findOrFail($id);
            $bellTiming->delete();

            return $this->success(null, 'Bell timing deleted successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to delete bell timing: ' . $e->getMessage());
        }
    }

    /**
     * Display weekly timetable for a class.
     */
    public function weeklyTimetable(Request $request, string $classSection): JsonResponse
    {
        try {
            $academicYear = $request->academic_year ?: date('Y') . '-' . (date('Y') + 1);
            
            $timetable = BellTiming::getTimetableForClass($classSection, $academicYear);
            
            return $this->success($timetable, 'Weekly timetable retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve weekly timetable: ' . $e->getMessage());
        }
    }

    /**
     * Get current period (AJAX endpoint).
     */
    public function currentPeriod(): JsonResponse
    {
        try {
            $currentPeriod = BellTiming::getCurrentPeriod();
            
            return $this->success([
                'current_period' => $currentPeriod,
                'current_time' => now()->format('H:i:s'),
                'current_day' => now()->format('l')
            ], 'Current period retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve current period: ' . $e->getMessage());
        }
    }

    /**
     * Bulk create schedule for a week.
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        try {
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
                            'created_by' => auth()->id() // Current authenticated user
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

            return $this->success(null, $message);
        } catch (\Exception $e) {
            return $this->error('Failed to bulk create schedule: ' . $e->getMessage());
        }
    }
}