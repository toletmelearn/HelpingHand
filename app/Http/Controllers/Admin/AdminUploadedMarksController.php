<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUploadedMarksController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        // Get filter options
        $exams = Exam::all();
        $classes = SchoolClass::all();
        $subjects = Subject::distinct()->pluck('name', 'name'); // Get unique subject names
        $teachers = Teacher::all();

        // Apply filters dynamically - show all if no filters selected
        $query = Result::with(['student', 'exam', 'uploadedByTeacher', 'schoolClass']);

        if ($request->exam_id) {
            $query->where('exam_id', $request->exam_id);
        }

        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        if ($request->subject) {
            $query->where('subject', $request->subject);
        }

        if ($request->teacher_id) {
            $query->where('uploaded_by_teacher_id', $request->teacher_id);
        }

        $results = $query->latest()->get();

        // Only show summary if at least one filter is applied
        $hasFilters = $request->filled(['exam_id', 'class_id', 'subject', 'teacher_id']);
        
        if ($hasFilters) {
            $summary = [
                'total_students' => $results->count(),
                'teacher_name' => $request->teacher_id ? Teacher::find($request->teacher_id)?->name : 'All Teachers',
                'subject' => $request->subject ?: 'All Subjects',
                'exam' => $request->exam_id ? Exam::find($request->exam_id)?->name : 'All Exams',
            ];
        } else {
            $summary = null;
        }

        return view('admin.uploaded-marks.index', compact('results', 'exams', 'classes', 'subjects', 'teachers', 'summary'));
    }

    public function exportToExcel(Request $request)
    {
        $this->authorize('viewAny', Result::class);

        // Apply same filters as index method
        $results = Result::with(['student', 'exam', 'uploadedByTeacher', 'schoolClass'])
            ->when($request->exam_id, function ($q) use ($request) {
                return $q->where('exam_id', $request->exam_id);
            })
            ->when($request->class_id, function ($q) use ($request) {
                return $q->where('class_id', $request->class_id);
            })
            ->when($request->subject, function ($q) use ($request) {
                return $q->where('subject', $request->subject);
            })
            ->when($request->teacher_id, function ($q) use ($request) {
                return $q->where('uploaded_by_teacher_id', $request->teacher_id);
            })
            ->latest()
            ->get();

        // Generate CSV content
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="uploaded_marks_' . date('Y-m-d_H-i-s') . '.csv"',
        ];

        $callback = function () use ($results) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Student', 'Roll Number', 'Class', 'Subject', 'Exam', 'Marks Obtained', 'Total Marks', 'Percentage', 'Grade', 'Teacher', 'Status', 'Date Uploaded']);

            foreach ($results as $result) {
                fputcsv($file, [
                    $result->student->name ?? 'N/A',
                    $result->student->roll_number ?? 'N/A',
                    $result->schoolClass->name ?? 'N/A',
                    $result->subject,
                    $result->exam->name ?? 'N/A',
                    $result->marks_obtained,
                    $result->total_marks,
                    round($result->percentage, 2) . '%',
                    $result->grade,
                    $result->uploadedByTeacher->name ?? 'N/A',
                    $result->status,
                    $result->uploaded_at ? $result->uploaded_at->format('Y-m-d H:i:s') : 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function deleteResult($id)
    {
        $this->authorize('delete', Result::class);

        $result = Result::findOrFail($id);
        $result->delete();

        return redirect()->back()->with('success', 'Result deleted successfully.');
    }

    public function unlockResult($id)
    {
        $this->authorize('update', Result::class);

        $result = Result::findOrFail($id);
        $result->is_locked = false;
        $result->save();

        return redirect()->back()->with('success', 'Result unlocked successfully.');
    }
}