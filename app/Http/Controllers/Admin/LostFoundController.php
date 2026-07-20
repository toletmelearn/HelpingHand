<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LostFoundItem;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LostFoundController extends Controller
{
    public function index(Request $request)
    {
        $query = LostFoundItem::with('reportedByUser');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location_found', 'like', "%{$search}%")
                  ->orWhere('claimant_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $items = $query->latest('date_reported')->paginate(15)->withQueryString();

        return view('admin.front-office.lost-found.index', compact('items'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get();
        return view('admin.front-office.lost-found.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location_found' => 'required|string|max:255',
            'location_lost' => 'nullable|string|max:255',
            'item_type' => 'required|in:lost,found',
            'date_reported' => 'required|date',
            'reported_by_user_id' => 'nullable|exists:users,id',
            'reported_by_name' => 'nullable|string|max:255',
            'status' => 'required|in:lost,found,claimed,returned',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $photoPath = $file->storeAs('lost-found', $filename, 'public');
        }

        LostFoundItem::create(array_merge($validated, [
            'photo_path' => $photoPath,
        ]));

        return redirect()->route('admin.front-office.lost-found.index')->with('success', 'Lost/Found item reported successfully.');
    }

    public function show($id)
    {
        $item = LostFoundItem::with('reportedByUser')->findOrFail($id);
        return view('admin.front-office.lost-found.show', compact('item'));
    }

    public function edit($id)
    {
        $item = LostFoundItem::findOrFail($id);
        $users = User::orderBy('name')->get();
        return view('admin.front-office.lost-found.edit', compact('item', 'users'));
    }

    public function update(Request $request, $id)
    {
        $item = LostFoundItem::findOrFail($id);

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location_found' => 'required|string|max:255',
            'location_lost' => 'nullable|string|max:255',
            'item_type' => 'required|in:lost,found',
            'date_reported' => 'required|date',
            'reported_by_user_id' => 'nullable|exists:users,id',
            'reported_by_name' => 'nullable|string|max:255',
            'status' => 'required|in:lost,found,claimed,returned',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $photoPath = $item->photo_path;
        if ($request->hasFile('photo')) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }
            $file = $request->file('photo');
            $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $photoPath = $file->storeAs('lost-found', $filename, 'public');
        }

        $item->update(array_merge($validated, [
            'photo_path' => $photoPath,
        ]));

        return redirect()->route('admin.front-office.lost-found.index')->with('success', 'Lost/Found item details updated.');
    }

    public function destroy($id)
    {
        $item = LostFoundItem::findOrFail($id);
        if ($item->photo_path) {
            Storage::disk('public')->delete($item->photo_path);
        }
        $item->delete();

        return redirect()->route('admin.front-office.lost-found.index')->with('success', 'Lost/Found item deleted.');
    }

    public function claim(Request $request, $id)
    {
        $item = LostFoundItem::findOrFail($id);

        $validated = $request->validate([
            'claimant_name' => 'required|string|max:255',
            'claimant_phone' => 'required|string|max:20',
            'verification_details' => 'required|string|max:1000',
        ]);

        $item->update([
            'claimant_name' => $validated['claimant_name'],
            'claimant_phone' => $validated['claimant_phone'],
            'verification_details' => $validated['verification_details'],
            'status' => 'returned',
            'returned_at' => Carbon::now(),
        ]);

        return back()->with('success', 'Item marked as returned and claimed successfully.');
    }
}
