<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateEntry;
use App\Models\User;
use Illuminate\Http\Request;

class AdminGateEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $entries = GateEntry::with('host')->orderBy('check_in', 'desc')->get();
        $hosts = User::all();

        return view('admin.gate-entries.index', compact('entries', 'hosts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'visitor_name' => 'required|string|max:255',
            'purpose' => 'required|string|max:500',
            'vehicle_no' => 'nullable|string|max:50',
            'host_user_id' => 'nullable|exists:users,id',
        ]);

        GateEntry::create(array_merge($request->all(), [
            'check_in' => now(),
        ]));

        return redirect()->route('admin.gate-entries.index')->with('success', 'Visitor checked in successfully.');
    }

    public function checkout($id)
    {
        $entry = GateEntry::findOrFail($id);
        $entry->update(['check_out' => now()]);

        return redirect()->route('admin.gate-entries.index')->with('success', 'Visitor checked out successfully.');
    }
}
