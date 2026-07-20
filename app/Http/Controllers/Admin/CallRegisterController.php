<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\User;
use Illuminate\Http\Request;

class CallRegisterController extends Controller
{
    public function index(Request $request)
    {
        $query = CallLog::with('assignedUser');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('caller_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('outcome', 'like', "%{$search}%");
            });
        }

        if ($request->filled('call_type')) {
            $query->where('call_type', $request->call_type);
        }

        if ($request->filled('purpose')) {
            $query->where('purpose', $request->purpose);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $calls = $query->latest()->paginate(15)->withQueryString();
        
        $users = User::orderBy('name')->get();

        return view('admin.front-office.calls.index', compact('calls', 'users'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('admin.front-office.calls.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'caller_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'call_type' => 'required|in:incoming,outgoing',
            'purpose' => 'required|in:admission,fee_reminder,emergency,general',
            'duration' => 'required|integer|min:0',
            'assigned_user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:completed,missed,follow_up_required',
            'follow_up_date' => 'nullable|required_if:status,follow_up_required|date',
            'outcome' => 'nullable|string',
        ]);

        CallLog::create($validated);

        return redirect()->route('admin.front-office.calls.index')->with('success', 'Call record logged successfully.');
    }

    public function show($id)
    {
        $call = CallLog::with('assignedUser')->findOrFail($id);
        return view('admin.front-office.calls.show', compact('call'));
    }

    public function edit($id)
    {
        $call = CallLog::findOrFail($id);
        $users = User::orderBy('name')->get();
        return view('admin.front-office.calls.edit', compact('call', 'users'));
    }

    public function update(Request $request, $id)
    {
        $call = CallLog::findOrFail($id);

        $validated = $request->validate([
            'caller_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'call_type' => 'required|in:incoming,outgoing',
            'purpose' => 'required|in:admission,fee_reminder,emergency,general',
            'duration' => 'required|integer|min:0',
            'assigned_user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:completed,missed,follow_up_required',
            'follow_up_date' => 'nullable|required_if:status,follow_up_required|date',
            'outcome' => 'nullable|string',
        ]);

        $call->update($validated);

        return redirect()->route('admin.front-office.calls.index')->with('success', 'Call record updated successfully.');
    }

    public function destroy($id)
    {
        $call = CallLog::findOrFail($id);
        $call->delete();

        return redirect()->route('admin.front-office.calls.index')->with('success', 'Call record deleted successfully.');
    }
}
