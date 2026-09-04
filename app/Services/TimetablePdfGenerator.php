<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\TimetableSlot;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * Priority 1.4: self-service PDF export of the weekly timetable already
 * shown on Student\StudentTimetableController::weekly(),
 * Parent\TimetableController::weekly() (a parent's active child IS a
 * Student -- identical grid, just a different subtitle), and
 * Teacher\TeacherTimetableController::index(). Builds a genuine period x
 * day grid -- distinct from those pages' own day-stacked table layout,
 * which reads fine on screen but wastes paper printed -- from the same
 * published TimetableSlot rows, through one shared print template.
 */
class TimetablePdfGenerator
{
    public function generateStudentTimetablePdf(Student $student): DomPdf
    {
        return $this->renderPdf($this->classGridFor($student, $student->name, 'Student Timetable'));
    }

    /** A parent's active child is a Student -- identical grid, different subtitle only. */
    public function generateParentChildTimetablePdf(Student $student): DomPdf
    {
        return $this->renderPdf($this->classGridFor($student, $student->name, "Child's Timetable"));
    }

    public function generateTeacherTimetablePdf(Teacher $teacher): DomPdf
    {
        $slots = TimetableSlot::published()
            ->where(fn ($q) => $q->where('teacher_id', $teacher->id)->orWhere('co_teacher_id', $teacher->id))
            ->with(['subject', 'schoolClass', 'section', 'bellTiming'])
            ->get()
            ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);

        $gridData = $this->buildGrid($slots, function (TimetableSlot $slot) {
            $className = $slot->schoolClass->name ?? '';
            if ($slot->section) {
                $className .= ' - ' . $slot->section->name;
            }

            return array_filter([
                $className,
                $slot->subject->name ?? '',
                $slot->room_number ? "Room: {$slot->room_number}" : null,
            ]);
        });

        return $this->renderPdf(array_merge($gridData, [
            'title' => $teacher->name,
            'subtitle' => 'Teacher Timetable',
        ]));
    }

    private function classGridFor(Student $student, string $title, string $subtitle): array
    {
        $classId = $student->canonicalClassId();
        $sectionId = $student->section_id;

        $slots = collect();
        if ($classId) {
            $slots = TimetableSlot::published()
                ->where('school_class_id', $classId)
                ->when($sectionId, fn ($q) => $q->where(function ($q2) use ($sectionId) {
                    $q2->whereNull('section_id')->orWhere('section_id', $sectionId);
                }))
                ->with(['subject', 'teacher', 'bellTiming'])
                ->get()
                ->filter(fn (TimetableSlot $s) => $s->bellTiming !== null);
        }

        $gridData = $this->buildGrid($slots, fn (TimetableSlot $slot) => array_filter([
            $slot->subject->name ?? '',
            $slot->teacher->name ?? '',
            $slot->room_number ? "Room: {$slot->room_number}" : null,
        ]));

        return array_merge($gridData, ['title' => $title, 'subtitle' => $subtitle]);
    }

    /**
     * @param Collection<int, TimetableSlot> $slots
     * @param callable(TimetableSlot): array<string> $cellLines the lines to
     *   print inside a cell, e.g. [subject, teacher, room] or [class, subject, room].
     * @return array{days: Collection<int,string>, periods: Collection<int,string>, grid: array}
     */
    private function buildGrid(Collection $slots, callable $cellLines): array
    {
        $days = $slots->pluck('bellTiming.day_of_week')->unique()
            ->sortBy(fn ($day) => $slots->firstWhere('bellTiming.day_of_week', $day)?->bellTiming?->day_order)
            ->values();

        // Distinct periods across every day, ordered by order_index --
        // schools label periods consistently (P1, P2, ...) across days
        // even when the underlying BellTiming row (and its actual
        // start/end time) differs per day.
        $periods = $slots->pluck('bellTiming.period_name')->unique()
            ->sortBy(fn ($name) => $slots->firstWhere('bellTiming.period_name', $name)?->bellTiming?->order_index)
            ->values();

        $grid = [];
        foreach ($periods as $period) {
            foreach ($days as $day) {
                $slot = $slots->first(fn (TimetableSlot $s) => $s->bellTiming->period_name === $period && $s->bellTiming->day_of_week === $day);
                $grid[$period][$day] = $slot ? $cellLines($slot) : null;
            }
        }

        return ['days' => $days, 'periods' => $periods, 'grid' => $grid];
    }

    /**
     * Renders the timetable data array through the shared print template.
     * Returns raw HTML (not yet turned into a PDF) -- kept separate so the
     * template's markup can be exercised/asserted on directly, and so a
     * future non-PDF consumer (e.g. an on-screen print preview) could
     * reuse it without going through dompdf at all.
     */
    public function generateTimetableView(array $gridData): string
    {
        return view('timetable.pdf-template', array_merge([
            'schoolName' => config('app.name', 'School'),
            'generatedAt' => now(),
        ], $gridData))->render();
    }

    private function renderPdf(array $gridData): DomPdf
    {
        $pdf = Pdf::loadHTML($this->generateTimetableView($gridData));
        $pdf->setPaper('A4', 'landscape');

        return $pdf;
    }
}
