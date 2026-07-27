<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableSlot;
use App\Models\BellTiming;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $schoolClassId = $request->get('school_class_id');
        $sectionId = $request->get('section_id');

        $classes = SchoolClass::orderBy('class_order')->get();
        $sections = Section::all();
        $teachers = Teacher::all();
        $subjects = Subject::all();

        // Get active bell timings grouped by day of week
        $bellTimings = BellTiming::active()->orderBy('order_index')->get();

        $slots = collect();
        if ($schoolClassId) {
            $slots = TimetableSlot::where('school_class_id', $schoolClassId)
                ->when($sectionId, function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                })
                ->with(['subject', 'teacher', 'bellTiming'])
                ->get();
        }

        return view('admin.timetable.grid', compact(
            'classes',
            'sections',
            'teachers',
            'subjects',
            'bellTimings',
            'slots',
            'schoolClassId',
            'sectionId'
        ));
    }

    public function store(Request $request)
    {
        $this->authorize('create', [
            TimetableSlot::class,
            $request->integer('school_class_id') ?: null,
            $request->integer('section_id') ?: null,
        ]);

        $validated = $request->validate([
            'school_class_id' => 'required|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'bell_timing_id' => 'required|exists:bell_timings,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'room_number' => 'nullable|string|max:50',
            'academic_year' => 'nullable|string|max:20',
        ]);

        // Conflict checking logic before saving
        $conflictCheck = $this->checkSlotConflicts($request);
        if ($conflictCheck['conflict']) {
            return back()->with('error', 'Scheduling conflict: ' . $conflictCheck['message']);
        }

        TimetableSlot::updateOrCreate(
            [
                'school_class_id' => $validated['school_class_id'],
                'section_id' => $validated['section_id'] ?? null,
                'bell_timing_id' => $validated['bell_timing_id'],
            ],
            $validated
        );

        return back()->with('success', 'Timetable slot scheduled successfully.');
    }

    public function destroy($id)
    {
        $slot = TimetableSlot::findOrFail($id);
        $this->authorize('delete', $slot);
        $slot->delete();

        return back()->with('success', 'Timetable slot cleared.');
    }

    public function checkConflictsApi(Request $request)
    {
        $this->authorize('viewAny', TimetableSlot::class);

        $result = $this->checkSlotConflicts($request);
        return response()->json($result);
    }

    private function checkSlotConflicts(Request $request): array
    {
        $id = $request->get('id');
        $teacherId = $request->get('teacher_id');
        $bellTimingId = $request->get('bell_timing_id');
        $roomNumber = $request->get('room_number');

        if (!$teacherId || !$bellTimingId) {
            return ['conflict' => false];
        }

        $bellTiming = BellTiming::findOrFail($bellTimingId);
        $startTime = $bellTiming->start_time->format('H:i:s');
        $endTime = $bellTiming->end_time->format('H:i:s');

        // Find overlapping bell timings on the same day_of_week
        $overlappingTimings = BellTiming::where('day_of_week', $bellTiming->day_of_week)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('start_time', '<', $endTime)
                      ->where('end_time', '>', $startTime);
            })
            ->pluck('id');

        // Check Teacher overlap
        $teacherConflict = TimetableSlot::whereIn('bell_timing_id', $overlappingTimings)
            ->where('teacher_id', $teacherId)
            ->when($id, function ($q) use ($id) {
                $q->where('id', '!=', $id);
            })
            ->first();

        if ($teacherConflict) {
            return [
                'conflict' => true,
                'type' => 'teacher',
                'message' => "Teacher is already scheduled to teach " . ($teacherConflict->schoolClass->name ?? 'another class') . " during this period."
            ];
        }

        // Check Room overlap
        if ($roomNumber) {
            $roomConflict = TimetableSlot::whereIn('bell_timing_id', $overlappingTimings)
                ->where('room_number', $roomNumber)
                ->when($id, function ($q) use ($id) {
                    $q->where('id', '!=', $id);
                })
                ->first();

            if ($roomConflict) {
                return [
                    'conflict' => true,
                    'type' => 'room',
                    'message' => "Room {$roomNumber} is already occupied by Class " . ($roomConflict->schoolClass->name ?? 'another class') . " during this period."
                ];
            }
        }

        return ['conflict' => false];
    }
}
