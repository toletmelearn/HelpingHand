<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\ExamSeatingArrangement;
use App\Models\ExamInvigilatorDuty;
use App\Models\ExamRelievingDuty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamArrangementController extends Controller
{
    public function __construct()
    {
        // Enforce basic authentication
        $this->middleware(function ($request, $next) {
            $this->checkAccess();
            return $next($request);
        });
    }

    /**
     * Check if current user has Exam Cell/Head or Admin privileges.
     */
    private function checkAccess()
    {
        // Allow Admin
        if (Auth::check() && Auth::user()->role === 'admin') {
            return true;
        }

        // Allow Teacher who is Exam Head or Exam Cell Member
        $teacherLogin = Auth::guard('teacher')->user();
        if ($teacherLogin && $teacherLogin->teacher) {
            if ($teacherLogin->teacher->isExamHead() || $teacherLogin->teacher->isExamCellMember()) {
                return true;
            }
        }

        abort(403, 'Unauthorized. Only Admin, Exam Head, or Exam Cell Members can manage exam arrangements.');
    }

    /**
     * List all exams with arrangement summaries.
     */
    public function index()
    {
        $exams = Exam::orderBy('exam_date', 'desc')->paginate(15);
        
        // Fetch summaries
        $summaries = [];
        foreach ($exams as $exam) {
            $summaries[$exam->id] = [
                'seating_count' => ExamSeatingArrangement::where('exam_id', $exam->id)->count(),
                'invigilator_count' => ExamInvigilatorDuty::where('exam_id', $exam->id)->count(),
                'relieving_count' => ExamRelievingDuty::where('exam_id', $exam->id)->count(),
            ];
        }

        return view('admin.exams.arrangements.index', compact('exams', 'summaries'));
    }

    /**
     * Seating arrangement view.
     */
    public function seatingIndex($examId)
    {
        $exam = Exam::findOrFail($examId);

        // Get all students enrolled in the class of this exam. Reads
        // school_class_id via exam.class_id, not the free-text class/
        // class_name string pair -- those use different vocabularies
        // (e.g. "X" vs "Class 10") and never matched anyone.
        $students = Student::where('school_class_id', $exam->class_id)->orderBy('name')->get();

        if ($students->isEmpty()) {
            return redirect()->route('admin.exams.arrangements.index')
                ->withErrors(['error' => 'No students found in the class for this exam.']);
        }

        // Fetch existing seating
        $seating = ExamSeatingArrangement::where('exam_id', $exam->id)
            ->get()
            ->keyBy('student_id');

        return view('admin.exams.arrangements.seating', compact('exam', 'students', 'seating'));
    }

    /**
     * Auto generate seating arrangement.
     */
    public function generateSeating(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);
        
        $request->validate([
            'room_number' => 'required|string|max:100',
            'seat_prefix' => 'nullable|string|max:10',
            'start_number' => 'required|integer|min:1',
        ]);

        // Reads school_class_id via exam.class_id -- see seatingIndex() above.
        $students = Student::where('school_class_id', $exam->class_id)->orderBy('name')->get();

        if ($students->isEmpty()) {
            return redirect()->back()->withErrors(['error' => 'No students found in the class for this exam.']);
        }

        $room = $request->room_number;
        $prefix = $request->seat_prefix ?? 'Seat-';
        $number = $request->start_number;

        DB::transaction(function () use ($exam, $students, $room, $prefix, &$number) {
            foreach ($students as $student) {
                ExamSeatingArrangement::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'room_number' => $room,
                        'seat_number' => $prefix . $number,
                    ]
                );
                $number++;
            }
        });

        return redirect()->back()->with('success', 'Successfully auto-generated seating arrangements for ' . $students->count() . ' students.');
    }

    /**
     * Save manual seating arrangements.
     */
    public function saveSeating(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);
        
        $request->validate([
            'seating' => 'required|array',
            'seating.*.student_id' => 'required|exists:students,id',
            'seating.*.room_number' => 'required|string|max:100',
            'seating.*.seat_number' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($exam, $request) {
            foreach ($request->seating as $seatData) {
                ExamSeatingArrangement::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'student_id' => $seatData['student_id'],
                    ],
                    [
                        'room_number' => $seatData['room_number'],
                        'seat_number' => $seatData['seat_number'],
                    ]
                );
            }
        });

        return redirect()->back()->with('success', 'Seating arrangements saved successfully.');
    }

    /**
     * Invigilator duty index view.
     */
    public function invigilatorIndex($examId)
    {
        $exam = Exam::findOrFail($examId);
        $teachers = Teacher::active()->orderBy('name')->get();
        
        // Rooms currently assigned to this exam's seating arrangement
        $rooms = ExamSeatingArrangement::where('exam_id', $exam->id)
            ->distinct()
            ->pluck('room_number');

        if ($rooms->isEmpty()) {
            // Fallback default rooms if no seating generated yet
            $rooms = collect(['Room 101', 'Room 102', 'Room 103']);
        }

        // Fetch existing invigilators
        $duties = ExamInvigilatorDuty::where('exam_id', $exam->id)
            ->with('teacher')
            ->get()
            ->keyBy('room_number');

        return view('admin.exams.arrangements.invigilators', compact('exam', 'teachers', 'rooms', 'duties'));
    }

    /**
     * Save invigilator duties.
     */
    public function saveInvigilators(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);
        
        $request->validate([
            'duties' => 'required|array',
            'duties.*.room_number' => 'required|string',
            'duties.*.teacher_id' => 'required|exists:teachers,id',
            'duties.*.role' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($exam, $request) {
            foreach ($request->duties as $dutyData) {
                ExamInvigilatorDuty::updateOrCreate(
                    [
                        'exam_id' => $exam->id,
                        'room_number' => $dutyData['room_number'],
                    ],
                    [
                        'teacher_id' => $dutyData['teacher_id'],
                        'role' => $dutyData['role'],
                    ]
                );
            }
        });

        return redirect()->back()->with('success', 'Invigilator duties saved successfully.');
    }

    /**
     * Relieving duty index view.
     */
    public function relievingIndex($examId)
    {
        $exam = Exam::findOrFail($examId);
        $teachers = Teacher::active()->orderBy('name')->get();
        
        $rooms = ExamSeatingArrangement::where('exam_id', $exam->id)
            ->distinct()
            ->pluck('room_number');

        if ($rooms->isEmpty()) {
            $rooms = collect(['Room 101', 'Room 102', 'Room 103']);
        }

        $duties = ExamRelievingDuty::where('exam_id', $exam->id)
            ->with('teacher')
            ->get();

        return view('admin.exams.arrangements.relieving', compact('exam', 'teachers', 'rooms', 'duties'));
    }

    /**
     * Save relieving duty assignments.
     */
    public function saveRelieving(Request $request, $examId)
    {
        $exam = Exam::findOrFail($examId);
        
        $request->validate([
            'duties' => 'present|array',
            'duties.*.teacher_id' => 'required|exists:teachers,id',
            'duties.*.time_slot' => 'required|string|max:100',
            'duties.*.room_number' => 'required|string|max:100',
        ]);

        DB::transaction(function () use ($exam, $request) {
            // Clear existing relieving duties first
            ExamRelievingDuty::where('exam_id', $exam->id)->delete();

            // Insert new duties
            foreach ($request->duties as $dutyData) {
                ExamRelievingDuty::create([
                    'exam_id' => $exam->id,
                    'teacher_id' => $dutyData['teacher_id'],
                    'time_slot' => $dutyData['time_slot'],
                    'room_number' => $dutyData['room_number'],
                ]);
            }
        });

        return redirect()->back()->with('success', 'Relieving duties saved successfully.');
    }
}
