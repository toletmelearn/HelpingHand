<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Result;
use App\Models\FeeCollection;
use App\Models\Exam;
use App\Models\HomeworkNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Support\Attendance\AttendanceCreditCalculator;

class AISmartFeaturesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display AI Smart Features dashboard
     */
    public function index()
    {
        return view('admin.ai-smart-features.index');
    }

    /**
     * Auto generate timetable
     */
    public function autoTimetable()
    {
        // Get all classes, teachers, and subjects
        $classes = \App\Models\SchoolClass::with(['sections'])->get();
        $teachers = Teacher::with(['subjects'])->get();
        $subjects = \App\Models\Subject::all();

        // Generate a basic timetable structure
        $timetable = $this->generateBasicTimetable($classes, $teachers, $subjects);

        return view('admin.ai-smart-features.timetable', compact('timetable', 'classes', 'teachers', 'subjects'));
    }

    /**
     * Generate basic timetable
     */
    private function generateBasicTimetable($classes, $teachers, $subjects)
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $periods = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th'];

        $timetable = [];

        foreach ($classes as $class) {
            $timetable[$class->name] = [];
            foreach ($days as $day) {
                $timetable[$class->name][$day] = [];
                foreach ($periods as $period) {
                    // Assign a random subject/teacher combination
                    $subject = $subjects->random();
                    $teacher = $teachers->random();
                    
                    $timetable[$class->name][$day][$period] = [
                        'subject' => $subject->name ?? 'General',
                        'teacher' => $teacher->name ?? 'TBA',
                        'room' => 'Room ' . rand(101, 210)
                    ];
                }
            }
        }

        return $timetable;
    }

    /**
     * Generate fee defaulter list
     */
    public function feeDefaulterList()
    {
        // Get students with pending fees for 2+ months
        $defaulterList = $this->calculateFeeDefaulters();

        return view('admin.ai-smart-features.defaulter-list', compact('defaulterList'));
    }

    /**
     * Calculate fee defaulters
     */
    private function calculateFeeDefaulters()
    {
        $students = Student::with(['feeAssignments.feeStructure.feeStructureItems', 'feeCollections.feeCollectionItems'])->get();

        $defaulters = [];

        foreach ($students as $student) {
            $assignments = $student->feeAssignments;
            $totalPending = 0;

            foreach ($assignments as $assignment) {
                $structure = $assignment->feeStructure;

                if (!$structure) continue;

                $totalExpected = $structure->feeStructureItems->sum('amount');
                
                $totalPaid = $student->feeCollections()
                    ->where('fee_structure_id', $structure->id)
                    ->sum('final_amount');

                $pending = $totalExpected - $totalPaid;

                if ($pending > 0) {
                    $defaulters[] = [
                        'student' => $student,
                        'class' => $student->class,
                        'total_pending' => $pending,
                        'contact' => $student->mobile,
                        'father_name' => $student->guardian_name
                    ];
                }
            }
        }

        // Sort by pending amount descending
        usort($defaulters, function ($a, $b) {
            return $b['total_pending'] <=> $a['total_pending'];
        });

        return $defaulters;
    }

    /**
     * Generate topper list
     */
    public function topperList(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id'
        ]);

        $examId = $request->exam_id;
        $exam = Exam::findOrFail($examId);

        // Get all results for this exam grouped by student
        $studentResults = Result::where('exam_id', $examId)
            ->select('student_id', DB::raw('SUM(marks_obtained) as total_marks_obtained, AVG(percentage) as average_percentage'))
            ->groupBy('student_id')
            ->orderByDesc('total_marks_obtained')
            ->get();

        $toppers = [];
        foreach ($studentResults as $result) {
            $student = Student::find($result->student_id);
            if ($student) {
                $toppers[] = [
                    'rank' => count($toppers) + 1,
                    'student' => $student,
                    'total_marks' => $result->total_marks_obtained,
                    'average_percentage' => round($result->average_percentage, 2),
                    'grade' => $this->calculateGrade($result->average_percentage)
                ];
            }
        }

        return view('admin.ai-smart-features.topper-list', compact('toppers', 'exam'));
    }

    /**
     * Calculate grade based on percentage
     */
    private function calculateGrade($percentage)
    {
        if ($percentage >= 90) return 'A1';
        if ($percentage >= 80) return 'A2';
        if ($percentage >= 70) return 'B1';
        if ($percentage >= 60) return 'B2';
        if ($percentage >= 50) return 'C1';
        if ($percentage >= 40) return 'C2';
        if ($percentage >= 33) return 'D';
        return 'F';
    }

    /**
     * Attendance warning system
     */
    public function attendanceWarning()
    {
        // Get students with attendance below threshold (e.g., 75%)
        $attendanceWarnings = $this->getAttendanceWarnings();

        return view('admin.ai-smart-features.attendance-warning', compact('attendanceWarnings'));
    }

    /**
     * Get attendance warnings
     */
    private function getAttendanceWarnings()
    {
        $students = Student::with(['attendances' => function($query) {
            $query->whereBetween('date', [Carbon::now()->subDays(30), Carbon::now()]);
        }])->get();

        $warnings = [];

        foreach ($students as $student) {
            $summary = AttendanceCreditCalculator::summarizeRecords($student->attendances, 'status');
            $totalDays = $summary['total_days'];
            $attendancePercentage = $summary['attendance_rate'];

            if ($totalDays > 0) {
                if ($attendancePercentage < 75) { // Below 75% threshold
                    $warnings[] = [
                        'student' => $student,
                        'attendance_percentage' => $attendancePercentage,
                        'total_days' => $totalDays,
                        'present_days' => $summary['present_days'],
                        'absent_days' => $summary['absent_days'],
                        'class' => $student->class,
                        'contact' => $student->mobile
                    ];
                }
            }
        }

        // Sort by attendance percentage ascending (lowest first)
        usort($warnings, function ($a, $b) {
            return $a['attendance_percentage'] <=> $b['attendance_percentage'];
        });

        return $warnings;
    }

    /**
     * Result analysis graph
     */
    public function resultAnalysis(Request $request)
    {
        $request->validate([
            'exam_id' => 'required|exists:exams,id'
        ]);

        $examId = $request->exam_id;
        $exam = Exam::findOrFail($examId);

        // Get overall statistics
        $totalStudents = Result::where('exam_id', $examId)->distinct('student_id')->count();
        $totalResults = Result::where('exam_id', $examId)->count();

        $passCount = Result::where('exam_id', $examId)
            ->where('result_status', 'pass')
            ->distinct('student_id')
            ->count();

        $failCount = $totalStudents - $passCount;
        $passPercentage = $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 2) : 0;

        // Average marks
        $avgPercentage = Result::where('exam_id', $examId)->avg('percentage') ?? 0;

        // Grade distribution
        $gradeDistribution = Result::where('exam_id', $examId)
            ->select('grade', DB::raw('COUNT(*) as count'))
            ->groupBy('grade')
            ->get()
            ->pluck('count', 'grade');

        // Subject-wise analysis
        $subjectAnalysis = Result::where('exam_id', $examId)
            ->select('subject', 
                DB::raw('AVG(percentage) as avg_percentage'),
                DB::raw('MIN(percentage) as min_percentage'),
                DB::raw('MAX(percentage) as max_percentage'),
                DB::raw('COUNT(*) as total_students')
            )
            ->groupBy('subject')
            ->get();

        return view('admin.ai-smart-features.result-analysis', compact(
            'exam',
            'totalStudents',
            'totalResults',
            'passCount',
            'failCount',
            'passPercentage',
            'avgPercentage',
            'gradeDistribution',
            'subjectAnalysis'
        ));
    }

    /**
     * Auto generate report based on criteria
     */
    public function autoGenerateReport(Request $request)
    {
        $request->validate([
            'report_type' => 'required|in:defaulter,attendance,topper,result_analysis',
            'date_range' => 'nullable|string',
            'class_id' => 'nullable|exists:school_classes,id'
        ]);

        $reportType = $request->report_type;
        $classId = $request->class_id;

        switch ($reportType) {
            case 'defaulter':
                $data = $this->calculateFeeDefaulters();
                $title = 'Fee Defaulter Report';
                break;
            case 'attendance':
                $data = $this->getAttendanceWarnings();
                $title = 'Low Attendance Warning Report';
                break;
            case 'topper':
                $data = $this->getTopperData();
                $title = 'Topper Analysis Report';
                break;
            case 'result_analysis':
                $data = $this->getResultAnalysisData();
                $title = 'Result Analysis Report';
                break;
            default:
                $data = [];
                $title = 'Auto Generated Report';
        }

        return view('admin.ai-smart-features.auto-report', compact('data', 'title', 'reportType'));
    }

    /**
     * Get topper data for auto report
     */
    private function getTopperData()
    {
        // Get latest exam
        $latestExam = Exam::latest()->first();

        if (!$latestExam) {
            return [];
        }

        // Get results for latest exam
        $studentResults = Result::where('exam_id', $latestExam->id)
            ->select('student_id', DB::raw('SUM(marks_obtained) as total_marks_obtained, AVG(percentage) as average_percentage'))
            ->groupBy('student_id')
            ->orderByDesc('total_marks_obtained')
            ->limit(10) // Top 10
            ->get();

        $toppers = [];
        foreach ($studentResults as $result) {
            $student = Student::find($result->student_id);
            if ($student) {
                $toppers[] = [
                    'rank' => count($toppers) + 1,
                    'student' => $student,
                    'total_marks' => $result->total_marks_obtained,
                    'average_percentage' => round($result->average_percentage, 2)
                ];
            }
        }

        return $toppers;
    }

    /**
     * Get result analysis data for auto report
     */
    private function getResultAnalysisData()
    {
        // Get latest exam
        $latestExam = Exam::latest()->first();

        if (!$latestExam) {
            return [];
        }

        // Get overall statistics
        $totalStudents = Result::where('exam_id', $latestExam->id)->distinct('student_id')->count();
        $passCount = Result::where('exam_id', $latestExam->id)
            ->where('result_status', 'pass')
            ->distinct('student_id')
            ->count();

        $passPercentage = $totalStudents > 0 ? round(($passCount / $totalStudents) * 100, 2) : 0;
        $avgPercentage = Result::where('exam_id', $latestExam->id)->avg('percentage') ?? 0;

        return [
            'exam_name' => $latestExam->name,
            'total_students' => $totalStudents,
            'pass_count' => $passCount,
            'pass_percentage' => $passPercentage,
            'avg_percentage' => $avgPercentage
        ];
    }

    /**
     * Smart notification system
     */
    public function smartNotifications()
    {
        // Get various notifications based on system data
        $notifications = [
            'fee_defaulters' => $this->calculateFeeDefaulters(),
            'low_attendance' => $this->getAttendanceWarnings(),
            'upcoming_exams' => Exam::whereBetween('exam_date', [Carbon::now(), Carbon::now()->addDays(7)])->get(),
            'pending_homework' => HomeworkNotice::where('type', 'homework')
                ->where('due_date', '<=', Carbon::now()->addDays(3))
                ->where('due_date', '>', Carbon::now())
                ->get()
        ];

        return view('admin.ai-smart-features.notifications', compact('notifications'));
    }
}