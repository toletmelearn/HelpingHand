<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\HomeworkNotice;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentHomeworkController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getStudent()
    {
        $user = Auth::user();
        // Resolve student profile
        return Student::where('user_id', $user->id)->first() ?? $user->student;
    }

    public function index()
    {
        $student = $this->getStudent();
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }

        // Fetch homework for student's class/section
        $homeworks = HomeworkNotice::where('type', 'homework')
            ->where(function($query) use ($student) {
                $query->where('class_id', $student->class_id);
            })
            ->with(['submissions' => function($q) use ($student) {
                $q->where('student_id', $student->id);
            }])
            ->orderBy('due_date', 'desc')
            ->get();

        return view('student.homework.index', compact('homeworks', 'student'));
    }

    public function submitForm($homeworkId)
    {
        $student = $this->getStudent();
        if (!$student) {
            abort(404, 'Student profile not found.');
        }

        $homework = HomeworkNotice::where('type', 'homework')
            ->where('class_id', $student->class_id)
            ->findOrFail($homeworkId);

        $submission = HomeworkSubmission::where('homework_notice_id', $homeworkId)
            ->where('student_id', $student->id)
            ->first();

        return view('student.homework.submit', compact('homework', 'submission'));
    }

    public function storeSubmission(Request $request, $homeworkId)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,docx,doc|max:5120', // Max 5MB
            'student_notes' => 'nullable|string|max:1000',
        ]);

        $student = $this->getStudent();
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }

        $homework = HomeworkNotice::where('type', 'homework')
            ->where('class_id', $student->class_id)
            ->findOrFail($homeworkId);

        // Upload attachment
        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('homework_submissions', 'public');
        }

        // Create or update submission
        HomeworkSubmission::updateOrCreate(
            [
                'homework_notice_id' => $homework->id,
                'student_id' => $student->id,
            ],
            [
                'submission_date' => now(),
                'file_path' => $filePath,
                'student_notes' => $request->student_notes,
                'status' => 'submitted',
            ]
        );

        return redirect()->route('student.homework.index')
            ->with('success', 'Homework submitted successfully.');
    }
}
