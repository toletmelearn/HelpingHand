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
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin');
    }

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

    /**
     * Reports V1: this previously crashed with a QueryException the
     * moment a class/section filter was applied -- the attendances table
     * has no class_id/section_id columns at all (only a free-text
     * `class` string; see Attendance::$fillable). Filters now go through
     * a join on students.id, matching the canonical
     * class_id/school_class_id resolution FinanceReportController's own
     * registers already use, which also makes section filtering possible
     * for the first time (the old `class` string has no section
     * granularity to filter on).
     */
    private function getAttendanceAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $query = Attendance::query()->whereBetween('attendances.date', [$dateFilter[0], $dateFilter[1]]);

        if ($classId || $sectionId) {
            $query->join('students', 'students.id', '=', 'attendances.student_id');
            if ($classId) {
                $query->where(function ($q) use ($classId) {
                    $q->where('students.class_id', $classId)->orWhere('students.school_class_id', $classId);
                });
            }
            if ($sectionId) {
                $query->where('students.section_id', $sectionId);
            }
        }

        $records = $query->get(['attendances.status']);
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

    /**
     * Reports V1: this previously crashed unconditionally, on every
     * dashboard load regardless of filters -- exams has no `date` column
     * (it's `exam_date`) and no `results_published` column at all (that
     * concept doesn't exist anywhere in the schema; replaced with a real,
     * available signal: whether the exam has any recorded Result via the
     * existing Exam::results() relationship). Also fixed each stat
     * clone()-ing the query first: where()/whereBetween() mutate the
     * builder in place, so the four counts below were previously
     * cumulative (AND-ed together) instead of four independent totals --
     * same clone-per-metric pattern this controller's own
     * getStudentAnalytics()/getFeeAnalytics() already use. exams has no
     * section_id column either (exams are scoped per-class, not
     * per-section) -- $sectionId is intentionally not applied here.
     */
    private function getExamAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $query = Exam::query();

        if ($classId) {
            $query->where('class_id', $classId);
        }

        return [
            'total_exams' => (clone $query)->whereBetween('created_at', $dateFilter)->count(),
            'upcoming_exams' => (clone $query)->where('exam_date', '>', now())->count(),
            'completed_exams' => (clone $query)->where('status', 'completed')->count(),
            'results_published' => (clone $query)->whereHas('results')->count(),
        ];
    }

    /**
     * Reports V1: crashed unconditionally -- Book has no status/issued_at/
     * due_date columns at all (it only tracks total_quantity per title;
     * issue/return/due-date state lives on BookIssue, one row per physical
     * loan). Fixed to query the table that actually holds this data,
     * library-domain field names unchanged from what BookIssue already
     * uses elsewhere in the codebase (BookController/BookIssueController).
     */
    private function getLibraryAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $issuedCount = \App\Models\BookIssue::where('status', 'issued')->count();

        return [
            'total_books' => (int) Book::sum('total_quantity'),
            'available_books' => max(0, (int) Book::sum('total_quantity') - $issuedCount),
            'issued_books' => $issuedCount,
            'books_issued_this_period' => \App\Models\BookIssue::whereBetween('issue_date', $dateFilter)->count(),
            'overdue_books' => \App\Models\BookIssue::where('due_date', '<', now())->where('status', 'issued')->count(),
        ];
    }

    /**
     * Reports V1: crashed unconditionally -- teacher_biometric_records has
     * no single `status` column; arrival and departure are tracked
     * separately (arrival_status: on_time/late, departure_status:
     * on_time/early_exit -- same columns/values TeacherBiometricController
     * already uses). Also fixed each stat re-querying from the same base
     * ->whereBetween() scope instead of each call further mutating the
     * previous one in place (where()/whereBetween() return $this, so the
     * original chain was cumulative AND-ing every prior condition into
     * each successive count).
     */
    private function getBiometricAnalytics($sessionId, $classId, $sectionId, $dateFilter)
    {
        $base = TeacherBiometricRecord::whereBetween('date', [$dateFilter[0], $dateFilter[1]]);

        if ($classId) {
            $base->join('teachers', 'teacher_biometric_records.teacher_id', '=', 'teachers.id');
        }

        return [
            'total_teacher_records' => (clone $base)->count(),
            'on_time_arrivals' => (clone $base)->where('arrival_status', 'on_time')->count(),
            'late_arrivals' => (clone $base)->where('arrival_status', 'late')->count(),
            'early_departures' => (clone $base)->where('departure_status', 'early_exit')->count(),
            'attendance_rate' => $this->calculateBiometricAttendanceRate($base),
        ];
    }

    private function calculateBiometricAttendanceRate($base)
    {
        $total = (clone $base)->count();
        $present = (clone $base)
            ->whereIn('arrival_status', ['on_time', 'late'])
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
