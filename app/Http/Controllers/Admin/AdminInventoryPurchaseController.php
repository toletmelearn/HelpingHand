<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminInventoryPurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $requests = PurchaseRequest::with(['requester', 'approver'])->orderBy('created_at', 'desc')->get();
        return view('admin.inventory.purchase-requests', compact('requests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'estimated_cost' => 'required|numeric|min:0',
        ]);

        PurchaseRequest::create([
            'item_name' => $request->item_name,
            'quantity' => $request->quantity,
            'estimated_cost' => $request->estimated_cost,
            'requested_by' => Auth::id(),
            'status' => 'pending',
        ]);

        return redirect()->route('admin.inventory.purchase-requests.index')->with('success', 'Purchase request submitted successfully.');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $purchaseRequest = PurchaseRequest::findOrFail($id);
        $purchaseRequest->update([
            'status' => $request->status,
            'approved_by' => Auth::id(),
        ]);

        return redirect()->route('admin.inventory.purchase-requests.index')->with('success', 'Purchase request status updated.');
    }
}
