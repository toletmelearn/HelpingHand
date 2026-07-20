<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\TeacherLogin;

class TeacherAuthController extends Controller
{
    /**
     * Show the teacher login form.
     */
    public function showLogin()
    {
        return view('teacher.auth.login');
    }

    /**
     * Handle teacher login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'identifier' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find teacher login by username (mobile OR employee_id)
        $teacherLogin = TeacherLogin::where('username', $request->identifier)
            ->active()
            ->first();

        if (!$teacherLogin) {
            Log::warning('Teacher login failed - invalid credentials', ['identifier' => $request->identifier]);
            return back()->with('error', 'Invalid credentials. Please check your username.');
        }

        // Check password
        if (!Hash::check($request->password, $teacherLogin->password)) {
            Log::warning('Teacher login failed - wrong password', ['teacher_id' => $teacherLogin->id, 'identifier' => $request->identifier]);
            return back()->with('error', 'Invalid password.');
        }

        // Authenticate using teacher guard
        Auth::guard('teacher')->login($teacherLogin, $request->filled('remember'));

        // Update last login
        $teacherLogin->updateLastLogin();
        
        Log::info('Teacher login successful', ['teacher_id' => $teacherLogin->id, 'identifier' => $request->identifier]);

        // Check if password change is required
        if ($teacherLogin->force_password_change) {
            return redirect()->route('teacher.password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        // Redirect to teacher dashboard
        return redirect()->route('teacher.dashboard');
    }

    /**
     * Handle teacher logout request.
     */
    public function logout(Request $request)
    {
        Auth::guard('teacher')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('teacher.login')->with('success', 'You have been logged out successfully.');
    }
}
