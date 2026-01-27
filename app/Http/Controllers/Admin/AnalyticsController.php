<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index()
    {
        return view('admin.analytics.dashboard');
    }
    
    public function attendanceHeatmap()
    {
        return response()->json(['data' => []]);
    }
    
    public function lateArrivals()
    {
        return response()->json(['data' => []]);
    }
    
    public function earlyDepartures()
    {
        return response()->json(['data' => []]);
    }
    
    public function disciplineScore()
    {
        return response()->json(['data' => []]);
    }
}
