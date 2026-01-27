<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use Illuminate\Http\Request;

class AssetCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = AssetCategory::orderBy('name');
        
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
        
        $categories = $query->paginate(15);
        
        return view('admin.inventory.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.inventory.categories.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name',
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        AssetCategory::create($request->all());
        
        return redirect()->route('admin.inventory.categories.index')->with('success', 'Asset category created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(AssetCategory $assetCategory)
    {
        $assetCategory->load(['assets']);
        return view('admin.inventory.categories.show', compact('assetCategory'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AssetCategory $assetCategory)
    {
        return view('admin.inventory.categories.edit', compact('assetCategory'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AssetCategory $assetCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:asset_categories,name,' . $assetCategory->id,
            'type' => 'required|string|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);
        
        $assetCategory->update($request->all());
        
        return redirect()->route('admin.inventory.categories.index')->with('success', 'Asset category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AssetCategory $assetCategory)
    {
        // Check if category has assets before deletion
        if ($assetCategory->assets()->count() > 0) {
            return redirect()->route('admin.inventory.categories.index')
                ->with('error', 'Cannot delete category with existing assets. Please reassign or delete assets first.');
        }
        
        $assetCategory->delete();
        
        return redirect()->route('admin.inventory.categories.index')->with('success', 'Asset category deleted successfully.');
    }
}
