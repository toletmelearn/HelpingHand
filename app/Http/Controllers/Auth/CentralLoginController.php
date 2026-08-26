<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\TeacherLogin;
use App\Models\ParentModel;
use App\Models\Student;

class CentralLoginController extends Controller
{
    /**
     * Show the central multi-role login form.
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }
    
    /**
     * Handle central multi-role login request.
     * 
     * Tries to authenticate in this order:
     * 1. Admin/Web User (email-based)
     * 2. Teacher (username/mobile/employee_id-based)
     * 3. Parent (mobile/admission_number-based)
     * 
     * This order is chosen because:
     * - Admin users use email which is unique and easily identifiable
     * - Teacher usernames are unique in teacher_logins table
     * - Parent mobile/admission numbers are unique in parents table
     * - No credential overlap between tables
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        
        $login = $request->login;
        $password = $request->password;
        
        // ============================================================
        // ATTEMPT 1: Admin / Web User Login
        // Check if login looks like an email address
        // ============================================================
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
            
            if ($user) {
                if ($user->status !== 'active') {
                    return back()->withErrors([
                        'login' => 'Your account is inactive. Please contact the administrator.',
                    ])->withInput($request->only('login'));
                }
                
                // Try to authenticate with web guard
                if (Auth::guard('web')->attempt(['email' => $login, 'password' => $password])) {
                    $request->session()->regenerate();
                    
                    \Illuminate\Support\Facades\Log::info('User login successful', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'guard' => 'web',
                        'ip' => $request->ip()
                    ]);
                    
                    // Redirect based on user role
                    if ($user->hasRole('receptionist') || $user->hasRole('reception')) {
                        return redirect()->intended(route('admin.front-office.dashboard'));
                    }
                    
                    if ($user->hasRole('admin')) {
                        return redirect()->intended(route('admin.dashboard'));
                    }

                    // For other web users, redirect to home
                    return redirect()->intended('/home');
                }
                
                \Illuminate\Support\Facades\Log::warning('Login failed - incorrect password', [
                    'email' => $login,
                    'guard' => 'web',
                    'ip' => $request->ip()
                ]);
                
                // Email found but password wrong
                return back()->withErrors([
                    'login' => 'The provided password is incorrect.',
                ])->withInput($request->only('login'));
            }
        }
        
        // ============================================================
        // ATTEMPT 2: Teacher Login
        // Check teacher_logins table by username (mobile/employee_id)
        // ============================================================
        $teacherLogin = TeacherLogin::where('username', $login)
            ->active()
            ->first();
        
        if ($teacherLogin) {
            // Check password using Hash
            if (Hash::check($password, $teacherLogin->password)) {
                // Authenticate using teacher guard
                Auth::guard('teacher')->login($teacherLogin, $request->filled('remember'));
                
                // Update last login
                $teacherLogin->updateLastLogin();
                
                // Regenerate session for security
                $request->session()->regenerate();
                
                \Illuminate\Support\Facades\Log::info('Teacher login successful', [
                    'teacher_login_id' => $teacherLogin->id,
                    'teacher_id' => $teacherLogin->teacher_id,
                    'username' => $teacherLogin->username,
                    'guard' => 'teacher',
                    'ip' => $request->ip()
                ]);
                
                // Check if password change is required
                if ($teacherLogin->force_password_change) {
                    return redirect()->route('teacher.password.change')
                        ->with('warning', 'You must change your password before continuing.');
                }
                
                // Redirect to teacher dashboard
                return redirect()->intended(route('teacher.dashboard'));
            }
            
            // Teacher found but password wrong
            return back()->withErrors([
                'login' => 'The provided password is incorrect.',
            ])->withInput($request->only('login'));
        }
        
        // ============================================================
        // ATTEMPT 3: Parent Login
        // Check parents table by mobile or admission_number
        // Also check by student's admission_no
        // ============================================================
        $parent = ParentModel::where('phone', $login)->first();
        
        // If not found, try to find by student's mobile, phone or admission number
        if (!$parent) {
            $student = Student::where('mobile', $login)
                ->orWhere('phone', $login)
                ->orWhere('admission_no', $login)
                ->first();
            if ($student) {
                $parent = ParentModel::where('student_id', $student->id)->first();
            }
        }
        
        if ($parent) {
            if ($parent->status !== 'active') {
                return back()->withErrors([
                    'login' => 'Your account is inactive. Please contact the administrator.',
                ])->withInput($request->only('login'));
            }

            // Check password using Hash
            if (Hash::check($password, $parent->password)) {
                // Authenticate using parent guard
                Auth::guard('parent')->login($parent, $request->filled('remember'));
                
                // Regenerate session for security
                $request->session()->regenerate();
                
                // Redirect to parent dashboard
                return redirect()->intended('/parent/dashboard');
            }
            
            // Parent found but password wrong
            return back()->withErrors([
                'login' => 'The provided password is incorrect.',
            ])->withInput($request->only('login'));
        }
        
        // ============================================================
        // ALL ATTEMPTS FAILED
        // Don't reveal whether the account exists or not
        // ============================================================
        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('login'));
    }
    
    /**
     * Handle logout request.
     */
    public function logout(Request $request)
    {
        // Logout from all guards to be safe
        Auth::guard('web')->logout();
        Auth::guard('teacher')->logout();
        Auth::guard('parent')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/');
    }
}
