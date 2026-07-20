<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashierClosing;
use App\Models\FeeCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashierClosingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-cashier-closing')->only(['index', 'show']);
        $this->middleware('permission:manage-cashier-closing')->only(['create', 'store']);
    }

    /**
     * Display a listing of cashier closings.
     */
    public function index(Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $cashierId = $request->get('cashier_id');

        $query = CashierClosing::with('cashier')->orderBy('closing_date', 'desc');

        if ($startDate) {
            $query->whereDate('closing_date', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('closing_date', '<=', $endDate);
        }
        if ($cashierId) {
            $query->where('cashier_id', $cashierId);
        }

        $closings = $query->paginate(20)->withQueryString();
        $cashiers = User::whereHas('roles', function($q) {
            $q->whereIn('name', ['admin', 'accountant']);
        })->orWhere('role', 'admin')->orWhere('role', 'accountant')->get();

        return view('admin.fees.cashier-closings.index', compact('closings', 'cashiers'));
    }

    /**
     * Show the form for creating a new cashier closing.
     */
    public function create(Request $request)
    {
        $cashier = Auth::user();
        $date = $request->get('date', now()->format('Y-m-d'));

        // Calculate expected collections based on cashier's collections for the selected date
        $expectedTotals = DB::table('fee_collections')
            ->where('collected_by', $cashier->id)
            ->whereDate('payment_date', $date)
            ->whereNull('deleted_at')
            ->selectRaw("
                SUM(CASE WHEN payment_mode = 'cash' THEN final_amount ELSE 0 END) as cash,
                SUM(CASE WHEN payment_mode = 'upi' THEN final_amount ELSE 0 END) as upi,
                SUM(CASE WHEN payment_mode = 'bank' THEN final_amount ELSE 0 END) as bank,
                SUM(CASE WHEN payment_mode = 'cheque' THEN final_amount ELSE 0 END) as cheque,
                SUM(CASE WHEN payment_mode = 'online' THEN final_amount ELSE 0 END) as online
            ")
            ->first();

        $expected = (object)[
            'cash' => (float)($expectedTotals->cash ?? 0),
            'upi' => (float)($expectedTotals->upi ?? 0),
            'bank' => (float)($expectedTotals->bank ?? 0),
            'cheque' => (float)($expectedTotals->cheque ?? 0),
            'online' => (float)($expectedTotals->online ?? 0)
        ];

        return view('admin.fees.cashier-closings.create', compact('expected', 'date', 'cashier'));
    }

    /**
     * Store a newly created cashier closing in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'closing_date' => 'required|date',
            'opening_balance' => 'required|numeric|min:0',
            'expected_cash' => 'required|numeric',
            'expected_upi' => 'required|numeric',
            'expected_bank' => 'required|numeric',
            'expected_cheque' => 'required|numeric',
            'expected_online' => 'required|numeric',
            'actual_cash' => 'required|numeric|min:0',
            'actual_upi' => 'required|numeric|min:0',
            'actual_bank' => 'required|numeric|min:0',
            'actual_cheque' => 'required|numeric|min:0',
            'actual_online' => 'required|numeric|min:0',
            'discrepancy_reason' => 'nullable|string',
            'remarks' => 'nullable|string'
        ]);

        $cashierId = Auth::id() ?? 1;

        // Check if report already submitted for this date by this cashier
        $exists = CashierClosing::where('cashier_id', $cashierId)
            ->whereDate('closing_date', $request->closing_date)
            ->exists();

        if ($exists) {
            return redirect()->route('admin.fees.cashier-closings.index')
                ->with('error', 'You have already submitted a closing report for ' . $request->closing_date);
        }

        CashierClosing::create(array_merge($request->all(), [
            'cashier_id' => $cashierId,
            'status' => 'submitted'
        ]));

        return redirect()->route('admin.fees.cashier-closings.index')
            ->with('success', 'Cashier closing report submitted successfully.');
    }

    /**
     * Display the specified cashier closing.
     */
    public function show($id)
    {
        $closing = CashierClosing::with('cashier')->findOrFail($id);
        return view('admin.fees.cashier-closings.show', compact('closing'));
    }
}
