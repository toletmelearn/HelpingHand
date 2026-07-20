<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class VisitorController extends Controller
{
    public function index()
    {
        $visitors = GateEntry::with('host')->orderBy('check_in', 'desc')->get();
        $hosts = User::all();

        // Calculate metrics
        $totalVisitorsToday = GateEntry::whereDate('check_in', Carbon::today())->count();
        $currentlyOnCampus = GateEntry::whereNull('check_out')->count();

        return view('admin.visitor.log', compact('visitors', 'hosts', 'totalVisitorsToday', 'currentlyOnCampus'));
    }

    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'visitor_name' => 'required|string|max:150',
            'purpose' => 'required|string|max:255',
            'vehicle_no' => 'nullable|string|max:50',
            'host_user_id' => 'nullable|exists:users,id',
        ]);

        GateEntry::create([
            'visitor_name' => $validated['visitor_name'],
            'purpose' => $validated['purpose'],
            'vehicle_no' => $validated['vehicle_no'],
            'host_user_id' => $validated['host_user_id'],
            'check_in' => Carbon::now(),
        ]);

        return back()->with('success', 'Visitor checked in successfully.');
    }

    public function checkOut($id)
    {
        $visitor = GateEntry::findOrFail($id);
        if ($visitor->check_out) {
            return back()->with('error', 'Visitor has already checked out.');
        }

        $visitor->update([
            'check_out' => Carbon::now(),
        ]);

        return back()->with('success', 'Visitor checked out successfully.');
    }
}
