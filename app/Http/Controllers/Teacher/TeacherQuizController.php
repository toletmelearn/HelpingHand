<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\OnlineQuiz;
use App\Models\QuizQuestion;
use App\Models\Exam;
use Illuminate\Http\Request;

class TeacherQuizController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:teacher');
    }

    public function index()
    {
        $quizzes = OnlineQuiz::with('exam')->get();
        $exams = Exam::all();
        return view('teacher.quizzes.index', compact('quizzes', 'exams'));
    }

    public function storeQuiz(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'exam_id' => 'nullable|exists:exams,id',
            'duration_minutes' => 'required|integer|min:1',
        ]);

        OnlineQuiz::create($request->all());

        return redirect()->route('teacher.quizzes.index')->with('success', 'Quiz created successfully.');
    }

    public function showQuiz($id)
    {
        $quiz = OnlineQuiz::with('questions')->findOrFail($id);
        return view('teacher.quizzes.show', compact('quiz'));
    }

    public function storeQuestion(Request $request, $quizId)
    {
        $request->validate([
            'question_text' => 'required|string|max:1000',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'correct_option' => 'required|in:a,b,c,d',
        ]);

        $quiz = OnlineQuiz::findOrFail($quizId);

        QuizQuestion::create(array_merge($request->all(), [
            'quiz_id' => $quiz->id,
        ]));

        // Increment total question count
        $quiz->increment('total_questions');

        return redirect()->route('teacher.quizzes.show', $quizId)->with('success', 'Question added successfully.');
    }
}
