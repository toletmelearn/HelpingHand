<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentStatus\TerminalStatusReconciliationDetector;

class TerminalStatusReconciliationReportController extends Controller
{
    public function index(TerminalStatusReconciliationDetector $detector)
    {
        $results = $detector->detect();

        return view('admin.reports.terminal-status-reconciliation', compact('results'));
    }
}
