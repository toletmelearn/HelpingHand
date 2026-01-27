<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Asset::with(['category'])
                    ->orderBy('name');
        
        // Apply filters
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        
        if ($request->filled('location')) {
            $query->where('location', 'LIKE', '%' . $request->location . '%');
        }
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('asset_code', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('vendor', 'LIKE', '%' . $request->search . '%');
            });
        }
        
        $assets = $query->paginate(15);
        $categories = AssetCategory::where('is_active', true)->get();
        
        return view('admin.inventory.assets.index', compact('assets', 'categories'));
    }

    public function create()
    {
        $categories = AssetCategory::where('is_active', true)->get();
        return view('admin.inventory.assets.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'vendor' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date|after_or_equal:purchase_date',
            'condition' => 'required|in:new,good,repair,scrap',
            'status' => 'required|in:active,in_use,under_repair,disposed',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:assets,serial_number',
            'quantity' => 'required|integer|min:1',
        ]);

        $asset = new Asset();
        $asset->asset_code = $this->generateAssetCode($request->category_id);
        $asset->name = $request->name;
        $asset->category_id = $request->category_id;
        $asset->vendor = $request->vendor;
        $asset->cost = $request->cost;
        $asset->purchase_date = $request->purchase_date;
        $asset->warranty_expiry_date = $request->warranty_expiry_date;
        $asset->warranty_details = $request->warranty_details;
        $asset->condition = $request->condition;
        $asset->status = $request->status;
        $asset->description = $request->description;
        $asset->location = $request->location;
        $asset->serial_number = $request->serial_number;
        $asset->quantity = $request->quantity;
        $asset->available_quantity = $request->quantity; // Initially all assets are available
        $asset->save();

        return redirect()->route('admin.assets.index')
                        ->with('success', 'Asset created successfully.');
    }

    public function show(Asset $asset)
    {
        $asset->load(['category']);
        return view('admin.inventory.assets.show', compact('asset'));
    }

    public function edit(Asset $asset)
    {
        $categories = AssetCategory::where('is_active', true)->get();
        return view('admin.inventory.assets.edit', compact('asset', 'categories'));
    }

    public function update(Request $request, Asset $asset)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:asset_categories,id',
            'vendor' => 'nullable|string|max:255',
            'cost' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date|after_or_equal:purchase_date',
            'condition' => 'required|in:new,good,repair,scrap',
            'status' => 'required|in:active,in_use,under_repair,disposed',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'serial_number' => 'nullable|string|max:255|unique:assets,serial_number,' . $asset->id,
            'quantity' => 'required|integer|min:1',
        ]);

        $originalData = $asset->toArray();

        $asset->name = $request->name;
        $asset->category_id = $request->category_id;
        $asset->vendor = $request->vendor;
        $asset->cost = $request->cost;
        $asset->purchase_date = $request->purchase_date;
        $asset->warranty_expiry_date = $request->warranty_expiry_date;
        $asset->warranty_details = $request->warranty_details;
        $asset->condition = $request->condition;
        $asset->status = $request->status;
        $asset->description = $request->description;
        $asset->location = $request->location;
        $asset->serial_number = $request->serial_number;
        $asset->quantity = $request->quantity;
        
        // If quantity changes, adjust available quantity accordingly
        $quantityDiff = $request->quantity - $asset->quantity;
        $asset->available_quantity += $quantityDiff;
        
        $asset->save();

        return redirect()->route('admin.assets.index')
                        ->with('success', 'Asset updated successfully.');
    }

    public function destroy(Asset $asset)
    {
        // Check if asset can be deleted (not in use)
        if ($asset->available_quantity != $asset->quantity) {
            return redirect()->back()
                            ->with('error', 'Cannot delete asset that is currently in use.');
        }

        $asset->delete();

        return redirect()->route('admin.inventory.assets.index')
                        ->with('success', 'Asset deleted successfully.');
    }

    public function issue(Request $request, Asset $asset)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:' . $asset->available_quantity,
            'location' => 'required|string|max:255',
            'assigned_to' => 'nullable|string|max:255',
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Update asset quantities
            $asset->available_quantity -= $request->quantity;
            $asset->save();

            // Create transaction record
            $transaction = new InventoryTransaction();
            $transaction->asset_id = $asset->id;
            $transaction->user_id = Auth::id();
            $transaction->transaction_type = 'issue';
            $transaction->quantity = $request->quantity;
            $transaction->locations = $request->location;
            $transaction->assigned_to = $request->assigned_to;
            $transaction->reason = $request->reason;
            $transaction->save();

            // Update asset status if needed
            if ($asset->available_quantity == 0) {
                $asset->status = 'in_use';
                $asset->save();
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Asset issued successfully.']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to issue asset.']);
        }
    }

    public function return(Request $request, Asset $asset)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'location' => 'required|string|max:255',
            'reason' => 'required|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Update asset quantities
            $asset->available_quantity += $request->quantity;
            $asset->save();

            // Create transaction record
            $transaction = new InventoryTransaction();
            $transaction->asset_id = $asset->id;
            $transaction->user_id = Auth::id();
            $transaction->transaction_type = 'return';
            $transaction->quantity = $request->quantity;
            $transaction->locations = $request->location;
            $transaction->reason = $request->reason;
            $transaction->save();

            // Update asset status if needed
            if ($asset->available_quantity > 0 && $asset->status === 'in_use') {
                $asset->status = 'active';
                $asset->save();
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Asset returned successfully.']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Failed to return asset.']);
        }
    }

    private function generateAssetCode($categoryId)
    {
        $category = AssetCategory::find($categoryId);
        $prefix = 'GEN'; // Default prefix
        
        if ($category) {
            if ($category->type == 'furniture') {
                $prefix = 'FURN';
            } elseif ($category->type == 'lab_equipment') {
                $prefix = 'LAB';
            } elseif ($category->type == 'electronics') {
                $prefix = 'ELEC';
            } elseif ($category->type == 'sports') {
                $prefix = 'SPORT';
            } elseif ($category->type == 'office') {
                $prefix = 'OFF';
            }
        }

        $lastAsset = Asset::where('asset_code', 'LIKE', $prefix . '-%')
                         ->orderBy(DB::raw('CAST(SUBSTRING(asset_code, LENGTH("' . $prefix . '-") + 1) AS UNSIGNED)'), 'DESC')
                         ->first();

        $number = 1;
        if ($lastAsset) {
            $lastNumber = intval(substr($lastAsset->asset_code, strlen($prefix . '-')));
            $number = $lastNumber + 1;
        }

        return $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}