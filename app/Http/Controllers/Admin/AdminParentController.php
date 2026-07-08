<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminParentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = ParentModel::with('students');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%")
                  ->orWhereHas('students', function($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $parents = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.parents.index', compact('parents'));
    }

    public function show($id)
    {
        $parent = ParentModel::with('students.schoolClass', 'students.section')->findOrFail($id);
        return view('admin.parents.show', compact('parent'));
    }

    public function update(Request $request, $id)
    {
        $parent = ParentModel::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:parents,email,' . $parent->id,
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $parent->update($request->only(['name', 'email', 'phone', 'mobile', 'status']));

        Log::info('Parent profile updated by admin', ['parent_id' => $parent->id, 'admin_id' => auth()->id()]);

        return redirect()->route('admin.parents.show', $parent->id)->with('success', 'Parent profile updated successfully.');
    }

    public function resetPassword(Request $request, $id)
    {
        $parent = ParentModel::findOrFail($id);

        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $parent->update([
            'password' => Hash::make($request->password),
            'must_reset_password' => true,
        ]);

        Log::info('Parent password reset by admin', ['parent_id' => $parent->id, 'admin_id' => auth()->id()]);

        return redirect()->route('admin.parents.show', $parent->id)->with('success', 'Parent password reset successfully.');
    }
}
