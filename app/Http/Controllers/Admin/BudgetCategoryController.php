<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetCategoryController extends Controller
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
        $query = BudgetCategory::with(['creator'])
                              ->orderBy('name');
        
        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%');
        }
        
        $categories = $query->paginate(15);
        
        $types = [
            'operational' => 'Operational',
            'capital' => 'Capital',
            'maintenance' => 'Maintenance'
        ];
        
        $statusOptions = [
            '1' => 'Active',
            '0' => 'Inactive'
        ];
        
        return view('admin.budget-category.index', compact('categories', 'types', 'statusOptions'));
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $types = [
            'operational' => 'Operational',
            'capital' => 'Capital',
            'maintenance' => 'Maintenance'
        ];
        
        return view('admin.budget-category.create', compact('types'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:budget_categories,name',
            'description' => 'nullable|string',
            'type' => 'required|in:operational,capital,maintenance',
            'default_allocation_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean'
        ]);
        
        try {
            $category = new BudgetCategory();
            $category->name = $request->name;
            $category->description = $request->description;
            $category->type = $request->type;
            $category->default_allocation_percentage = $request->default_allocation_percentage;
            $category->is_active = $request->boolean('is_active', true);
            $category->created_by = Auth::id();
            $category->save();
            
            return redirect()->route('admin.budget-category.index')
                           ->with('success', 'Budget category created successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to create budget category: ' . $e->getMessage()])
                        ->withInput();
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(BudgetCategory $budgetCategory)
    {
        $budgetCategory->load(['creator', 'expenses', 'budgets']);
        
        $stats = [
            'total_expenses' => $budgetCategory->expenses()->sum('amount'),
            'total_budgets' => $budgetCategory->budgets()->count(),
            'active_budgets' => $budgetCategory->budgets()->wherePivot('allocated_amount', '>', 0)->count()
        ];
        
        return view('admin.budget-category.show', compact('budgetCategory', 'stats'));
    }
    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BudgetCategory $budgetCategory)
    {
        $types = [
            'operational' => 'Operational',
            'capital' => 'Capital',
            'maintenance' => 'Maintenance'
        ];
        
        return view('admin.budget-category.edit', compact('budgetCategory', 'types'));
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BudgetCategory $budgetCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:budget_categories,name,' . $budgetCategory->id,
            'description' => 'nullable|string',
            'type' => 'required|in:operational,capital,maintenance',
            'default_allocation_percentage' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean'
        ]);
        
        try {
            $budgetCategory->name = $request->name;
            $budgetCategory->description = $request->description;
            $budgetCategory->type = $request->type;
            $budgetCategory->default_allocation_percentage = $request->default_allocation_percentage;
            $budgetCategory->is_active = $request->boolean('is_active', true);
            $budgetCategory->save();
            
            return redirect()->route('admin.budget-category.show', $budgetCategory)
                           ->with('success', 'Budget category updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update budget category: ' . $e->getMessage()])
                        ->withInput();
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BudgetCategory $budgetCategory)
    {
        // Check if category is used in any budgets or expenses
        if ($budgetCategory->expenses()->count() > 0) {
            return redirect()->route('admin.budget-category.show', $budgetCategory)
                           ->with('error', 'Cannot delete category that has associated expenses.');
        }
        
        if ($budgetCategory->budgets()->count() > 0) {
            return redirect()->route('admin.budget-category.show', $budgetCategory)
                           ->with('error', 'Cannot delete category that is used in budgets.');
        }
        
        try {
            $budgetCategory->delete();
            return redirect()->route('admin.budget-category.index')
                           ->with('success', 'Budget category deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.budget-category.index')
                           ->with('error', 'Failed to delete budget category: ' . $e->getMessage());
        }
    }
    
    /**
     * Toggle active status.
     */
    public function toggleActive(BudgetCategory $budgetCategory)
    {
        try {
            $budgetCategory->is_active = !$budgetCategory->is_active;
            $budgetCategory->save();
            
            $status = $budgetCategory->is_active ? 'activated' : 'deactivated';
            return redirect()->route('admin.budget-category.show', $budgetCategory)
                           ->with('success', "Budget category {$status} successfully.");
        } catch (\Exception $e) {
            return redirect()->route('admin.budget-category.show', $budgetCategory)
                           ->with('error', 'Failed to toggle category status: ' . $e->getMessage());
        }
    }
}
