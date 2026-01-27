<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Budget;
use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
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
        $query = Expense::with(['budget', 'category', 'creator', 'approver'])
                       ->orderBy('expense_date', 'desc')
                       ->orderBy('created_at', 'desc');
        
        // Apply filters
        if ($request->filled('budget_id')) {
            $query->where('budget_id', $request->budget_id);
        }
        
        if ($request->filled('category_id')) {
            $query->where('budget_category_id', $request->category_id);
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->filled('date_from')) {
            $query->where('expense_date', '>=', $request->date_from);
        }
        
        if ($request->filled('date_to')) {
            $query->where('expense_date', '<=', $request->date_to);
        }
        
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('description', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('vendor_name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('receipt_number', 'LIKE', '%' . $request->search . '%');
            });
        }
        
        $expenses = $query->paginate(15);
        
        // Get filter options
        $budgets = Budget::active()->pluck('name', 'id');
        $categories = BudgetCategory::active()->pluck('name', 'id');
        
        $statuses = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];
        
        $paymentMethods = [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
            'credit_card' => 'Credit Card',
            'online' => 'Online Payment'
        ];
        
        return view('admin.expense.index', compact('expenses', 'budgets', 'categories', 'statuses', 'paymentMethods'));
    }
    
    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $budgets = Budget::active()->with('categories')->get();
        $categories = BudgetCategory::active()->get();
        
        // Pre-select budget if provided
        $selectedBudget = $request->budget_id ? Budget::find($request->budget_id) : null;
        
        return view('admin.expense.create', compact('budgets', 'categories', 'selectedBudget'));
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'budget_id' => 'required|exists:budgets,id',
            'budget_category_id' => 'required|exists:budget_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'receipt_number' => 'nullable|string|max:100',
            'vendor_name' => 'nullable|string|max:255',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card,online'
        ]);
        
        // Verify budget is active and can accept expenses
        $budget = Budget::find($request->budget_id);
        if (!$budget || !$budget->canBeModified()) {
            return back()->withErrors(['budget_id' => 'Selected budget is not active or cannot accept expenses'])->withInput();
        }
        
        try {
            $expense = new Expense();
            $expense->budget_id = $request->budget_id;
            $expense->budget_category_id = $request->budget_category_id;
            $expense->title = $request->title;
            $expense->description = $request->description;
            $expense->amount = $request->amount;
            $expense->expense_date = $request->expense_date;
            $expense->receipt_number = $request->receipt_number;
            $expense->vendor_name = $request->vendor_name;
            $expense->payment_method = $request->payment_method;
            $expense->status = 'pending';
            $expense->created_by = Auth::id();
            $expense->save();
            
            return redirect()->route('admin.expense.index')
                           ->with('success', 'Expense recorded successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record expense: ' . $e->getMessage()])->withInput();
        }
    }
    
    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        $expense->load(['budget', 'category', 'creator', 'approver']);
        return view('admin.expense.show', compact('expense'));
    }
    
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        if (!$expense->canBeModified()) {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'This expense cannot be modified as it has been approved.');
        }
        
        $budgets = Budget::active()->with('categories')->get();
        $categories = BudgetCategory::active()->get();
        
        return view('admin.expense.edit', compact('expense', 'budgets', 'categories'));
    }
    
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        if (!$expense->canBeModified()) {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'This expense cannot be modified as it has been approved.');
        }
        
        $request->validate([
            'budget_id' => 'required|exists:budgets,id',
            'budget_category_id' => 'required|exists:budget_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'receipt_number' => 'nullable|string|max:100',
            'vendor_name' => 'nullable|string|max:255',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card,online'
        ]);
        
        try {
            $expense->budget_id = $request->budget_id;
            $expense->budget_category_id = $request->budget_category_id;
            $expense->title = $request->title;
            $expense->description = $request->description;
            $expense->amount = $request->amount;
            $expense->expense_date = $request->expense_date;
            $expense->receipt_number = $request->receipt_number;
            $expense->vendor_name = $request->vendor_name;
            $expense->payment_method = $request->payment_method;
            $expense->save();
            
            return redirect()->route('admin.expense.show', $expense)
                           ->with('success', 'Expense updated successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update expense: ' . $e->getMessage()])->withInput();
        }
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        if (!$expense->canBeModified()) {
            return redirect()->route('admin.expense.index')
                           ->with('error', 'This expense cannot be deleted as it has been approved.');
        }
        
        try {
            $expense->delete();
            return redirect()->route('admin.expense.index')
                           ->with('success', 'Expense deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.expense.index')
                           ->with('error', 'Failed to delete expense: ' . $e->getMessage());
        }
    }
    
    /**
     * Approve the expense.
     */
    public function approve(Expense $expense, Request $request)
    {
        if ($expense->status !== 'pending') {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'Only pending expenses can be approved.');
        }
        
        try {
            $expense->approve(Auth::id(), $request->approval_notes);
            
            return redirect()->route('admin.expense.show', $expense)
                           ->with('success', 'Expense approved successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'Failed to approve expense: ' . $e->getMessage());
        }
    }
    
    /**
     * Reject the expense.
     */
    public function reject(Expense $expense, Request $request)
    {
        if ($expense->status !== 'pending') {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'Only pending expenses can be rejected.');
        }
        
        $request->validate([
            'rejection_notes' => 'required|string|max:500'
        ]);
        
        try {
            $expense->reject(Auth::id(), $request->rejection_notes);
            
            return redirect()->route('admin.expense.show', $expense)
                           ->with('success', 'Expense rejected successfully.');
        } catch (\Exception $e) {
            return redirect()->route('admin.expense.show', $expense)
                           ->with('error', 'Failed to reject expense: ' . $e->getMessage());
        }
    }
}
