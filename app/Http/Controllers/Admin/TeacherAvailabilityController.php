<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BellTiming;
use App\Models\Teacher;
use App\Models\TeacherAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherAvailabilityController extends Controller
{
    /**
     * Admin sees a list of teachers to pick from; a teacher visiting
     * directly is sent straight to their own grid.
     */
    public function index()
    {
        $this->authorize('viewAny', TeacherAvailability::class);

        $user = auth()->user();
        if (!$user->hasRole('admin') && $user->hasRole('teacher')) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if ($teacher) {
                return redirect()->route('teacher-availability.edit', $teacher);
            }
        }

        $teachers = Teacher::orderBy('name')->get();

        return view('admin.teacher-availability.index', compact('teachers'));
    }

    /**
     * Day x period grid. Scoped to the shared/school-wide bell timings
     * (class_section IS NULL) -- availability is a teacher-level concept,
     * not a per-class one, and class_section is an unreliable free-text
     * field elsewhere in this module (see FeasibilityService).
     */
    public function edit(Teacher $teacher)
    {
        $this->authorize('manage', [TeacherAvailability::class, $teacher->id]);

        $timings = BellTiming::where('is_active', true)
            ->where('is_break', false)
            ->whereNull('class_section')
            ->orderBy('order_index')
            ->get();

        $days = $timings->sortBy('day_order')->pluck('day_of_week')->unique()->values()->all();
        $periods = $timings->pluck('period_name')->unique()->values()->all();

        // grid[day][period_name] = bell_timing_id, so the view can look up
        // the right id for each cell without re-querying.
        $grid = [];
        foreach ($timings as $timing) {
            $grid[$timing->day_of_week][$timing->period_name] = $timing->id;
        }

        $blockedIds = TeacherAvailability::where('teacher_id', $teacher->id)
            ->where('is_available', false)
            ->pluck('bell_timing_id')
            ->all();

        return view('admin.teacher-availability.edit', compact('teacher', 'days', 'periods', 'grid', 'blockedIds'));
    }

    /**
     * One POST for the whole grid: `blocked` is the full set of
     * bell_timing_ids currently toggled off. Absence of a row means
     * available, so we only ever store/delete the blocked rows.
     */
    public function update(Request $request, Teacher $teacher)
    {
        $this->authorize('manage', [TeacherAvailability::class, $teacher->id]);

        $validated = $request->validate([
            'blocked' => 'array',
            'blocked.*' => 'integer|exists:bell_timings,id',
        ]);

        $blockedIds = $validated['blocked'] ?? [];

        DB::transaction(function () use ($teacher, $blockedIds) {
            TeacherAvailability::where('teacher_id', $teacher->id)
                ->where('is_available', false)
                ->whereNotIn('bell_timing_id', $blockedIds)
                ->delete();

            foreach ($blockedIds as $bellTimingId) {
                TeacherAvailability::updateOrCreate(
                    ['teacher_id' => $teacher->id, 'bell_timing_id' => $bellTimingId],
                    ['is_available' => false]
                );
            }
        });

        return redirect()->route('teacher-availability.edit', $teacher)
            ->with('success', 'Availability updated.');
    }
}
