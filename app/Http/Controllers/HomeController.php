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
        
        // Redirect user based on their role
        if ($user && $user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        } elseif ($user && $user->hasRole('teacher')) {
            return redirect()->route('teachers.dashboard');
        } elseif ($user && $user->hasRole('student')) {
            return redirect()->route('students.dashboard');
        } elseif ($user && $user->hasRole('parent')) {
            return redirect()->route('parent.dashboard');
        } else {
            // Default fallback
            return redirect()->route('students.dashboard');
        }
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