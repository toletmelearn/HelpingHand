<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryIncident;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;

class StudentDisciplineController extends Controller
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

        $incidents = DisciplinaryIncident::with('actions')
            ->where('student_id', $student->id)
            ->orderBy('incident_date', 'desc')
            ->get();

        $totalDemerits = $incidents->sum('demerit_points');

        return view('student.discipline.index', compact('incidents', 'totalDemerits', 'student'));
    }
}
