<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\BudgetCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BudgetDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display the budget dashboard.
     */
    public function index(Request $request)
    {
        $currentYear = $request->filled('year') ? $request->year : date('Y');
        
        // Overall statistics
        $totalBudgets = Budget::count();
        $activeBudgets = Budget::active()->count();
        $totalCategories = BudgetCategory::active()->count();
        
        // Current year statistics
        $currentYearBudget = Budget::forYear($currentYear)->first();
        
        $stats = [
            'total_budgets' => $totalBudgets,
            'active_budgets' => $activeBudgets,
            'total_categories' => $totalCategories,
            'current_year_budget' => $currentYearBudget,
            'total_expenses' => Expense::approved()->sum('amount'),
            'pending_expenses' => Expense::pending()->count(),
            'over_budget_count' => Budget::active()->get()->filter(fn($b) => $b->isOverBudget())->count()
        ];
        
        // Budget utilization by category
        $categoryUtilization = [];
        if ($currentYearBudget) {
            $categories = $currentYearBudget->categories;
            foreach ($categories as $category) {
                $allocated = $category->pivot->allocated_amount;
                $spent = $category->pivot->spent_amount;
                $utilization = $allocated > 0 ? round(($spent / $allocated) * 100, 2) : 0;
                
                $categoryUtilization[] = [
                    'name' => $category->name,
                    'allocated' => $allocated,
                    'spent' => $spent,
                    'remaining' => $allocated - $spent,
                    'utilization' => $utilization,
                    'status' => $utilization > 90 ? 'warning' : ($utilization > 100 ? 'danger' : 'success')
                ];
            }
        }
        
        // Recent expenses
        $recentExpenses = Expense::with(['budget', 'category'])
                                ->orderBy('created_at', 'desc')
                                ->limit(10)
                                ->get();
        
        // Monthly expense trend
        $monthlyExpenses = Expense::select(
            DB::raw('MONTH(expense_date) as month'),
            DB::raw('SUM(amount) as total')
        )
        ->whereYear('expense_date', $currentYear)
        ->where('status', 'approved')
        ->whereNull('deleted_at')
        ->groupBy(DB::raw('MONTH(expense_date)'))
        ->orderBy('month')
        ->get()
        ->pluck('total', 'month')
        ->toArray();
        
        // Fill missing months with 0
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = $monthlyExpenses[$i] ?? 0;
        }
        
        // Budget status distribution
        $budgetStatusDistribution = [
            'draft' => Budget::byStatus('draft')->count(),
            'approved' => Budget::byStatus('approved')->count(),
            'locked' => Budget::byStatus('locked')->count(),
            'closed' => Budget::byStatus('closed')->count()
        ];
        
        // Years for filter
        $availableYears = Budget::select('fiscal_year')
                               ->distinct()
                               ->orderBy('fiscal_year', 'desc')
                               ->pluck('fiscal_year');
        
        return view('admin.budget.dashboard', compact(
            'stats',
            'categoryUtilization',
            'recentExpenses',
            'monthlyData',
            'budgetStatusDistribution',
            'currentYear',
            'availableYears'
        ));
    }
    
    /**
     * Get budget analytics data for AJAX requests.
     */
    public function analytics(Request $request)
    {
        $year = $request->filled('year') ? $request->year : date('Y');
        
        $data = [
            'total_budget' => Budget::forYear($year)->sum('total_amount'),
            'allocated_budget' => Budget::forYear($year)->sum('allocated_amount'),
            'spent_budget' => Budget::forYear($year)->sum('spent_amount'),
            'total_expenses' => Expense::whereYear('expense_date', $year)->where('status', 'approved')->sum('amount'),
            'pending_expenses_count' => Expense::whereYear('expense_date', $year)->where('status', 'pending')->count(),
            'categories_count' => BudgetCategory::active()->count()
        ];
        
        return response()->json($data);
    }
    
    /**
     * Generate budget reports.
     */
    public function reports(Request $request)
    {
        $year = $request->filled('year') ? $request->year : date('Y');
        
        // Get detailed report data
        $budgets = Budget::forYear($year)
                        ->with(['categories', 'expenses'])
                        ->get();
        
        $reportData = [];
        foreach ($budgets as $budget) {
            $budgetData = [
                'name' => $budget->name,
                'total_amount' => $budget->total_amount,
                'allocated_amount' => $budget->allocated_amount,
                'spent_amount' => $budget->spent_amount,
                'remaining_amount' => $budget->allocated_amount - $budget->spent_amount,
                'utilization_percentage' => $budget->utilization_percentage,
                'categories' => [],
                'total_expenses' => $budget->expenses()->where('status', 'approved')->sum('amount')
            ];
            
            foreach ($budget->categories as $category) {
                $budgetData['categories'][] = [
                    'name' => $category->name,
                    'allocated' => $category->pivot->allocated_amount,
                    'spent' => $category->pivot->spent_amount,
                    'remaining' => $category->pivot->allocated_amount - $category->pivot->spent_amount,
                    'utilization' => $category->pivot->allocated_amount > 0 ? 
                        round(($category->pivot->spent_amount / $category->pivot->allocated_amount) * 100, 2) : 0
                ];
            }
            
            $reportData[] = $budgetData;
        }
        
        return view('admin.budget.reports', compact('reportData', 'year'));
    }
}
