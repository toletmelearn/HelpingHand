<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PerformanceScore;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index()
    {
        return view('admin.performance.index');
    }
    
    public function scores()
    {
        $scores = PerformanceScore::with('teacher')->latest()->paginate(20);
        return view('admin.performance.scores', compact('scores'));
    }
    
    public function calculate(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Performance scores calculated']);
    }
}
