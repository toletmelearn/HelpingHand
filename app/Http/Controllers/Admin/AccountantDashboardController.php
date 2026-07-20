<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountantDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-fees');
    }

    public function index()
    {
        $today = today()->toDateString();

        // 1. CARDS
        // Today's Collection
        $todayCollection = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('fee_collections')) {
            // fee_collections is soft-deletable (a reversed/cancelled
            // collection). The drill-down (Collection Register) uses the
            // FeeCollection Eloquent model, which excludes soft-deleted rows
            // by default -- exclude them here too so the card total always
            // matches what clicking through actually shows.
            $todayCollection = (float) DB::table('fee_collections')
                ->where('payment_date', $today)
                ->whereNull('deleted_at')
                ->sum('final_amount');
        }

        // Today's Refund
        $todayRefund = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('fee_refunds')) {
            $todayRefund = (float) DB::table('fee_refunds')
                ->where('type', 'refund')
                ->whereBetween('processed_at', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->sum('amount');
        }

        // Today's Discounts
        $todayDiscounts = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('student_discounts_applied')) {
            $todayDiscounts = (float) DB::table('student_discounts_applied')
                ->whereBetween('created_at', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->sum('amount');
        }

        // Today's Pending (Total unpaid balance in system)
        $todayPending = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('student_fee_ledgers')) {
            $totals = DB::table('student_fee_ledgers')
                ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->first();
            $todayPending = max(0.00, ($totals->total_debit ?? 0) - ($totals->total_credit ?? 0));
        }

        // Today's Due (New debits loaded today)
        $todayDue = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('student_fee_ledgers')) {
            $todayDue = (float) DB::table('student_fee_ledgers')
                ->where('date', $today)
                ->sum('debit');
        }

        // Today's Late Fee
        $todayLateFee = 0.00;
        if (\Illuminate\Support\Facades\Schema::hasTable('fee_collections')) {
            $todayLateFee = (float) DB::table('fee_collections')
                ->where('payment_date', $today)
                ->whereNull('deleted_at')
                ->sum('late_fine');
        }

        // Today's Admissions
        $todayAdmissions = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('students')) {
            // Raw DB::table() query bypasses the SoftDeletes scope, so a
            // student deleted the same day they were created (or any other
            // soft-deleted row) still got counted here even though the
            // "Today's Admissions" drill-down list (which uses the Student
            // Eloquent model) correctly excludes them -- card said N,
            // clicking through showed 0. Match the same active-only scope
            // the list uses.
            $todayAdmissions = DB::table('students')
                ->whereBetween('created_at', [$today . ' 00:00:00', $today . ' 23:59:59'])
                ->whereNull('deleted_at')
                ->count();
        }

        // 2. CHARTS DATA
        // Monthly Collection (Last 6 Months)
        $monthlyCollections = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth()->toDateString();
            $monthEnd = now()->subMonths($i)->endOfMonth()->toDateString();
            $monthLabel = now()->subMonths($i)->format('M Y');
            
            $collected = (float) DB::table('fee_collections')
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('final_amount');
            
            $refunded = (float) DB::table('fee_refunds')
                ->where('type', 'refund')
                ->whereBetween('processed_at', [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59'])
                ->sum('amount');

            $monthlyCollections[] = [
                'label' => $monthLabel,
                'net' => max(0, $collected - $refunded)
            ];
        }

        // Outstanding Trend (Last 6 Months cumulative outstanding)
        $outstandingTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $endOfMonth = now()->subMonths($i)->endOfMonth()->toDateString();
            $monthLabel = now()->subMonths($i)->format('M Y');
            
            $outstanding = (float) DB::table('student_fee_ledgers')
                ->where('date', '<=', $endOfMonth)
                ->selectRaw('SUM(debit) - SUM(credit) as balance')
                ->value('balance');

            $outstandingTrend[] = [
                'label' => $monthLabel,
                'outstanding' => max(0, $outstanding)
            ];
        }

        // Payment Mode Distribution
        $modes = DB::table('fee_collections')
            ->select('payment_mode')
            ->selectRaw('SUM(final_amount) as total')
            ->groupBy('payment_mode')
            ->get()
            ->pluck('total', 'payment_mode')
            ->toArray();
        $paymentModes = [
            'cash' => (float) ($modes['cash'] ?? 0),
            'upi' => (float) ($modes['upi'] ?? 0),
            'bank' => (float) ($modes['bank'] ?? 0),
            'online' => (float) ($modes['online'] ?? 0)
        ];

        // Fee Head Collection
        $headCollection = DB::table('fee_collection_items')
            ->join('fee_types', 'fee_types.id', '=', 'fee_collection_items.fee_type_id')
            ->select('fee_types.name as head_name')
            ->selectRaw('SUM(fee_collection_items.amount) as total')
            ->groupBy('fee_types.name')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        // Class-wise Collection
        $classJoinColumn = 'students.class_id';
        if (\Illuminate\Support\Facades\Schema::hasColumn('students', 'school_class_id')) {
            $classJoinColumn = DB::raw('COALESCE(students.class_id, students.school_class_id)');
        }
        $classCollection = DB::table('fee_collections')
            ->join('students', 'students.id', '=', 'fee_collections.student_id')
            ->leftJoin('school_classes', 'school_classes.id', '=', $classJoinColumn)
            ->select('school_classes.name as class_name')
            ->selectRaw('SUM(fee_collections.final_amount) as total')
            ->groupBy('school_classes.name')
            ->orderBy('total', 'desc')
            ->take(5)
            ->get();

        // 3. QUEUES
        // Recent Transactions
        $recentTransactions = DB::table('fee_collections')
            ->join('students', 'students.id', '=', 'fee_collections.student_id')
            ->select(
                'fee_collections.receipt_no',
                'students.name as student_name',
                'fee_collections.payment_date',
                'fee_collections.payment_mode',
                'fee_collections.final_amount'
            )
            ->orderBy('fee_collections.payment_date', 'desc')
            ->orderBy('fee_collections.id', 'desc')
            ->take(5)
            ->get();

        // Pending Approval Queue (Discount verification and Reversal approval requests)
        $pendingReversals = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('fee_reversal_requests')) {
            $pendingReversals = DB::table('fee_reversal_requests')
                ->join('students', 'students.id', '=', 'fee_reversal_requests.student_id')
                ->where('fee_reversal_requests.status', 'pending')
                ->select(
                    'fee_reversal_requests.id',
                    'students.name as student_name',
                    DB::raw("'Fee Reversal' as type"),
                    'fee_reversal_requests.reason',
                    'fee_reversal_requests.created_at'
                )
                ->orderBy('fee_reversal_requests.created_at', 'desc')
                ->take(5)
                ->get();
        }

        $pendingDiscounts = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('discount_approvals')) {
            $pendingDiscounts = DB::table('discount_approvals')
                ->join('students', 'students.id', '=', 'discount_approvals.student_id')
                ->leftJoin('discount_rules', 'discount_rules.id', '=', 'discount_approvals.discount_rule_id')
                ->where('discount_approvals.status', 'pending_verification')
                ->select(
                    'discount_approvals.id',
                    'students.name as student_name',
                    DB::raw("'Discount Approval' as type"),
                    DB::raw("COALESCE(discount_rules.name, 'General Discount') as reason"),
                    'discount_approvals.created_at'
                )
                ->orderBy('discount_approvals.created_at', 'desc')
                ->take(5)
                ->get();
        }

        $pendingQueue = collect($pendingReversals)
            ->concat($pendingDiscounts)
            ->sortByDesc('created_at')
            ->take(5);

        return view('admin.fees.dashboard', compact(
            'today', 'todayCollection', 'todayRefund', 'todayDiscounts', 'todayPending',
            'todayDue', 'todayLateFee', 'todayAdmissions',
            'monthlyCollections', 'outstandingTrend', 'paymentModes',
            'headCollection', 'classCollection', 'recentTransactions', 'pendingQueue'
        ));
    }
}
