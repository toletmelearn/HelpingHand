<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\Student;
use App\Models\ParentModel;
use App\Models\FeeCollection;

class ParentAuthController extends Controller
{
    public function showLogin()
    {
        return view('parent.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required',
            'password' => 'required'
        ]);

        $login = $request->login;
        $password = $request->password;

        // login via mobile OR admission number
        // First try to find by parent's own mobile or admission number
        $parent = \App\Models\ParentModel::where('phone', $login)->first();
        
        // If not found, try to find by student's mobile, phone or admission number
        if (!$parent) {
            $student = \App\Models\Student::where('mobile', $login)
                ->orWhere('phone', $login)
                ->orWhere('admission_no', $login)
                ->first();
            if ($student) {
                $parent = \App\Models\ParentModel::where('student_id', $student->id)->first();
            }
        }

        if (!$parent) {
            Log::warning('Parent login failed - invalid credentials', ['login' => $login]);
            return back()->with('error', 'Invalid mobile/admission');
        }

        if ($parent->status !== 'active') {
            Log::warning('Parent login failed - account inactive', ['parent_id' => $parent->id, 'login' => $login]);
            return back()->with('error', 'Your account is inactive. Please contact the administrator.');
        }

        if (!Hash::check($password, $parent->password)) {
            Log::warning('Parent login failed - wrong password', ['parent_id' => $parent->id, 'login' => $login]);
            return back()->with('error', 'Wrong password');
        }

        Auth::guard('parent')->login($parent);
        
        Log::info('Parent login successful', ['parent_id' => $parent->id, 'login' => $login]);

        return redirect('/parent/dashboard');
    }

    public function logout()
    {
        Auth::guard('parent')->logout();
        return redirect()->route('parent.login');
    }

    public function showResetPasswordForm()
    {
        $parent = Auth::guard('parent')->user();

        if (!$parent->must_reset_password) {
            return redirect()->route('parent.dashboard');
        }

        return view('parent.auth.reset-password');
    }

    public function updatePassword(Request $request)
    {
        $parent = Auth::guard('parent')->user();

        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $parent->update([
            'password' => Hash::make($request->password),
            'must_reset_password' => false,
        ]);

        Log::info('Parent completed forced password reset', ['parent_id' => $parent->id]);

        return redirect()->route('parent.dashboard')->with('success', 'Password updated successfully.');
    }
    
    public function dashboard()
    {
        $parent = Auth::guard('parent')->user();
        
        if (!$parent) {
            return redirect()->route('parent.login');
        }
        
        $students = $parent->students;
        if ($students->isEmpty()) {
            $student = $parent->student;
            $students = collect($student ? [$student] : []);
        }

        $activeStudentId = session('active_student_id');
        $student = $students->firstWhere('id', $activeStudentId) ?? $students->first();

        if ($student && $student->id !== $activeStudentId) {
            session(['active_student_id' => $student->id]);
        }
        
        return view('parent.dashboard', compact('student', 'students'));
    }

    public function switchStudent($id)
    {
        $parent = Auth::guard('parent')->user();
        if (!$parent) {
            return redirect()->route('parent.login');
        }

        $hasChild = $parent->students()->where('id', $id)->exists()
            || ($parent->student_id == $id);

        if (!$hasChild) {
            abort(403, 'Unauthorized student selection.');
        }

        session(['active_student_id' => (int) $id]);

        return redirect()->back()->with('success', 'Switched context successfully.');
    }
}