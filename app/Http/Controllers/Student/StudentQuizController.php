<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\OnlineQuiz;
use App\Models\QuizSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentQuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getStudent()
    {
        $user = Auth::user();
        return Student::where('user_id', $user->id)->first() ?? $user->student;
    }

    public function index()
    {
        $student = $this->getStudent();
        if (!$student) {
            return redirect()->back()->with('error', 'Student profile not found.');
        }

        $quizzes = OnlineQuiz::with('exam')->get();
        $submissions = QuizSubmission::where('student_id', $student->id)->get()->keyBy('quiz_id');

        return view('student.quizzes.index', compact('quizzes', 'submissions', 'student'));
    }

    public function showTake($id)
    {
        $student = $this->getStudent();
        $quiz = OnlineQuiz::with('questions')->findOrFail($id);

        // Check if student already took it
        $existing = QuizSubmission::where('student_id', $student->id)->where('quiz_id', $quiz->id)->first();
        if ($existing) {
            return redirect()->route('student.quizzes.index')->with('error', 'You have already taken this quiz.');
        }

        return view('student.quizzes.take', compact('quiz', 'student'));
    }

    public function storeSubmission(Request $request, $id)
    {
        $student = $this->getStudent();
        $quiz = OnlineQuiz::with('questions')->findOrFail($id);

        $answers = $request->input('answers', []);
        $score = 0;

        foreach ($quiz->questions as $question) {
            $submitted = $answers[$question->id] ?? null;
            if ($submitted === $question->correct_option) {
                $score++;
            }
        }

        QuizSubmission::create([
            'student_id' => $student->id,
            'quiz_id' => $quiz->id,
            'score' => $score,
            'answers_json' => $answers,
        ]);

        return redirect()->route('student.quizzes.index')->with('success', "Quiz completed! You scored {$score}/{$quiz->total_questions}.");
    }
}
