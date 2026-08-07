<?php

namespace App\Http\Controllers\API;

use App\Models\ParentModel;
use App\Models\Student;
use App\Models\TeacherSubstitution;
use App\Models\TimetableSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * T5 item 2: API counterpart to Parent\TimetableController::today() (the
 * web parent-portal view), for the mobile/app context. Token-gated the
 * same as every other route in this group (auth:sanctum + throttle +
 * ApiAccessControl -- see routes/api.php's protected v1 group).
 *
 * Ownership check, and why it doesn't match this file's siblings: this
 * codebase's Sanctum-token "parent" identity has no WORKING link to a
 * Student via the Guardian system -- DashboardController::parentDashboard()
 * calls Student::whereHas('guardians', ...), but Student only defines the
 * singular guardian() relation (guardians() doesn't exist, so that call
 * throws), and even if it did, guardians.id isn't users.id -- there's no
 * user_id column on guardians at all. That's pre-existing and already
 * reflected in the known-failing SanctumTokenAbilityTest baseline; not
 * fixed here (a real fix is a parent-identity redesign, well beyond one
 * timetable endpoint). StudentController::attendance()/results()/fees()
 * have the opposite problem -- no ownership check at all, any authenticated
 * token can read any student by id. Rather than inherit either gap, this
 * endpoint bridges through the `parents` table by email, the one identity
 * fact that genuinely exists and is populated for a parent account today.
 * Both gaps are flagged in the T5 report and the module completion
 * report's deferred register.
 */
class ParentTimetableController extends BaseApiController
{
    public function today(Request $request, int $studentId): JsonResponse
    {
        $user = $request->user();

        $parentRecord = ParentModel::where('email', $user->email)->first();
        $allowedStudentIds = $parentRecord
            ? $parentRecord->students->pluck('id')->push($parentRecord->student_id)->filter()->unique()
            : collect();

        if (!$parentRecord || !$allowedStudentIds->contains($studentId)) {
            return $this->error("You are not authorized to view this student's timetable.", 403);
        }

        $student = Student::find($studentId);
        if (!$student) {
            return $this->error('Student not found', 404);
        }

        $classId = $student->canonicalClassId();
        if (!$classId) {
            return $this->success(['date' => now()->toDateString(), 'periods' => []], 'No class assigned to this student yet.');
        }

        $today = now();
        $dayOfWeek = $today->format('l');
        $sectionId = $student->section_id;

        $slots = TimetableSlot::published()
            ->where('school_class_id', $classId)
            ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
            }))
            ->whereHas('bellTiming', fn ($q) => $q->where('day_of_week', $dayOfWeek))
            ->with(['subject', 'teacher', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null)
            ->sortBy(fn (TimetableSlot $s) => $s->bellTiming->order_index)
            ->values();

        $substitutions = TeacherSubstitution::where('class_id', $classId)
            ->whereDate('substitution_date', $today)
            ->where('status', '!=', 'cancelled')
            ->get()
            ->keyBy('bell_timing_id');

        $periods = $slots->map(function (TimetableSlot $slot) use ($substitutions) {
            $sub = $substitutions->get($slot->bell_timing_id);
            $isArrangement = (bool) ($sub && $sub->substitute_teacher_id);

            return [
                'period_name' => $slot->bellTiming->period_name,
                'start_time' => $slot->bellTiming->start_time?->format('H:i'),
                'end_time' => $slot->bellTiming->end_time?->format('H:i'),
                'subject' => $slot->subject->name ?? null,
                'teacher' => $isArrangement ? ($sub->substituteTeacher->name ?? null) : ($slot->teacher->name ?? null),
                'is_arrangement' => $isArrangement,
            ];
        })->values();

        return $this->success([
            'date' => $today->toDateString(),
            'periods' => $periods,
        ], "Today's periods retrieved successfully");
    }
}
