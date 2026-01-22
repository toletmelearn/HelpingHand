<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function __construct()
    {
        // Middleware is handled in the route group in routes/web.php
    }
    
    public function index()
    {
        $user = Auth::user();
        $dashboardData = [];
        
        // Load dashboard based on user role
        if ($user && ($user->hasRole('admin') || $user->hasRole('teacher'))) {
            $dashboardData = [
                'students' => \App\Models\Student::count(),
                'teachers' => \App\Models\Teacher::count(),
                'attendance' => \App\Models\Attendance::count(),
                'bell_timing' => \App\Models\BellTiming::count(),
                'exam_papers' => \App\Models\ExamPaper::count()
            ];
        }
        
        return view('home.index', compact('dashboardData'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}