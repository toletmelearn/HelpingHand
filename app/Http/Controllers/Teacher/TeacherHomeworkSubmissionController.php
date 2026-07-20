<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\HomeworkNotice;
use App\Models\HomeworkSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherHomeworkSubmissionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    public function index($homeworkId)
    {
        $homework = HomeworkNotice::where('type', 'homework')->findOrFail($homeworkId);
        
        $submissions = HomeworkSubmission::where('homework_notice_id', $homeworkId)
            ->with('student')
            ->orderBy('submission_date', 'desc')
            ->get();

        return view('teacher.homework-submissions.index', compact('homework', 'submissions'));
    }

    public function evaluateForm($submissionId)
    {
        $submission = HomeworkSubmission::with(['student', 'homeworkNotice'])->findOrFail($submissionId);
        return view('teacher.homework-submissions.evaluate', compact('submission'));
    }

    public function storeEvaluation(Request $request, $submissionId)
    {
        $request->validate([
            'marks_obtained' => 'nullable|numeric|min:0',
            'grade' => 'nullable|string|max:10',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $submission = HomeworkSubmission::findOrFail($submissionId);
        
        $teacherLogin = Auth::guard('teacher')->user();

        $submission->update([
            'marks_obtained' => $request->marks_obtained,
            'grade' => $request->grade,
            'remarks' => $request->remarks,
            'status' => 'evaluated',
            'evaluated_by' => $teacherLogin->teacher_id, // Map to teacher model ID
            'evaluated_at' => now(),
        ]);

        return redirect()->route('teacher.homework.submissions.index', $submission->homework_notice_id)
            ->with('success', 'Homework evaluated and graded successfully.');
    }
}
