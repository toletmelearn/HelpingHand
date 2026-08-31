<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\DatesheetEntry;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Support\Facades\Auth;

/**
 * View-only. Reuses the exact ownership pattern already established this
 * session by TimetableSlotPolicy::teacherAssignedToClassSection() --
 * scoped to classes/sections the teacher holds a real
 * TeacherClassSubjectAssignment for. Published entries only; a teacher
 * never sees a draft/under-review/approved-but-not-yet-published
 * datesheet regardless of assignment.
 */
class TeacherDatesheetController extends Controller
{
    public function index()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin?->teacher;

        if (! $teacher) {
            return redirect()->route('teacher.dashboard')->with('error', 'Teacher record not found.');
        }

        $assignments = TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->get();
        $classIds = $assignments->pluck('class_id')->unique();

        $entries = DatesheetEntry::whereIn('school_class_id', $classIds)
            ->whereHas('datesheet', fn ($q) => $q->where('status', 'published'))
            ->where(function ($q) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $q->orWhere(function ($q2) use ($assignment) {
                        $q2->where('school_class_id', $assignment->class_id)
                            ->where(function ($q3) use ($assignment) {
                                $q3->whereNull('section_id');
                                if ($assignment->section_id) {
                                    $q3->orWhere('section_id', $assignment->section_id);
                                }
                            });
                    });
                }
            })
            ->with(['schoolClass', 'section', 'subject', 'datesheet'])
            ->orderBy('exam_date')
            ->get();

        return view('teacher.datesheets.index', compact('entries'));
    }
}
