<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialYearClosingService;
use App\Models\FinancialYearClosing;

class FinancialYearClosingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view-year-closing')->only(['index']);
        $this->middleware('permission:manage-year-closing')->only(['stage', 'confirm', 'rollback']);
    }

    /**
     * Index showing closing list and staged alert.
     */
    public function index()
    {
        $stagedClosing = FinancialYearClosing::where('status', 'staged')->first();

        $history = FinancialYearClosing::orderBy('id', 'desc')->get();

        $activeSessions = DB::table('academic_sessions')
            ->where('is_active', true)
            ->get();

        $allSessions = DB::table('academic_sessions')
            ->get();

        return view('admin.fees.year-closing.index', compact(
            'stagedClosing', 'history', 'activeSessions', 'allSessions'
        ));
    }

    /**
     * Staging Closing.
     */
    public function stage(Request $request)
    {
        $request->validate([
            'from_session_code' => 'required|string|exists:academic_sessions,code',
            'to_session_code' => 'required|string|exists:academic_sessions,code|different:from_session_code',
            'remarks' => 'nullable|string|max:500'
        ]);

        try {
            FinancialYearClosingService::stageClosing(
                $request->from_session_code,
                $request->to_session_code,
                $request->remarks,
                auth()->id()
            );

            return redirect()->route('admin.fees.year-closing.index')
                ->with('success', 'Financial year closing staged successfully. Please review the balances carried forward before final confirmation.');
        } catch (\Exception $e) {
            return redirect()->route('admin.fees.year-closing.index')
                ->with('error', 'Failed to stage closing: ' . $e->getMessage());
        }
    }

    /**
     * Rollback Staging.
     */
    public function rollback(Request $request)
    {
        $request->validate([
            'closing_id' => 'required|integer|exists:financial_year_closings,id'
        ]);

        try {
            FinancialYearClosingService::rollbackClosing($request->closing_id);

            return redirect()->route('admin.fees.year-closing.index')
                ->with('success', 'Staged year closing rolled back successfully. Opening balances removed.');
        } catch (\Exception $e) {
            return redirect()->route('admin.fees.year-closing.index')
                ->with('error', 'Failed to rollback: ' . $e->getMessage());
        }
    }

    /**
     * Confirm Closure.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'closing_id' => 'required|integer|exists:financial_year_closings,id'
        ]);

        try {
            FinancialYearClosingService::confirmClosing($request->closing_id);

            return redirect()->route('admin.fees.year-closing.index')
                ->with('success', 'Financial year closing confirmed and finalized. Academic session is now frozen.');
        } catch (\Exception $e) {
            return redirect()->route('admin.fees.year-closing.index')
                ->with('error', 'Failed to finalize: ' . $e->getMessage());
        }
    }
}
