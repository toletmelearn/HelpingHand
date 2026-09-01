<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AdmitCard;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\TeacherClassSubjectAssignment;
use Illuminate\Support\Facades\Auth;

/**
 * View-only, for exam-hall verification. Scoped the same way as
 * TeacherDatesheetController::index() -- classes/sections the teacher
 * holds a real TeacherClassSubjectAssignment for -- and reuses
 * Parent/StudentAdmitCardController's exact published/locked-only,
 * revoked-is-never-visible ownership pattern.
 */
class TeacherAdmitCardController extends Controller
{
    private function assignedClassSectionQuery()
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin?->teacher;

        if (! $teacher) {
            return null;
        }

        return TeacherClassSubjectAssignment::where('teacher_id', $teacher->id)->get();
    }

    private function studentInAssignments($student, $assignments): bool
    {
        if (! $student) {
            return false;
        }

        foreach ($assignments as $assignment) {
            if ((int) $student->school_class_id !== (int) $assignment->class_id) {
                continue;
            }

            if (! $assignment->section_id || (int) $student->section_id === (int) $assignment->section_id) {
                return true;
            }
        }

        return false;
    }

    public function index()
    {
        $assignments = $this->assignedClassSectionQuery();

        if ($assignments === null) {
            return redirect()->route('teacher.dashboard')->with('error', 'Teacher record not found.');
        }

        $classIds = $assignments->pluck('class_id')->unique();

        $admitCards = AdmitCard::with(['student', 'exam', 'format'])
            ->whereIn('status', ['published', 'locked'])
            ->whereHas('student', function ($q) use ($classIds) {
                $q->whereIn('school_class_id', $classIds);
            })
            ->get()
            ->filter(fn ($admitCard) => $this->studentInAssignments($admitCard->student, $assignments))
            ->sortBy(fn ($admitCard) => optional($admitCard->exam)->exam_date)
            ->values();

        return view('teacher.admit-cards.index', compact('admitCards'));
    }

    public function show(AdmitCard $admitCard)
    {
        $assignments = $this->assignedClassSectionQuery();

        if ($assignments === null || ! $this->studentInAssignments($admitCard->student, $assignments)) {
            abort(403, 'Unauthorized to view this admit card.');
        }

        if (! in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available yet or has been revoked.');
        }

        return view('teacher.admit-cards.show', compact('admitCard'));
    }

    public function downloadPdf(AdmitCard $admitCard)
    {
        $assignments = $this->assignedClassSectionQuery();

        if ($assignments === null || ! $this->studentInAssignments($admitCard->student, $assignments)) {
            abort(403, 'Unauthorized to download this admit card.');
        }

        if (! in_array($admitCard->status, ['published', 'locked']) || $admitCard->status === 'revoked') {
            abort(403, 'This admit card is not available for download or has been revoked.');
        }

        $pdf = Pdf::loadView('student.admit-cards.pdf', compact('admitCard'));

        return $pdf->download("admit-card-{$admitCard->id}.pdf");
    }
}
