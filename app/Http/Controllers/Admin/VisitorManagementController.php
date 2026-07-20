<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GateEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisitorManagementController extends Controller
{
    public function index(Request $request)
    {
        $query = GateEntry::with('host');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('visitor_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'inside') {
                $query->whereNull('check_out');
            } elseif ($request->status === 'checked_out') {
                $query->whereNotNull('check_out');
            }
        }

        if ($request->filled('blacklisted')) {
            $query->where('is_blacklisted', true);
        }

        $visitors = $query->latest('check_in')->paginate(15)->withQueryString();
        $hosts = User::orderBy('name')->get();
        
        $totalVisitorsToday = GateEntry::whereDate('check_in', Carbon::today())->count();
        $currentlyOnCampus = GateEntry::whereNull('check_out')->count();

        return view('admin.front-office.visitors.index', compact('visitors', 'hosts', 'totalVisitorsToday', 'currentlyOnCampus'));
    }

    public function create()
    {
        $hosts = User::orderBy('name')->get();
        return view('admin.front-office.visitors.create', compact('hosts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'visitor_name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
            'purpose' => 'required|string|max:255',
            'department' => 'nullable|string|max:100',
            'host_user_id' => 'nullable|exists:users,id',
            'id_proof_type' => 'nullable|string|max:50',
            'id_proof_number' => 'nullable|string|max:50',
            'photo' => 'nullable|string', // Base64 canvas photo or file upload
            'is_emergency' => 'boolean',
            'remarks' => 'nullable|string',
            'vehicle_no' => 'nullable|string|max:50',
        ]);

        // Guard: Check if phone number is blacklisted in previous logs
        $isBlacklisted = GateEntry::where('phone', $validated['phone'])->where('is_blacklisted', true)->exists();
        if ($isBlacklisted && !$request->has('force_check_in')) {
            return back()->withInput()->with('blacklist_warning', 'This visitor phone number is blacklisted. Do you wish to override?');
        }

        // Process base64 photo
        $photoPath = null;
        if (!empty($request->photo)) {
            $photoData = $request->photo;
            if (preg_match('/^data:image\/(\w+);base64,/', $photoData, $type)) {
                $photoData = substr($photoData, strpos($photoData, ',') + 1);
                $type = strtolower($type[1]); // png, jpg, jpeg

                if (in_array($type, ['jpg', 'jpeg', 'png'])) {
                    $photoData = base64_decode($photoData);

                    if ($photoData !== false) {
                        $filename = 'visitors/' . Str::random(40) . '.' . $type;
                        Storage::disk('public')->put($filename, $photoData);
                        $photoPath = $filename;
                    }
                }
            }
        }

        GateEntry::create([
            'visitor_name' => $validated['visitor_name'],
            'phone' => $validated['phone'],
            'purpose' => $validated['purpose'],
            'department' => $validated['department'],
            'host_user_id' => $validated['host_user_id'],
            'id_proof_type' => $validated['id_proof_type'],
            'id_proof_number' => $validated['id_proof_number'],
            'photo_path' => $photoPath,
            'is_blacklisted' => $isBlacklisted,
            'is_emergency' => $request->boolean('is_emergency'),
            'remarks' => $validated['remarks'],
            'vehicle_no' => $validated['vehicle_no'],
            'check_in' => Carbon::now(),
        ]);

        return redirect()->route('admin.front-office.visitors.index')->with('success', 'Visitor checked in successfully.');
    }

    public function show($id)
    {
        $visitor = GateEntry::with('host')->findOrFail($id);
        return view('admin.front-office.visitors.show', compact('visitor'));
    }

    public function edit($id)
    {
        $visitor = GateEntry::findOrFail($id);
        $hosts = User::orderBy('name')->get();
        return view('admin.front-office.visitors.edit', compact('visitor', 'hosts'));
    }

    public function update(Request $request, $id)
    {
        $visitor = GateEntry::findOrFail($id);

        $validated = $request->validate([
            'visitor_name' => 'required|string|max:150',
            'phone' => 'required|string|max:20',
            'purpose' => 'required|string|max:255',
            'department' => 'nullable|string|max:100',
            'host_user_id' => 'nullable|exists:users,id',
            'id_proof_type' => 'nullable|string|max:50',
            'id_proof_number' => 'nullable|string|max:50',
            'is_emergency' => 'boolean',
            'remarks' => 'nullable|string',
            'vehicle_no' => 'nullable|string|max:50',
        ]);

        $visitor->update([
            'visitor_name' => $validated['visitor_name'],
            'phone' => $validated['phone'],
            'purpose' => $validated['purpose'],
            'department' => $validated['department'],
            'host_user_id' => $validated['host_user_id'],
            'id_proof_type' => $validated['id_proof_type'],
            'id_proof_number' => $validated['id_proof_number'],
            'is_emergency' => $request->boolean('is_emergency'),
            'remarks' => $validated['remarks'],
            'vehicle_no' => $validated['vehicle_no'],
        ]);

        if ($request->has('check_out_now')) {
            $visitor->update(['check_out' => Carbon::now()]);
        }

        return redirect()->route('admin.front-office.visitors.index')->with('success', 'Visitor details updated successfully.');
    }

    public function destroy($id)
    {
        $visitor = GateEntry::findOrFail($id);
        $visitor->delete();

        return redirect()->route('admin.front-office.visitors.index')->with('success', 'Visitor record deleted successfully.');
    }

    public function toggleBlacklist($id)
    {
        $visitor = GateEntry::findOrFail($id);
        $newStatus = !$visitor->is_blacklisted;

        // Apply blacklist to all records with same phone number
        GateEntry::where('phone', $visitor->phone)->update(['is_blacklisted' => $newStatus]);

        $statusMsg = $newStatus ? 'blacklisted' : 'removed from blacklist';
        return back()->with('success', "Visitor phone number {$visitor->phone} has been {$statusMsg}.");
    }

    public function printBadge($id)
    {
        $visitor = GateEntry::with('host')->findOrFail($id);
        return view('admin.front-office.visitors.badge', compact('visitor'));
    }
}
