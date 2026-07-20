<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;

class TeacherProfileController extends Controller
{
    /**
     * Show teacher profile.
     */
    public function show()
    {
        $login = Auth::guard('teacher')->user();
        
        // Get actual teacher data using the direct relationship
        $teacher = $login->teacher;
        
        return view('teacher.profile.show', compact('teacher', 'login'));
    }

    /**
     * Update teacher profile.
     */
    public function update(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();
        $teacher = $teacherLogin->teacher;
        
        if (!$teacher) {
            return back()->with('error', 'Teacher record not found.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:teachers,email,' . $teacher->id,
            'phone' => 'required|string|max:15',
            'mobile' => 'nullable|string|max:15|unique:teachers,mobile,' . $teacher->id,
            'address' => 'nullable|string|max:500',
        ]);

        $teacher->update($request->only(['name', 'email', 'phone', 'mobile', 'address']));

        return back()->with('success', 'Profile updated successfully!');
    }

    /**
     * Show change password form.
     */
    public function changePasswordForm()
    {
        return view('teacher.profile.change-password');
    }

    /**
     * Change teacher password.
     */
    public function changePassword(Request $request)
    {
        $teacherLogin = Auth::guard('teacher')->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Verify current password
        if (!Hash::check($request->current_password, $teacherLogin->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        // Check if new password is same as default
        if ($request->new_password === '123456') {
            return back()->withErrors(['new_password' => 'Please choose a password different from the default.']);
        }

        // Update password and remove force change flag
        $teacherLogin->update([
            'password' => Hash::make($request->new_password),
            'force_password_change' => false,
        ]);
        
        // Re-authenticate the teacher to maintain session
        Auth::guard('teacher')->login($teacherLogin, true);

        return redirect()->route('teacher.dashboard')
            ->with('success', 'Password changed successfully!');
    }
}
