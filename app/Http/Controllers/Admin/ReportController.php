<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }
    
    public function generate(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Report generated']);
    }
    
    public function templates()
    {
        return view('admin.reports.templates');
    }
}
