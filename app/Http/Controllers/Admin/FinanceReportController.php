<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinanceReportController extends Controller
{
    public static $reportsList = [
        'collection_register' => 'Collection Register',
        'cash_book' => 'Cash Book',
        'day_book' => 'Day Book',
        'ledger_book' => 'Ledger Book',
        'outstanding_register' => 'Outstanding Register',
        'demand_register' => 'Demand Register',
        'refund_register' => 'Refund Register',
        'discount_register' => 'Discount Register',
        'late_fee_register' => 'Late Fee Register',
        'head_wise' => 'Head-wise Collection',
        'month_wise' => 'Month-wise Collection',
        'class_wise' => 'Class-wise Collection',
        'session_comparison' => 'Session Comparison',
        'income_summary' => 'Income Summary',
        'cancelled_receipts' => 'Cancelled Receipt Register',
        'receipt_register' => 'Receipt Register'
    ];

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-finance-reports');
    }

    /**
     * Reporting dashboard view and filter results page.
     */
    public function index(Request $request)
    {
        $activeReport = $request->get('type', 'collection_register');
        
        // Load filter metadata
        $classes = SchoolClass::orderBy('class_order', 'asc')->get();
        $sections = \App\Models\Section::orderBy('name')->get();
        // Every student needs to be selectable for per-student drill-down
        // reports (Ledger Book, Outstanding Register, etc.) -- an earlier
        // take(100) silently made ~88% of the school's students unselectable.
        $students = Student::orderBy('name', 'asc')->get(['id', 'name', 'admission_no']);

        // Build filtered query
        $query = $this->buildReportQuery($activeReport, $request);

        // Fetch data
        if ($activeReport === 'month_wise') {
            // Month-wise grouping done in PHP to ensure SQLite/MySQL compatibility
            $data = $this->formatMonthWiseData($query->get());
        } else {
            $data = $query->paginate(20)->withQueryString();
        }

        $reportTitle = self::$reportsList[$activeReport] ?? 'Financial Report';

        return view('admin.fees.reports.index', compact(
            'classes', 'sections', 'students', 'activeReport', 'data', 'reportTitle'
        ));
    }

    /**
     * Streaming large dataset export (Excel / CSV / PDF / Print).
     */
    public function export(Request $request)
    {
        $type = $request->get('type', 'collection_register');
        $format = $request->get('format', 'csv');
        $title = self::$reportsList[$type] ?? 'Financial Report';
        $filename = str_replace(' ', '_', strtolower($title)) . '_' . date('Ymd_His');

        $query = $this->buildReportQuery($type, $request);

        if ($format === 'pdf' || $format === 'print') {
            // PDF/Print takes up to 1000 records for layout rendering
            if ($type === 'month_wise') {
                $data = $this->formatMonthWiseData($query->get());
            } else {
                $data = $query->take(1000)->get();
            }
            return view('admin.fees.reports.export', compact('data', 'type', 'title', 'format'));
        }

        // "Excel" export is a plain CSV stream (same pattern as the Fee
        // Demand/Collection Registers and Defaulter Registry exports) --
        // labeling it .xls/application/vnd.ms-excel while the actual
        // content is comma-separated text made Excel try to parse it as
        // its native binary format, fail, and dump the whole file into a
        // single cell instead of splitting it into rows and columns.
        $contentType = 'text/csv';
        $extension = 'csv';

        // Stream file download to prevent PHP out-of-memory errors
        return response()->streamDownload(function () use ($type, $query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Excel UTF-8 BOM

            // 1. Column headers
            fputcsv($handle, $this->getReportHeaders($type));

            // 2. Data rows
            if ($type === 'month_wise') {
                $formatted = $this->formatMonthWiseData($query->get());
                foreach ($formatted as $row) {
                    fputcsv($handle, $this->formatRowData($row, $type));
                }
            } else {
                foreach ($query->cursor() as $row) {
                    fputcsv($handle, $this->formatRowData($row, $type));
                }
            }

            fclose($handle);
        }, "{$filename}.{$extension}", [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Expires' => '0',
        ]);
    }

    /**
     * Build report queries with active filters.
     */
    protected function buildReportQuery(string $type, Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $classId = $request->get('class_id');
        $sectionId = $request->get('section_id');
        $studentId = $request->get('student_id');
        $paymentMode = $request->get('payment_mode');

        switch ($type) {
            case 'collection_register':
                $query = DB::table('fee_collections')
                    ->join('students', 'students.id', '=', 'fee_collections.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->leftJoin('users', 'users.id', '=', 'fee_collections.collected_by')
                    ->select(
                        'fee_collections.receipt_no',
                        'students.admission_no',
                        'students.name as student_name',
                        'school_classes.name as class_name',
                        'fee_collections.payment_date',
                        'fee_collections.payment_mode',
                        'fee_collections.final_amount',
                        'users.name as cashier_name'
                    );

                $this->applyStandardFilters($query, 'fee_collections.payment_date', $startDate, $endDate, $classId, $studentId);
                if ($paymentMode) {
                    $query->where('fee_collections.payment_mode', $paymentMode);
                }
                $query->orderBy('fee_collections.payment_date', 'desc');
                break;

            case 'cash_book':
                $collections = DB::table('fee_collections')
                    ->join('students', 'students.id', '=', 'fee_collections.student_id')
                    ->select(
                        'fee_collections.payment_date as date',
                        'fee_collections.receipt_no as reference',
                        'students.name as particulars',
                        'fee_collections.final_amount as debit',
                        DB::raw('0.00 as credit'),
                        'students.id as student_id',
                        'students.class_id as class_id',
                        'students.school_class_id as school_class_id'
                    )
                    ->where('fee_collections.payment_mode', 'cash');

                $refunds = DB::table('fee_refunds')
                    ->join('students', 'students.id', '=', 'fee_refunds.student_id')
                    ->select(
                        DB::raw('DATE(fee_refunds.processed_at) as date'),
                        DB::raw("CAST(fee_refunds.id AS CHAR) as reference"),
                        'students.name as particulars',
                        DB::raw('0.00 as debit'),
                        'fee_refunds.amount as credit',
                        'students.id as student_id',
                        'students.class_id as class_id',
                        'students.school_class_id as school_class_id'
                    )
                    ->where('fee_refunds.payment_mode', 'cash');

                if ($startDate) {
                    $collections->where('fee_collections.payment_date', '>=', $startDate);
                    $refunds->where('fee_refunds.processed_at', '>=', $startDate . ' 00:00:00');
                }
                if ($endDate) {
                    $collections->where('fee_collections.payment_date', '<=', $endDate);
                    $refunds->where('fee_refunds.processed_at', '<=', $endDate . ' 23:59:59');
                }
                if ($classId) {
                    $collections->where(function($q) use($classId) {
                        $q->where('students.class_id', $classId)->orWhere('students.school_class_id', $classId);
                    });
                    $refunds->where(function($q) use($classId) {
                        $q->where('students.class_id', $classId)->orWhere('students.school_class_id', $classId);
                    });
                }
                if ($studentId) {
                    $collections->where('students.id', $studentId);
                    $refunds->where('students.id', $studentId);
                }

                $query = $collections->unionAll($refunds)->orderBy('date', 'desc');
                break;

            case 'day_book':
                $query = DB::table('student_fee_ledgers')
                    ->join('students', 'students.id', '=', 'student_fee_ledgers.student_id')
                    ->select(
                        'student_fee_ledgers.date',
                        'students.name as student_name',
                        'student_fee_ledgers.description',
                        'student_fee_ledgers.debit',
                        'student_fee_ledgers.credit'
                    );

                $this->applyStandardFilters($query, 'student_fee_ledgers.date', $startDate, $endDate, $classId, $studentId);
                $query->orderBy('student_fee_ledgers.date', 'desc');
                break;

            case 'ledger_book':
                $query = DB::table('student_fee_ledgers')
                    ->select('date', 'description', 'reference_type', 'reference_id', 'debit', 'credit', 'running_balance');

                if ($studentId) {
                    $query->where('student_id', $studentId);
                } else {
                    $query->whereRaw('1 = 0');
                }
                if ($startDate) {
                    $query->where('date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('date', '<=', $endDate);
                }
                $query->orderBy('date', 'asc')->orderBy('id', 'asc');
                break;

            case 'outstanding_register':
                $query = DB::table('student_fee_ledgers')
                    ->join('students', 'students.id', '=', 'student_fee_ledgers.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->select(
                        'students.admission_no',
                        'students.name as student_name',
                        'school_classes.name as class_name'
                    )
                    ->selectRaw('SUM(student_fee_ledgers.debit) as total_charged')
                    ->selectRaw('SUM(student_fee_ledgers.credit) as total_paid')
                    ->selectRaw('SUM(student_fee_ledgers.debit) - SUM(student_fee_ledgers.credit) as outstanding_balance')
                    ->groupBy('students.admission_no', 'students.name', 'school_classes.name', 'school_classes.class_order')
                    ->havingRaw('(SUM(student_fee_ledgers.debit) - SUM(student_fee_ledgers.credit)) > 0.01')
                    ->orderBy('school_classes.class_order', 'asc')
                    ->orderBy('students.name', 'asc');

                if ($classId) {
                    $query->where(function($q) use($classId) {
                        $q->where('students.class_id', $classId)->orWhere('students.school_class_id', $classId);
                    });
                }
                if ($sectionId) {
                    $query->where('students.section_id', $sectionId);
                }
                if ($studentId) {
                    $query->where('students.id', $studentId);
                }
                break;

            case 'demand_register':
                $query = DB::table('student_fee_ledgers')
                    ->join('students', 'students.id', '=', 'student_fee_ledgers.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->select(
                        'students.admission_no',
                        'students.name as student_name',
                        'school_classes.name as class_name',
                        'student_fee_ledgers.date as demand_date',
                        'student_fee_ledgers.description',
                        'student_fee_ledgers.debit as amount'
                    )
                    ->where('student_fee_ledgers.debit', '>', 0);

                $this->applyStandardFilters($query, 'student_fee_ledgers.date', $startDate, $endDate, $classId, $studentId);
                if ($sectionId) {
                    $query->where('students.section_id', $sectionId);
                }
                $query->orderBy('school_classes.class_order', 'asc')
                    ->orderBy('students.name', 'asc')
                    ->orderBy('student_fee_ledgers.date', 'desc');
                break;

            case 'refund_register':
                $query = DB::table('fee_refunds')
                    ->join('students', 'students.id', '=', 'fee_refunds.student_id')
                    ->select(
                        'fee_refunds.processed_at as date',
                        'students.name as student_name',
                        'fee_refunds.type',
                        'fee_refunds.amount',
                        'fee_refunds.payment_mode',
                        'fee_refunds.reason'
                    );

                $this->applyStandardFilters($query, 'fee_refunds.processed_at', $startDate, $endDate, $classId, $studentId);
                if ($paymentMode) {
                    $query->where('fee_refunds.payment_mode', $paymentMode);
                }
                $query->orderBy('fee_refunds.processed_at', 'desc');
                break;

            case 'discount_register':
                $query = DB::table('student_discounts_applied')
                    ->join('students', 'students.id', '=', 'student_discounts_applied.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->join('discount_rules', 'discount_rules.id', '=', 'student_discounts_applied.discount_rule_id')
                    ->select(
                        'students.name as student_name',
                        'school_classes.name as class_name',
                        'discount_rules.name as discount_name',
                        'student_discounts_applied.amount',
                        'student_discounts_applied.status'
                    );

                $this->applyStandardFilters($query, 'student_discounts_applied.created_at', $startDate, $endDate, $classId, $studentId);
                $query->orderBy('student_discounts_applied.created_at', 'desc');
                break;

            case 'late_fee_register':
                $query = DB::table('fee_collections')
                    ->join('students', 'students.id', '=', 'fee_collections.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->select(
                        'fee_collections.payment_date as date',
                        'students.name as student_name',
                        'school_classes.name as class_name',
                        'fee_collections.late_fine as late_fee_collected'
                    )
                    ->where('fee_collections.late_fine', '>', 0);

                $this->applyStandardFilters($query, 'fee_collections.payment_date', $startDate, $endDate, $classId, $studentId);
                $query->orderBy('fee_collections.payment_date', 'desc');
                break;

            case 'head_wise':
                $query = DB::table('fee_collection_items')
                    ->join('fee_types', 'fee_types.id', '=', 'fee_collection_items.fee_type_id')
                    ->join('fee_collections', 'fee_collections.id', '=', 'fee_collection_items.fee_collection_id')
                    ->select('fee_types.name as head_name')
                    ->selectRaw('SUM(fee_collection_items.amount) as total_collected')
                    ->groupBy('fee_types.name');

                if ($startDate) {
                    $query->where('fee_collections.payment_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('fee_collections.payment_date', '<=', $endDate);
                }
                break;

            case 'month_wise':
                // Queries raw collections then groups in PHP
                $query = DB::table('fee_collections')
                    ->select('payment_date', 'payment_mode', 'final_amount');

                if ($startDate) {
                    $query->where('payment_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('payment_date', '<=', $endDate);
                }
                break;

            case 'class_wise':
                $query = DB::table('fee_collections')
                    ->join('students', 'students.id', '=', 'fee_collections.student_id')
                    ->leftJoin('school_classes', 'school_classes.id', '=', DB::raw('COALESCE(students.class_id, students.school_class_id)'))
                    ->select('school_classes.name as class_name')
                    ->selectRaw('SUM(fee_collections.final_amount) as total_collected')
                    ->groupBy('school_classes.name');

                if ($startDate) {
                    $query->where('fee_collections.payment_date', '>=', $startDate);
                }
                if ($endDate) {
                    $query->where('fee_collections.payment_date', '<=', $endDate);
                }
                break;

            case 'session_comparison':
                $query = DB::table('student_fee_ledgers')
                    ->select('academic_year')
                    ->selectRaw('SUM(debit) as total_demand')
                    ->selectRaw('SUM(credit) as total_collected')
                    ->selectRaw('SUM(debit) - SUM(credit) as total_outstanding')
                    ->groupBy('academic_year')
                    ->whereNotNull('academic_year')
                    ->where('academic_year', '!=', '');
                break;

            case 'income_summary':
                $query = DB::table('student_fee_ledgers')
                    ->leftJoin('fee_types', 'fee_types.id', '=', 'student_fee_ledgers.fee_type_id')
                    ->select(DB::raw("COALESCE(fee_types.name, 'General/Other Dues') as head_name"))
                    ->selectRaw('SUM(student_fee_ledgers.debit) as demanded')
                    ->selectRaw('SUM(student_fee_ledgers.credit) as collected')
                    ->selectRaw('SUM(student_fee_ledgers.debit) - SUM(student_fee_ledgers.credit) as outstanding')
                    ->groupBy('head_name');
                break;

            case 'cancelled_receipts':
                $query = DB::table('fee_reversal_requests')
                    ->join('fee_collections', 'fee_collections.id', '=', 'fee_reversal_requests.fee_collection_id')
                    ->join('students', 'students.id', '=', 'fee_reversal_requests.student_id')
                    ->leftJoin('users', 'users.id', '=', 'fee_reversal_requests.processed_by')
                    ->select(
                        'fee_collections.receipt_no',
                        'students.name as student_name',
                        'fee_collections.final_amount as original_amount',
                        'fee_reversal_requests.reversal_date as reversed_date',
                        'fee_reversal_requests.reason',
                        'users.name as approved_by_name'
                    )
                    ->where('fee_reversal_requests.status', 'approved');

                $this->applyStandardFilters($query, 'fee_reversal_requests.reversal_date', $startDate, $endDate, $classId, $studentId);
                $query->orderBy('fee_reversal_requests.reversal_date', 'desc');
                break;

            case 'receipt_register':
                $query = DB::table('fee_collections')
                    ->join('students', 'students.id', '=', 'fee_collections.student_id')
                    ->select(
                        'fee_collections.receipt_no',
                        'students.name as student_name',
                        'fee_collections.payment_date',
                        'fee_collections.payment_mode',
                        'fee_collections.final_amount'
                    );

                $this->applyStandardFilters($query, 'fee_collections.payment_date', $startDate, $endDate, $classId, $studentId);
                if ($paymentMode) {
                    $query->where('fee_collections.payment_mode', $paymentMode);
                }
                $query->orderBy('fee_collections.payment_date', 'desc');
                break;

            default:
                // Unrecognized $type (stale bookmark, renamed report key, hand-
                // edited URL) -- 404 instead of leaving $query undefined and
                // crashing on ->paginate()/->cursor() below.
                abort(404, "Unknown report type '{$type}'.");
        }

        return $query;
    }

    /**
     * Apply date range, class, and student filters helper.
     */
    protected function applyStandardFilters($query, ?string $dateCol, ?string $start, ?string $end, ?string $classId, ?string $studentId)
    {
        if ($dateCol) {
            if ($start) {
                $query->where($dateCol, '>=', $start);
            }
            if ($end) {
                $query->where($dateCol, '<=', $end);
            }
        }
        if ($classId) {
            $query->where(function($q) use($classId) {
                $q->where('students.class_id', $classId)->orWhere('students.school_class_id', $classId);
            });
        }
        if ($studentId) {
            $query->where('students.id', $studentId);
        }
    }

    /**
     * Map report headers.
     */
    protected function getReportHeaders(string $type): array
    {
        switch ($type) {
            case 'collection_register':
                return ['Receipt No', 'Admission No', 'Student Name', 'Class Name', 'Payment Date', 'Payment Mode', 'Final Amount', 'Cashier'];
            case 'cash_book':
                return ['Date', 'Reference/Receipt', 'Particulars', 'Debit (Collection)', 'Credit (Refund)'];
            case 'day_book':
                return ['Date', 'Student Name', 'Description', 'Debit', 'Credit'];
            case 'ledger_book':
                return ['Date', 'Reference Type', 'Reference ID', 'Description', 'Debit', 'Credit', 'Running Balance'];
            case 'outstanding_register':
                return ['Admission No', 'Student Name', 'Class Name', 'Total Charged', 'Total Paid', 'Outstanding Balance'];
            case 'demand_register':
                return ['Admission No', 'Student Name', 'Class Name', 'Demand Date', 'Description', 'Amount'];
            case 'refund_register':
                return ['Date', 'Student Name', 'Refund Type', 'Amount Refunded', 'Payment Mode', 'Reason'];
            case 'discount_register':
                return ['Student Name', 'Class Name', 'Discount Rule Name', 'Discount Amount', 'Status'];
            case 'late_fee_register':
                return ['Date', 'Student Name', 'Class Name', 'Late Fee Collected'];
            case 'head_wise':
                return ['Fee Head Name', 'Total Collected'];
            case 'month_wise':
                return ['Month', 'Cash', 'UPI', 'Bank', 'Online', 'Total Collected'];
            case 'class_wise':
                return ['Class Name', 'Total Collected'];
            case 'session_comparison':
                return ['Academic Session', 'Total Demand', 'Total Collected', 'Total Outstanding', 'Recovery Rate (%)'];
            case 'income_summary':
                return ['Component / Head', 'Demanded Amount', 'Collected Amount', 'Outstanding Amount'];
            case 'cancelled_receipts':
                return ['Receipt No', 'Student Name', 'Original Amount', 'Reversed Date', 'Reason', 'Reversed By'];
            case 'receipt_register':
                return ['Receipt No', 'Student Name', 'Payment Date', 'Payment Mode', 'Paid Amount'];
            default:
                return [];
        }
    }

    /**
     * Map and format single row fields for output.
     */
    protected function formatRowData($row, string $type): array
    {
        if (is_array($row)) {
            // Month-wise formatted structure
            return [
                $row['month_name'],
                number_format($row['cash'], 2),
                number_format($row['upi'], 2),
                number_format($row['bank'], 2),
                number_format($row['online'], 2),
                number_format($row['total'], 2)
            ];
        }

        switch ($type) {
            case 'collection_register':
                return [$row->receipt_no, $row->admission_no, $row->student_name, $row->class_name, $row->payment_date, strtoupper($row->payment_mode), $row->final_amount, $row->cashier_name];
            case 'cash_book':
                return [$row->date, $row->reference, $row->particulars, $row->debit, $row->credit];
            case 'day_book':
                return [$row->date, $row->student_name, $row->description, $row->debit, $row->credit];
            case 'ledger_book':
                return [$row->date, $row->reference_type, $row->reference_id, $row->description, $row->debit, $row->credit, $row->running_balance];
            case 'outstanding_register':
                return [$row->admission_no, $row->student_name, $row->class_name, $row->total_charged, $row->total_paid, $row->outstanding_balance];
            case 'demand_register':
                return [$row->admission_no, $row->student_name, $row->class_name, $row->demand_date, $row->description, $row->amount];
            case 'refund_register':
                return [$row->date, $row->student_name, strtoupper($row->type), $row->amount, strtoupper($row->payment_mode), $row->reason];
            case 'discount_register':
                return [$row->student_name, $row->class_name, $row->discount_name, $row->amount, strtoupper($row->status)];
            case 'late_fee_register':
                return [$row->date, $row->student_name, $row->class_name, $row->late_fee_collected];
            case 'head_wise':
                return [$row->head_name, $row->total_collected];
            case 'class_wise':
                return [$row->class_name, $row->total_collected];
            case 'session_comparison':
                $recovery = $row->total_demand > 0 ? round(($row->total_collected / $row->total_demand) * 100, 2) : 100.00;
                return [$row->academic_year, $row->total_demand, $row->total_collected, $row->total_outstanding, $recovery . '%'];
            case 'income_summary':
                return [$row->head_name, $row->demanded, $row->collected, $row->outstanding];
            case 'cancelled_receipts':
                return [$row->receipt_no, $row->student_name, $row->original_amount, $row->reversed_date, $row->reason, $row->approved_by_name];
            case 'receipt_register':
                return [$row->receipt_no, $row->student_name, $row->payment_date, strtoupper($row->payment_mode), $row->final_amount];
            default:
                return [];
        }
    }

    /**
     * Month-wise PHP aggregation helper.
     */
    protected function formatMonthWiseData($results): array
    {
        $grouped = [];
        foreach ($results as $row) {
            $carbon = Carbon::parse($row->payment_date);
            $key = $carbon->format('Y-m');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'month_name' => $carbon->format('F Y'),
                    'cash' => 0, 'upi' => 0, 'bank' => 0, 'online' => 0,
                    'total' => 0
                ];
            }
            $mode = strtolower($row->payment_mode ?: 'cash');
            if (isset($grouped[$key][$mode])) {
                $grouped[$key][$mode] += (float)$row->final_amount;
            } else {
                $grouped[$key]['cash'] += (float)$row->final_amount;
            }
            $grouped[$key]['total'] += (float)$row->final_amount;
        }
        krsort($grouped);
        return array_values($grouped);
    }
}
