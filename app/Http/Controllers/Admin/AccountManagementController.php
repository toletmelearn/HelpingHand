<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\TeacherLogin;
use App\Models\ParentModel;

class AccountManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin']);
    }

    /**
     * Display a listing of logins by role.
     */
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'users');
        $search = $request->get('search');
        $status = $request->get('status');

        $users = null;
        $teachers = null;
        $parents = null;

        if ($tab === 'users') {
            $query = User::query();
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }
            if ($status) {
                $query->where('status', $status);
            }
            $users = $query->orderBy('name')->paginate(10)->withQueryString();
        } elseif ($tab === 'teachers') {
            $query = TeacherLogin::with('teacher');
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'LIKE', "%{$search}%")
                      ->orWhereHas('teacher', function ($t) use ($search) {
                          $t->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }
            if ($status) {
                $query->where('status', $status);
            }
            $teachers = $query->orderBy('username')->paginate(10)->withQueryString();
        } elseif ($tab === 'parents') {
            $query = ParentModel::query();
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('mobile', 'LIKE', "%{$search}%")
                      ->orWhere('admission_number', 'LIKE', "%{$search}%");
                });
            }
            if ($status) {
                $query->where('status', $status);
            }
            $parents = $query->orderBy('name')->paginate(10)->withQueryString();
        }

        // Calculate missing login counts to show warning/actions
        $missingTeachersCount = \App\Models\Teacher::whereNotIn('id', function($q) {
            $q->select('teacher_id')->from('teacher_logins');
        })->count();

        $missingParentsCount = \App\Models\Student::whereNotIn('id', function($q) {
            $q->select('student_id')->from('parents');
        })->count();

        return view('admin.accounts.index', compact(
            'tab', 'search', 'status', 'users', 'teachers', 'parents', 
            'missingTeachersCount', 'missingParentsCount'
        ));
    }

    /**
     * Change password for a specific user.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'role_type' => 'required|in:user,teacher,parent',
            'account_id' => 'required|integer',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $roleType = $request->role_type;
        $accountId = $request->account_id;
        $newPassword = $request->password;

        if ($roleType === 'user') {
            $account = User::findOrFail($accountId);
            $account->update([
                'password' => Hash::make($newPassword),
            ]);
        } elseif ($roleType === 'teacher') {
            $account = TeacherLogin::findOrFail($accountId);
            $account->update([
                'password' => Hash::make($newPassword),
                'force_password_change' => false, // Reset force status since admin set it
            ]);
        } elseif ($roleType === 'parent') {
            $account = ParentModel::findOrFail($accountId);
            $account->update([
                'password' => bcrypt($newPassword),
                'must_reset_password' => false, // Reset force status since admin set it — matches the teacher branch above
            ]);
        }

        return back()->with('success', 'Password updated successfully for ' . ($account->name ?? $account->username) . '!');
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus(Request $request)
    {
        $request->validate([
            'role_type' => 'required|in:user,teacher,parent',
            'account_id' => 'required|integer',
        ]);

        $roleType = $request->role_type;
        $accountId = $request->account_id;

        if ($roleType === 'user') {
            $account = User::findOrFail($accountId);
            
            // Prevent self-deactivation
            if ($account->id === auth()->id()) {
                return back()->with('error', 'You cannot deactivate your own admin account!');
            }
            
            $newStatus = $account->status === 'active' ? 'inactive' : 'active';
            $account->update(['status' => $newStatus]);
        } elseif ($roleType === 'teacher') {
            $account = TeacherLogin::findOrFail($accountId);
            $newStatus = $account->status === 'active' ? 'inactive' : 'active';
            $account->update(['status' => $newStatus]);
        } elseif ($roleType === 'parent') {
            $account = ParentModel::findOrFail($accountId);
            $newStatus = $account->status === 'active' ? 'inactive' : 'active';
            $account->update(['status' => $newStatus]);
        }

        $statusText = $newStatus === 'active' ? 'activated' : 'deactivated';
        return back()->with('success', 'Account status for ' . ($account->name ?? $account->username) . ' changed to ' . $newStatus . ' successfully!');
    }

    /**
     * Auto-generate missing portal logins.
     */
    public function syncAccounts(Request $request)
    {
        $createdTeachers = 0;
        $createdParents = 0;
        $generatedCredentials = [];

        // 1. Generate Teacher Logins
        $teachersWithoutLogin = \App\Models\Teacher::whereNotIn('id', function($q) {
            $q->select('teacher_id')->from('teacher_logins');
        })->get();

        foreach ($teachersWithoutLogin as $teacher) {
            if (!empty($teacher->phone)) {
                // Random one-time password, not a shared guessable default — combined with
                // force_password_change (enforced on every request by TeacherAuth), this
                // closes the window where a known password could be used to hijack the
                // account before the real teacher's first login.
                $tempPassword = \Illuminate\Support\Str::random(10);
                TeacherLogin::create([
                    'teacher_id' => $teacher->id,
                    'school_id' => $teacher->school_id ?? null,
                    'username' => $teacher->phone,
                    'password' => Hash::make($tempPassword),
                    'status' => 'active',
                    'force_password_change' => true,
                ]);
                $generatedCredentials[] = [
                    'type' => 'Teacher', 'name' => $teacher->name,
                    'username' => $teacher->phone, 'password' => $tempPassword,
                ];
                $createdTeachers++;
            }
        }

        // 2. Generate Parent Logins
        $studentsWithoutParent = \App\Models\Student::whereNotIn('id', function($q) {
            $q->select('student_id')->from('parents');
        })->get();

        foreach ($studentsWithoutParent as $student) {
            $tempPassword = \Illuminate\Support\Str::random(10);
            $login = $student->mobile ?: $student->phone;
            $parent = ParentModel::create([
                'student_id' => $student->id,
                'name' => 'Parent of ' . $student->name,
                'email' => 'parent.' . $student->id . '@example.com',
                'phone' => $login,
                'mobile' => $login,
                'admission_number' => $student->admission_no,
                'password' => bcrypt($tempPassword),
                'must_reset_password' => true,
                'status' => 'active',
            ]);
            $generatedCredentials[] = [
                'type' => 'Parent', 'name' => $parent->name,
                'username' => $login, 'password' => $tempPassword,
            ];
            $createdParents++;
        }

        return back()
            ->with('success', "Auto-generation completed! Generated {$createdTeachers} Teacher portal logins and {$createdParents} Parent portal logins, each with a unique one-time password that must be changed on first login.")
            ->with('generated_credentials', $generatedCredentials);
    }
}
