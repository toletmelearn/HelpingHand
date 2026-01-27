<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BudgetController extends Controller
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
        $query = Budget::with(['creator', 'approver'])
                      ->orderBy('fiscal_year', 'desc')
                      ->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year', $request->fiscal_year);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%');
            });
        }
        
        $budgets = $query->paginate(15);
        
        // Get filter options
        $years = Budget::select('fiscal_year')
                      ->distinct()
                      ->orderBy('fiscal_year', 'desc')
                      ->pluck('fiscal_year');
        
        $statuses = [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'locked' => 'Locked',
            'closed' => 'Closed'
        ];
        
        return view('admin.budget.index', compact('budgets', 'years', 'statuses'));
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = BudgetCategory::active()->get();
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        
        return view('admin.budget.create', compact('categories', 'currentYear', 'nextYear'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'fiscal_year' => 'required|integer|min:2020|max:2030',
            'total_amount' => 'required|numeric|min:0',
            'allocations' => 'required|array',
            'allocations.*.category_id' => 'required|exists:budget_categories,id',
            'allocations.*.amount' => 'required|numeric|min:0'
        ]);
        
        // Validate total allocation matches total amount
        $totalAllocation = collect($request->allocations)->sum('amount');
        if (abs($totalAllocation - $request->total_amount) > 0.01) {
            return back()->withErrors(['total_amount' => 'Total allocation must equal total budget amount'])->withInput();
        }
        
        DB::beginTransaction();
        try {
            $budget = new Budget();
            $budget->name = $request->name;
            $budget->description = $request->description;
            $budget->fiscal_year = $request->fiscal_year;
            $budget->total_amount = $request->total_amount;
            $budget->allocated_amount = $totalAllocation;
            $budget->spent_amount = 0;
            $budget->status = 'draft';
            $budget->created_by = Auth::id();
            $budget->save();
            
            // Save allocations
            foreach ($request->allocations as $allocation) {
                $budget->allocateToCategory($allocation['category_id'], $allocation['amount']);
            }
            
            DB::commit();
            
            return redirect()->route('admin.budget.index')
                           ->with('success', 'Budget created successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to create budget: ' . $e->getMessage()])->withInput();
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(Budget $budget)
    {
        $budget->load(['categories', 'expenses.category', 'creator', 'approver']);
        
        $categoryStats = [];
        foreach ($budget->categories as $category) {
            $categoryStats[] = [
                'category' => $category,
                'allocated' => $category->pivot->allocated_amount,
                'spent' => $category->pivot->spent_amount,
                'remaining' => $category->pivot->allocated_amount - $category->pivot->spent_amount,
                'utilization' => $category->pivot->allocated_amount > 0 ? 
                    round(($category->pivot->spent_amount / $category->pivot->allocated_amount) * 100, 2) : 0
            ];
        }
        
        return view('admin.budget.show', compact('budget', 'categoryStats'));
    }
    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Budget $budget)
    {
        if (!$budget->canBeModified()) {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'This budget cannot be modified in its current status.');
        }
        
        $categories = BudgetCategory::active()->get();
        $budget->load('categories');
        
        return view('admin.budget.edit', compact('budget', 'categories'));
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Budget $budget)
    {
        if (!$budget->canBeModified()) {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'This budget cannot be modified in its current status.');
        }
        
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0',
            'allocations' => 'required|array',
            'allocations.*.category_id' => 'required|exists:budget_categories,id',
            'allocations.*.amount' => 'required|numeric|min:0'
        ]);
        
        // Validate total allocation matches total amount
        $totalAllocation = collect($request->allocations)->sum('amount');
        if (abs($totalAllocation - $request->total_amount) > 0.01) {
            return back()->withErrors(['total_amount' => 'Total allocation must equal total budget amount'])->withInput();
        }
        
        DB::beginTransaction();
        try {
            $budget->name = $request->name;
            $budget->description = $request->description;
            $budget->total_amount = $request->total_amount;
            $budget->allocated_amount = $totalAllocation;
            $budget->save();
            
            // Clear existing allocations
            $budget->categories()->detach();
            
            // Save new allocations
            foreach ($request->allocations as $allocation) {
                $budget->allocateToCategory($allocation['category_id'], $allocation['amount']);
            }
            
            DB::commit();
            
            return redirect()->route('admin.budget.show', $budget)
                           ->with('success', 'Budget updated successfully.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['error' => 'Failed to update budget: ' . $e->getMessage()])->withInput();
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Budget $budget)
    {
        if (!$budget->canBeModified()) {
            return redirect()->route('admin.budget.index')
                           ->with('error', 'This budget cannot be deleted in its current status.');
        }
        
        try {
            $budget->delete();
            return redirect()->route('admin.budget.index')
                           ->with('success', 'Budget deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.budget.index')
                           ->with('error', 'Failed to delete budget: ' . $e->getMessage());
        }
    }
    
    /**
     * Approve the budget.
     */
    public function approve(Budget $budget)
    {
        if ($budget->status !== 'draft') {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Only draft budgets can be approved.');
        }
        
        try {
            $budget->status = 'approved';
            $budget->approval_date = now();
            $budget->approved_by = Auth::id();
            $budget->save();
            
            return redirect()->route('admin.budget.show', $budget)
                           ->with('success', 'Budget approved successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Failed to approve budget: ' . $e->getMessage());
        }
    }
    
    /**
     * Lock the budget.
     */
    public function lock(Budget $budget)
    {
        if ($budget->status !== 'approved') {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Only approved budgets can be locked.');
        }
        
        try {
            $budget->status = 'locked';
            $budget->lock_date = now();
            $budget->save();
            
            return redirect()->route('admin.budget.show', $budget)
                           ->with('success', 'Budget locked successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Failed to lock budget: ' . $e->getMessage());
        }
    }
    
    /**
     * Close the budget.
     */
    public function close(Budget $budget)
    {
        if ($budget->status !== 'locked') {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Only locked budgets can be closed.');
        }
        
        try {
            $budget->status = 'closed';
            $budget->close_date = now();
            $budget->save();
            
            return redirect()->route('admin.budget.show', $budget)
                           ->with('success', 'Budget closed successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.budget.show', $budget)
                           ->with('error', 'Failed to close budget: ' . $e->getMessage());
        }
    }
}
