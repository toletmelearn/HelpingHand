<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvancedReport;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\FeeCollection;
use App\Models\StudentFeeLedger;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\Book;
use App\Models\TeacherBiometricRecord;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\Attendance\AttendanceCreditCalculator;

class AdvancedReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = AdvancedReport::with('creator')
            ->when($request->module, function ($query) use ($request) {
                return $query->where('module', $request->module);
            })
            ->when($request->type, function ($query) use ($request) {
                return $query->where('type', $request->type);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $modules = ['students', 'fees', 'attendance', 'exams', 'library', 'biometric'];
        $types = ['kpi', 'chart', 'table', 'summary'];

        return view('admin.reports.advanced.index', compact('reports', 'modules', 'types'));
    }

    public function dashboard(Request $request)
    {
        // Get filter parameters
        $academicSessionId = $request->get('academic_session_id');
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $dateRange = $request->get('date_range', 'this_month');

        // Get date range
        $dateFilter = $this->getDateRange($dateRange);

        // Students Analytics
        $studentStats = $this->getStudentAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        
        // Fee Analytics
        $feeStats = $this->getFeeAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        
        // Attendance Analytics
        $attendanceStats = $this->getAttendanceAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        
        // Exam Analytics
        $examStats = $this->getExamAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        
        // Library Analytics
        $libraryStats = $this->getLibraryAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        
        // Biometric Analytics
        $biometricStats = $this->getBiometricAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);

        // Get filter options
        $academicSessions = AcademicSession::all();
        $classes = SchoolClass::all();
        $sections = Section::all();

        return view('admin.reports.advanced.dashboard', compact(
            'studentStats',
            'feeStats',
            'attendanceStats',
            'examStats',
            'libraryStats',
            'biometricStats',
            'academicSessions',
            'classes',
            'sections',
            'academicSessionId',
            'classId',
            'sectionId',
            'dateRange'
        ));
    }

    private function getDateRange($range)
    {
        switch ($range) {
            case 'today':
                return [now()->startOfDay(), now()->endOfDay()];
            case 'this_week':
                return [now()->startOfWeek(), now()->endOfWeek()];
            case 'this_month':
                return [now()->startOfMonth(), now()->endOfMonth()];
            case 'last_month':
                return [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];
            case 'this_year':
                return [now()->startOfYear(), now()->endOfYear()];
            default:
                return [now()->startOfMonth(), now()->endOfMonth()];
        }
    }

    private function getStudentAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $baseQuery = $this->baseStudentAnalyticsQuery($sessionId, $classId, $sectionId);

        return [
            'total_students' => (clone $baseQuery)->count(),
            'new_admissions' => (clone $baseQuery)->whereBetween('created_at', $dateFilter)->count(),
            'passed_out' => $this->countStudentsWithLatestStatus($baseQuery, 'passed_out'),
            'left_school' => $this->countStudentsWithLatestStatus($baseQuery, 'left_school'),
            'active_students' => $this->countActiveStudentsByLatestStatus($baseQuery),
        ];
    }

    private function baseStudentAnalyticsQuery($sessionId, $classId, $sectionId)
    {
        return Student::query()
            ->when($sessionId, function ($query) use ($sessionId) {
                $query->where('academic_session_id', $sessionId);
            })
            ->when($classId, function ($query) use ($classId) {
                $query->where('class_id', $classId);
            })
            ->when($sectionId, function ($query) use ($sectionId) {
                $query->where('section_id', $sectionId);
            });
    }

    private function latestStudentStatusIdsSubquery()
    {
        return DB::table('student_statuses')
            ->selectRaw('MAX(id)')
            ->groupBy('student_id');
    }

    private function countStudentsWithLatestStatus($baseQuery, array|string $statuses): int
    {
        $statuses = (array) $statuses;

        return (clone $baseQuery)
            ->whereIn('students.id', function ($query) use ($statuses) {
                $query->select('student_id')
                    ->from('student_statuses')
                    ->whereIn('id', $this->latestStudentStatusIdsSubquery())
                    ->whereIn('status', $statuses);
            })
            ->count();
    }

    private function countActiveStudentsByLatestStatus($baseQuery): int
    {
        $inactiveStatuses = ['passed_out', 'left_school', 'inactive', 'tc_issued'];

        return (clone $baseQuery)
            ->whereNotIn('students.id', function ($query) use ($inactiveStatuses) {
                $query->select('student_id')
                    ->from('student_statuses')
                    ->whereIn('id', $this->latestStudentStatusIdsSubquery())
                    ->whereIn('status', $inactiveStatuses);
            })
            ->count();
    }

    private function getFeeAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $collectionsQuery = FeeCollection::query();
        $ledgerQuery = StudentFeeLedger::query();

        if ($classId || $sectionId) {
            $collectionsQuery->join('students', 'fee_collections.student_id', '=', 'students.id');
            $ledgerQuery->join('students', 'student_fee_ledgers.student_id', '=', 'students.id');
            if ($classId) {
                $collectionsQuery->where('students.class_id', $classId);
                $ledgerQuery->where('students.class_id', $classId);
            }
            if ($sectionId) {
                $collectionsQuery->where('students.section_id', $sectionId);
                $ledgerQuery->where('students.section_id', $sectionId);
            }
        }

        return [
            'total_fees_collected' => (clone $collectionsQuery)->sum('fee_collections.final_amount'),
            'pending_dues' => (clone $ledgerQuery)->where('student_fee_ledgers.unpaid_amount', '>', 0)->sum('student_fee_ledgers.unpaid_amount'),
            'overdue_fees' => (clone $ledgerQuery)->where('student_fee_ledgers.date', '<', now())->where('student_fee_ledgers.unpaid_amount', '>', 0)->sum('student_fee_ledgers.unpaid_amount'),
            'payments_this_period' => (clone $collectionsQuery)->whereBetween('fee_collections.payment_date', $dateFilter)->count(),
        ];
    }

    private function getAttendanceAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $query = Attendance::query();
        
        if ($classId) {
            $query->where('class_id', $classId);
        }
        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        $records = $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->get(['status']);
        $summary = AttendanceCreditCalculator::summarizeRecords($records, 'status');
        
        return [
            'attendance_rate' => $summary['attendance_rate'],
            'total_attendance' => $summary['total_days'],
            'present_count' => $summary['present_days'],
            'absent_count' => $summary['absent_days'],
            'late_arrivals' => $summary['late_days'],
            'attendance_credit' => $summary['attendance_credit'],
            'half_days' => $summary['half_days'],
            'leave_days' => $summary['leave_days'],
        ];
    }

    private function getExamAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $query = Exam::query();
        
        if ($classId) {
            $query->where('class_id', $classId);
        }
        if ($sectionId) {
            $query->where('section_id', $sectionId);
        }

        return [
            'total_exams' => $query->whereBetween('created_at', $dateFilter)->count(),
            'upcoming_exams' => $query->where('date', '>', now())->count(),
            'completed_exams' => $query->where('status', 'completed')->count(),
            'results_published' => $query->where('results_published', true)->count(),
        ];
    }

    private function getLibraryAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        return [
            'total_books' => Book::count(),
            'available_books' => Book::where('status', 'available')->count(),
            'issued_books' => Book::where('status', 'issued')->count(),
            'books_issued_this_period' => Book::whereBetween('issued_at', $dateFilter)->count(),
            'overdue_books' => Book::where('due_date', '<', now())->where('status', 'issued')->count(),
        ];
    }

    private function getBiometricAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $query = TeacherBiometricRecord::query();
        
        if ($classId) {
            // For teacher assignments to classes
            $query->join('teachers', 'teacher_biometric_records.teacher_id', '=', 'teachers.id');
        }

        return [
            'total_teacher_records' => $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->count(),
            'on_time_arrivals' => $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->where('status', 'on_time')->count(),
            'late_arrivals' => $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->where('status', 'late')->count(),
            'early_departures' => $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->where('status', 'early_departure')->count(),
            'attendance_rate' => $this->calculateBiometricAttendanceRate($query, $dateFilter),
        ];
    }

    private function calculateBiometricAttendanceRate($query, $dateFilter)
    {
        $total = $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])->count();
        $present = $query->whereBetween('date', [$dateFilter[0], $dateFilter[1]])
            ->whereIn('status', ['on_time', 'late'])
            ->count();
        
        return $total > 0 ? round(($present / $total) * 100, 2) : 0;
    }

    public function create()
    {
        $modules = ['students', 'fees', 'attendance', 'exams', 'library', 'biometric'];
        $types = ['kpi', 'chart', 'table', 'summary'];
        $chartTypes = ['bar', 'line', 'pie', 'area'];
        
        return view('admin.reports.advanced.create', compact('modules', 'types', 'chartTypes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:kpi,chart,table,summary',
            'module' => 'required|in:students,fees,attendance,exams,library,biometric',
            'chart_type' => 'nullable|in:bar,line,pie,area',
            'is_active' => 'boolean',
        ]);

        $report = AdvancedReport::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => $request->type,
            'module' => $request->module,
            'chart_type' => $request->chart_type,
            'is_active' => $request->is_active ?? true,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.advanced-reports.index')
            ->with('success', 'Advanced report created successfully.');
    }

    public function show(AdvancedReport $advancedReport)
    {
        return view('admin.reports.advanced.show', compact('advancedReport'));
    }

    public function edit(AdvancedReport $advancedReport)
    {
        $modules = ['students', 'fees', 'attendance', 'exams', 'library', 'biometric'];
        $types = ['kpi', 'chart', 'table', 'summary'];
        $chartTypes = ['bar', 'line', 'pie', 'area'];
        
        return view('admin.reports.advanced.edit', compact('advancedReport', 'modules', 'types', 'chartTypes'));
    }

    public function update(Request $request, AdvancedReport $advancedReport)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:kpi,chart,table,summary',
            'module' => 'required|in:students,fees,attendance,exams,library,biometric',
            'chart_type' => 'nullable|in:bar,line,pie,area',
            'is_active' => 'boolean',
        ]);

        $advancedReport->update($request->only([
            'name', 'description', 'type', 'module', 'chart_type', 'is_active'
        ]));

        return redirect()->route('admin.advanced-reports.index')
            ->with('success', 'Advanced report updated successfully.');
    }

    public function destroy(AdvancedReport $advancedReport)
    {
        $advancedReport->delete();

        return redirect()->route('admin.advanced-reports.index')
            ->with('success', 'Advanced report deleted successfully.');
    }

    public function export(Request $request, AdvancedReport $advancedReport, $format = 'pdf')
    {
        // Get filter parameters
        $academicSessionId = $request->get('academic_session_id');
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $dateRange = $request->get('date_range', 'this_month');

        // Get date range
        $dateFilter = $this->getDateRange($dateRange);

        // Get all analytics data
        $studentStats = $this->getStudentAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        $feeStats = $this->getFeeAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        $attendanceStats = $this->getAttendanceAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        $examStats = $this->getExamAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        $libraryStats = $this->getLibraryAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);
        $biometricStats = $this->getBiometricAnalytics($academicSessionId, $classId, $sectionId, $dateFilter);

        $data = compact(
            'studentStats', 'feeStats', 'attendanceStats', 
            'examStats', 'libraryStats', 'biometricStats',
            'academicSessionId', 'classId', 'sectionId', 'dateRange'
        );

        if ($format === 'pdf') {
            return $this->exportPdf($data);
        } elseif ($format === 'excel') {
            return $this->exportExcel($data);
        }

        return redirect()->back()->with('error', 'Invalid export format');
    }

    private function exportPdf($data)
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.reports.advanced.export-pdf', $data);
        return $pdf->download('advanced-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function exportExcel($data)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AdvancedReportExport($data), 
            'advanced-report-' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
