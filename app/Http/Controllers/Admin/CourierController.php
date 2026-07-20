<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourierController extends Controller
{
    public function index(Request $request)
    {
        $query = Courier::with('recipient');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('courier_company', 'like', "%{$search}%")
                  ->orWhere('sender', 'like', "%{$search}%")
                  ->orWhere('receiver', 'like', "%{$search}%");
            });
        }

        if ($request->filled('courier_type')) {
            $query->where('courier_type', $request->courier_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $couriers = $query->latest()->paginate(15)->withQueryString();

        return view('admin.front-office.couriers.index', compact('couriers'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('admin.front-office.couriers.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'courier_company' => 'required|string|max:100',
            'courier_type' => 'required|in:incoming,outgoing',
            'parcel_type' => 'required|in:document,package,registered_post,speed_post,regular',
            'sender' => 'required|string|max:255',
            'receiver' => 'required|string|max:255',
            'recipient_user_id' => 'nullable|exists:users,id',
            'delivery_date' => 'nullable|date',
            'status' => 'required|in:pending,delivered,returned,lost',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('couriers', $filename, 'public');
        }

        Courier::create(array_merge($validated, [
            'attachment_path' => $attachmentPath,
        ]));

        return redirect()->route('admin.front-office.couriers.index')->with('success', 'Courier entry logged successfully.');
    }

    public function show($id)
    {
        $courier = Courier::with('recipient')->findOrFail($id);
        return view('admin.front-office.couriers.show', compact('courier'));
    }

    public function edit($id)
    {
        $courier = Courier::findOrFail($id);
        $users = User::orderBy('name')->get();
        return view('admin.front-office.couriers.edit', compact('courier', 'users'));
    }

    public function update(Request $request, $id)
    {
        $courier = Courier::findOrFail($id);

        $validated = $request->validate([
            'tracking_number' => 'required|string|max:100',
            'courier_company' => 'required|string|max:100',
            'courier_type' => 'required|in:incoming,outgoing',
            'parcel_type' => 'required|in:document,package,registered_post,speed_post,regular',
            'sender' => 'required|string|max:255',
            'receiver' => 'required|string|max:255',
            'recipient_user_id' => 'nullable|exists:users,id',
            'delivery_date' => 'nullable|date',
            'status' => 'required|in:pending,delivered,returned,lost',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        $attachmentPath = $courier->attachment_path;
        if ($request->hasFile('attachment')) {
            if ($attachmentPath) {
                Storage::disk('public')->delete($attachmentPath);
            }
            $file = $request->file('attachment');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('couriers', $filename, 'public');
        }

        $courier->update(array_merge($validated, [
            'attachment_path' => $attachmentPath,
        ]));

        return redirect()->route('admin.front-office.couriers.index')->with('success', 'Courier entry updated successfully.');
    }

    public function destroy($id)
    {
        $courier = Courier::findOrFail($id);
        if ($courier->attachment_path) {
            Storage::disk('public')->delete($courier->attachment_path);
        }
        $courier->delete();

        return redirect()->route('admin.front-office.couriers.index')->with('success', 'Courier entry deleted successfully.');
    }
}
